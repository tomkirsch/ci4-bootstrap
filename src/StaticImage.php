<?php

namespace Tomkirsch\Bootstrap;

use \Closure;

class StaticImage
{
    /**
     * List of image widths that exist
     * @var array
     */
    public array $widths = [];

    /**
     * The public-facing filename (.htaccess rewrite)
     * @var Closure
     */
    public $file;

    /**
     * <img> alt attribute
     * @var string
     */
    public string $alt;

    /**
     * Maximum supported resolution
     * @var float
     */
    public float $maxResolutionFactor = 1;

    /**
     * Resolution steps
     * @var float
     */
    public float $resolutionStep = 0.5;

    /**
     * Loading attribute for <img>
     * @var string 'auto'|'lazy'|'eager'
     */
    public string $loading = 'auto';

    /**
     * Fetch priority attribute for <img>
     * @var string 'auto'|'high'|'low'
     */
    public string $fetchPriority = 'auto';

    /**
     * Prints newlines
     * @var bool
     */
    public bool $prettyPrint = FALSE;

    /**
     * Renders the <img> attribute
     * @var array|null
     */
    public $imgAttr;


    /**
     * Config instance
     * @var BootstrapConfig $config
     */
    protected $config;

    public function __construct(?BootstrapConfig $config = NULL)
    {
        $this->config = $config ?? new BootstrapConfig();
    }

    /**
     *  Sets the source file to read; $dest can be used to dynamically rename the file; $query can pass a query string to the dest filename string
     * 
     * @param Closure $fileName A function with width and resolution arguments that returns the string file name
     * @param string|null $alt The alt attribute for the <img>
     */
    public function withFile(Closure $fileName, ?string $alt = NULL)
    {
        $this->file = $fileName;
        $this->alt = $alt;
        return $this;
    }

    /**
     * Renders <source>s and optional <img>, ensuring the image is always bigger than the media width.
     * You must pass an array of widths. If an assoc array, the keys will be used as media widths.
     */
    public function renderSources(array $options = []): string
    {
        $this->maxResolutionFactor = $this->config->defaultMaxResolution;
        $this->resolutionStep = $this->config->defaultResolutionStep;
        $this->loading = $this->config->defaultLoading;
        $this->fetchPriority = $this->config->defaultFetchPriority;
        $this->prettyPrint = $this->config->prettyPrint;
        $this->widths = [];
        $this->file = NULL;
        $this->imgAttr = NULL;

        // set public properties
        foreach ($options as $option => $val) {
            // anything passed in $config takes precedent
            if (property_exists($this, $option)) {
                $this->$option = $val;
            }
        }
        if (empty($this->widths)) throw new \Exception("No widths given");
        if (empty($this->file)) throw new \Exception("No file callback given");

        $out = "";
        $keys = array_keys($this->widths);
        arsort($keys); // desc order
        foreach ($this->widths as $key => $width) {
            // assume if the key is less than 10, it's not an assoc array
            $mediaWidth = $key < 10 ? $width : $key;

            // find the bigger image
            $index = array_search($key, $keys);
            if ($index > 0) {
                $width = $this->widths[$keys[$index - 1]];
            } else {
                // no bigger image exists, continue
                continue;
            }

            // compile sources
            $sources = [];
            for ($res = 1; $res <= $this->maxResolutionFactor; $res += $this->resolutionStep) {
                $resWidth = $width * $res;
                $foundWidth = in_array($resWidth, $this->widths) ? $resWidth : NULL;
                // is there no exact match? then find the closest one
                if (!$foundWidth) {
                    $otherWidths = array_filter($this->widths, function ($w) use ($resWidth) {
                        return $w >= $resWidth;
                    });
                    if (count($otherWidths)) $foundWidth = min($otherWidths);
                }
                if (!$foundWidth) continue;
                // call the closure
                if ($src = ($this->file)($foundWidth, $res)) {
                    $src .= $res > 1 ? ' ' . $res . 'x' : "";
                    $sources[] = $src;
                }
            }
            $attr = "srcset";
            $glue = $this->prettyPrint ? ",\n" : ", ";
            $out .= '<source media="(min-width:' . $mediaWidth . 'px)" ' . $attr . '="' . implode($glue, $sources) . '">';
        }
        if ($this->imgAttr) {
            if (empty($this->imgAttr['src'])) {
                $this->imgAttr['src'] = ($this->file)(min($this->widths), 1);
            }
            if (isset($this->loading) && $this->loading !== 'auto') {
                $this->imgAttr['loading'] = $this->loading;
            }
            if (isset($this->fetchPriority) && $this->fetchPriority !== 'auto') {
                $this->imgAttr['fetchpriority'] = $this->fetchPriority;
            }
            $out .= '<img ' . stringify_attributes($this->imgAttr) . ' />';
        }
        return $out;
    }
}

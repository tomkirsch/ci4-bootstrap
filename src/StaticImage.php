<?php

namespace Tomkirsch\Bootstrap;

use CodeIgniter\Images\Image;
use \Closure;

class StaticImage
{

    /**
     * List of image widths that exist
     * @var array
     */
    public $widths = [];

    /**
     * The public-facing filename (.htaccess rewrite)
     * @var Closure
     */
    public $file;

    /**
     * <img> alt attribute
     * @var string
     */
    public $alt;

    /**
     * Maximum supported resolution
     * @var float
     */
    public $maxResolutionFactor = 1;

    /**
     * Resolution steps
     * @var float
     */
    public $resolutionStep = 0.5;

    /**
     * Whether image is lazy-loaded (requires lazysizes JS)
     * @var bool
     */
    public $lazy = FALSE;

    /**
     * Prints newlines
     * @var bool
     */
    public $prettyPrint = FALSE;

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
     * Renders <source> You must pass an array of widths. If an assoc array, the keys will be used as media widths
     */
    public function renderSources(array $options = []): string
    {
        $this->maxResolutionFactor = $this->config->defaultMaxResolution;
        $this->resolutionStep = $this->config->defaultResolutionStep;
        $this->lazy = $this->config->defaultIsLazy;
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
        foreach ($this->widths as $key => $width) {
            $mediaWidth = $key < 10 ? $width : $key;
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
            $prop = $this->lazy ? "data-srcset" : "srcset";
            $glue = $this->prettyPrint ? ",\n" : ", ";
            $out .= '<source media="(min-width:' . $mediaWidth . 'px)" ' . $prop . '="' . implode($glue, $sources) . '">';
        }
        if ($this->imgAttr) {
            $attr = $this->lazy ? "data-src" : "src";
            if (empty($this->imgAttr[$attr])) {
                $this->imgAttr[$attr] = ($this->file)(min($this->widths), 1);
            }
            $out .= '<img ' . stringify_attributes($this->imgAttr) . ' />';
        }
        return $out;
    }
}

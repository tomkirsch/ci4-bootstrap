<?php

namespace Tomkirsch\Bootstrap;

use CodeIgniter\Images\Image;

class DynamicImage
{
	/**
	 * LQIP setting to use the source image at the smallest bootstrap breakpoint width as the placeholder
	 */
	const LQIP_XS = 'xs';
	/**
	 * LQIP setting to use a transparent pixel as a placeholder
	 */
	const LQIP_PIXEL = 'pixel';
	/**
	 * Hires setting that uses the source image's width as the largest possible size to be displayed
	 */
	const HIRES_SOURCE = 'source';

	/**
	 * Attributes for the <picture> element
	 */
	public array $pictureAttr = [];

	/**
	 * Attributes for the <img> element
	 */
	public array $imgAttr = [];

	/**
	 * The image file
	 * @var string|null|Image
	 */
	public $file;

	/**
	 * Source image width/height
	 */
	public ?int $origWidth;
	public ?int $origHeight;

	/**
	 * The public-facing filename (.htaccess rewrite)
	 */
	public ?string $publicFile;

	/**
	 * The public-facing file extension
	 */
	public ?string $publicFileExt;

	/**
	 * <img> alt attribute
	 */
	public ?string $alt;

	/**
	 * GET query to append to the publicFile
	 * @var null|array|string
	 */
	public $query;

	/**
	 * Raw grid layout dimensions, screen widths as keys and values as container widths (can also specify heights with CSV string) (ex: [1200=>190, 992=>480, 768=>"500,350"])
	 */
	public ?array $grid;

	/**
	 * Original col- classes
	 */
	public ?string $colClasses;

	/**
	 * Whether to create the col-* class div on render
	 * @var bool
	 */
	public bool $colWrapper = FALSE;

	/**
	 * Attributes for the col wrapper
	 */
	public array $colWrapperAttr = [];

	/**
	 * Bootstrap gutter width
	 */
	public int $gutterWidth = 0;

	/**
	 * Max-height of container to prevent larger images from being used
	 */
	public int $containerMaxHeight = 0;

	/**
	 * Maximum supported resolution
	 */
	public float $maxResolutionFactor = 1;

	/**
	 * Resolution steps
	 */
	public float $resolutionStep = 0.5;

	/**
	 * Maximum width/height to offer the public. These are hard limits that will never be surpassed.
	 * @var int|string|null
	 */
	public $hiresX, $hiresY;

	/**
	 * Ratio setting 
	 * @var bool|float|string
	 */
	public $ratio = FALSE;

	/**
	 * Whether image should be cropped to ratio
	 * @var bool
	 */
	public bool $ratioCrop = FALSE;

	/**
	 * Ratio wrapper div attributes
	 */
	public array $ratioWrapperAttr = [];

	/**
	 * Native loading hint: lazy | eager | null (auto)
	 */
	public string $loading = 'auto';

	/**
	 * Network priority hint: high | low | auto
	 */
	public string $fetchPriority = 'auto';

	/**
	 * Low quality image placeholder setting
	 * @var string|int|null
	 */
	public $lqip;

	/**
	 * LQIP attributes
	 */
	public ?array $lqipAttr;

	/**
	 * Prints newlines
	 */
	public bool $prettyPrint = FALSE;

	/**
	 * Reset grid after render. Set to TRUE to optimize loops that use the same grid.
	 * You MUST call reset(TRUE) or loop(FALSE) when done looping, so the next call recalculates the grid.
	 */
	public bool $loop = FALSE;

	/**
	 * Dictionary of screen widths and resolution factors
	 */
	protected ?array $resolutionDict;

	/**
	 * Tracks wrappers to close divs
	 */
	protected ?int $wrapCount;

	/**
	 * Array of col widths parsed from $colClasses
	 */
	protected ?array $cols;

	/**
	 * Config instance
	 * @var BootstrapConfig $config
	 */
	protected $config;

	/**
	 * Last colClasses parsed
	 */
	protected string $lastColClasses = "";

	public function __construct(?BootstrapConfig $config = NULL)
	{
		$this->config = $config ?? new BootstrapConfig();
		$this->reset();
	}

	/**
	 * Reset grid calculations and preferences to the config/defaults
	 */
	public function reset(bool $resetGrid = TRUE)
	{
		$this->file = NULL;
		$this->origWidth = $this->origHeight = NULL;
		$this->publicFile = NULL;
		$this->publicFileExt = NULL;
		$this->query = NULL;
		$this->alt = NULL;
		$this->colClasses = NULL;

		if ($resetGrid) {
			$this->resetGrid();
		}
		$this->maxResolutionFactor = $this->config->defaultMaxResolution;
		$this->ratio($this->config->defaultUseRatio);
		$this->loading($this->config->defaultLoading);
		$this->fetchPriority($this->config->defaultFetchPriority);
		$this->hires($this->config->defaultHiresWidth, $this->config->defaultHiresHeight, $this->config->defaultResolutionStep);
		$this->lqip($this->config->defaultLqip);
		$this->prettyPrint($this->config->prettyPrint);
		$this->attr([], []);
	}

	/**
	 * Resets the grid
	 */
	public function resetGrid()
	{
		$this->grid = NULL;
		$this->colWrapper = FALSE;
		$this->gutterWidth = $this->config->defaultGutterWidth;
		$this->containerMaxHeight = 0;
		return $this;
	}

	/**
	 * Utility  - resize image maintaining aspect ratio
	 */
	public function reproportion(int $width, int $height = 0, string $masterDim = 'auto'): array
	{
		if ($masterDim !== 'width' && $masterDim !== 'height') {
			if ($width > 0 && $height > 0) {
				$masterDim = ((($this->origHeight / $this->origWidth) - ($height / $width)) < 0) ? 'width' : 'height';
			} else {
				$masterDim = ($height === 0) ? 'width' : 'height';
			}
		} elseif (($masterDim === 'width' && $width === 0) || ($masterDim === 'height' && $height === 0)
		) {
			throw new \Exception("Invalid sizes passed");
		}

		if ($masterDim === 'width') {
			$height = (int) floor($width * $this->origHeight / $this->origWidth);
		} else {
			$width = (int) floor($this->origWidth * $height / $this->origHeight);
		}
		return [$width, $height];
	}

	/**
	 *  Sets the source file to read; $dest can be used to dynamically rename the file; $query can pass a query string to the dest filename string
	 * 
	 * @param string $file Local source image file
	 * @param string|null $alt The alt attribute for the <img>
	 * @param string|null $publicFile Public-facing file, possibly rewritten with .htaccess
	 * @param string|array|null $query GET parameters to append to the destination
	 */
	public function withFile(string $file, ?string $alt = NULL, ?string $publicFile = NULL, $query = NULL)
	{
		$this->file = $file;
		$this->alt = $alt;
		$this->publicFile = $publicFile;
		$this->query = $query;
		return $this;
	}

	/**
	 * If you know the source image size, use this to avoid expensive getimagesize() calls
	 */
	public function withSize(int $width, int $height)
	{
		$this->origWidth = $width;
		$this->origHeight = $height;
		return $this;
	}

	/**
	 * Sets the raw grid array
	 * @param array|null $grid Pass keys as screen widths, values as expected container widths. Values can also be comma separated to specify heights.
	 */
	public function grid(?array $grid)
	{
		$this->grid = $grid;
		return $this;
	}

	/**
	 * Sets the grid using col-* classes. If $wrapperAttr is set, a wrapper div will be automagically be printed with the passed col classes
	 * @param string|array|null $colClasses The column names (ex "col-md-5 col-lg-2")
	 * @param array|null $wrapperAttr If not NULL, a column wrapper element will automatically be added with the col classes
	 * @param int $gutterWidth Subtracts pixels from the bootstrap containers. Set to zero with gutterless layouts
	 * @param int $containerMaxHeight Use to prevent large images being displayed on containers with max-height CSS
	 */
	public function cols($colClasses = NULL, ?array $wrapperAttr = NULL, ?int $gutterWidth = NULL, ?int $containerMaxHeight = NULL)
	{
		$this->colClasses = $colClasses;
		$this->colWrapperAttr = $wrapperAttr;
		$this->gutterWidth = $gutterWidth ?? $this->gutterWidth ?? 0;
		$this->containerMaxHeight = $containerMaxHeight ?? $this->containerMaxHeight ?? 0;
		return $this;
	}

	/**
	 * Set loading attribute for native lazy loading
	 * @param string $value 'lazy', 'eager', or 'auto'
	 */
	public function loading(string $value)
	{
		$this->loading = $value;
		return $this;
	}

	/**
	 * Set fetch priority hint
	 * @param string $value 'high', 'low', or 'auto'
	 */
	public function fetchPriority(string $value)
	{
		$this->fetchPriority = $value;
		return $this;
	}


	/**
	 * Enable looping - if true, grid will not be reset after render
	 */
	public function loop(bool $value)
	{
		$this->loop = $value;
		return $this;
	}

	/**
	 * Enable support for high resolution images.
	 * @param int|string|null $xFactor Pass string 'source' to match the source image width, a scale factor (1-10), or pixel width to limit the image width. Null will disable retina sources.
	 * @param int|string|null $yFactor Pass string 'source' to match the source image height or a pixel value. NULL not check any limits on the height
	 * @param float|null $resolutionStep Determines how many steps we want to offer. For example, hires(2, 0.5) will generate 2x and 1.5x versions
	 */
	public function hires($xFactor, $yFactor = NULL, ?float $resolutionStep = NULL)
	{
		$this->hiresX = is_numeric($xFactor) ? floatval($xFactor) : $xFactor;
		$this->hiresY = $yFactor;
		$this->resolutionStep = $resolutionStep ?? $this->config->defaultResolutionStep;
		return $this;
	}

	/**
	 * Sets the ratio. Requires CSS to apply padding to the wrapper.
	 * 
	 * @param bool|string|float $value FALSE will disable ratio padding, TRUE will use the image's original ratio. Strings like "16:9" or "16/9" work as well.
	 * @param bool $crop Crop the image inside the ratio container. If FALSE, the image will scale down and be centered
	 * @param array|null $wrapperAttr Attributes to add to the ratio wrapper html
	 */
	public function ratio($value, bool $ratioCrop = FALSE, array $ratioWrapperAttr = [])
	{
		$this->ratio = $value;
		$this->ratioCrop = $ratioCrop;
		$this->ratioWrapperAttr = $ratioWrapperAttr;
		return $this;
	}

	/**
	 * Sets the Low-Quality Image Placeholder (LQIP)
	 * @param string|int|null $src Possible values - string 'xs': the source image at the smallest bootstrap container size, string 'pixel': a transparent pixel, int width: dynamically resized width in pixels (ie. 100px), string hex: a solid color (ie. '#FF0000'), string otherFileName: an alternate file
	 * @param array $attr Attributes to attach to the LQIP element
	 */
	public function lqip(?string $src = NULL, array $attr = [])
	{
		$this->lqip = $src;
		$this->lqipAttr = $attr;
		return $this;
	}

	/**
	 * Sets attributes for the <picture> and <img> elements
	 */
	public function attr(array $pictureAttr, array $imgAttr)
	{
		$this->pictureAttr = $pictureAttr;
		$this->imgAttr = $imgAttr;
		return $this;
	}

	/**
	 * PrettyPrint
	 */
	public function prettyPrint(bool $value)
	{
		$this->prettyPrint = $value;
		return $this;
	}

	/**
	 * Renders the <picture> element
	 */
	public function render(array $options = []): string
	{
		// set properties
		foreach ($options as $option => $val) {
			$this->setVal($option, $val);
		}

		// validate Image file, get orig dimensions if not set. this will throw an exception if the file is missing
		try {
			$this->checkFile();
		} catch (\Exception $e) {
			log_message('error', "DynamicImage error: " . $e->getMessage());
			$attr = array_merge($this->imgAttr, ['alt' => "Missing image"]);
			return '<picture' . stringify_attributes($this->pictureAttr) . '>'
				. '<img src="' . $this->pixel64() . '" ' . stringify_attributes($attr) . '>'
				. '</picture>';
		}

		// validate grid
		if (!$this->grid || $this->lastColClasses !== $this->colClasses) {
			$this->parseColNames();
			// make grid using col class names and bootstrap breakpoints
			$this->grid($this->cols2Grid());
			// store last to optimize loops
			$this->lastColClasses = $this->colClasses ?? "";
		}
		$mediaDict = $this->grid;
		// are we using a custom ratio that is larger than our own ratio? then offset the container widths since it will be "zoomed in" behind the crop, and we want the correct-sized image file
		if ($this->ratio && $this->ratio !== TRUE && $this->ratioCrop) {
			$sourceRatio = $this->sourceRatio();
			$containerRatio = $this->parseRatio($this->ratio);
			if ($sourceRatio < $containerRatio) {
				$percent = (100 * 100) / ($sourceRatio * 100);
			} else {
				$percent = $sourceRatio * 100;
			}
			if ($percent > 100) {
				foreach ($this->grid as $media => $dim) {
					if (is_string($dim)) {
						list($w, $h) = explode(",", $dim);
						$w = round(($percent * intval($w)) / 100);
						$h = round(($percent * intval($h)) / 100);
						$mediaDict[$media] = "$w,$h";
					} else {
						$mediaDict[$media] = round(($percent * $dim) / 100);
					}
				}
			}
		}
		// put our biggest media queries first and create resolution dictionary
		krsort($mediaDict);
		$this->makeResolutionDict($mediaDict);

		$this->wrapCount = 0;
		$out = "";

		// make col wrapper
		$out .= $this->renderColWrapper();

		// make ratio wrapper(s)
		$out .= $this->renderRatioWrapper();

		// render picture and img
		$out .=  $this->renderPicture();

		// close the wrappers
		$out .= str_repeat('</div>' . $this->nl(), $this->wrapCount);

		// reset stuff. if we're looping, we don't want to reset the grid
		$this->reset(!$this->loop);

		return $out;
	}

	/**
	 * Render <picture> element
	 */
	protected function renderPicture(): string
	{
		$out = '';
		// picture
		$this->pictureAttr ??= [];
		$out .= '<picture' . stringify_attributes($this->pictureAttr) . '>' . $this->nl();
		foreach ($this->resolutionDict as $mediaWidth => $data) {
			$sourceAttr = ["media" => '(min-width:' . $mediaWidth . 'px)'];
			$sources = [];
			foreach ($data as $factor => $width) {
				// are we not supporting hi res devices? then skip
				if (floatval($factor) > 1 && empty($this->hiresX)) continue;
				$src = $this->getPublicFileName($width);
				// use a key here, so we don't get a bloated thing like "foo-800.jpg 4x, foo-800.jpg 3x, foo-800.jpg 2x"
				$key = $src;
				if (floatval($factor) > 1) $src .= ' ' . $factor . 'x';
				$sources[$key] = $src;
			}
			if ($this->publicFileExt === 'webp' || $this->publicFileExt === 'jp2') {
				$sourceAttr['type'] = 'image/' . $this->publicFileExt;
			}
			$sourceAttr['srcset'] = $this->nl() . implode(', ' . $this->nl(), array_values($sources));
			$out .= '<source' . stringify_attributes($sourceAttr) . '>' . $this->nl();
		}
		// write the <img>
		$out .= $this->renderPictureImg();
		$out .= '</picture>' . $this->nl();
		return $out;
	}

	/**
	 * Renders the <img> element inside of <picture>
	 */
	protected function renderPictureImg(): string
	{
		$out = '';
		$imgAttr = array_merge($this->getLqipAttr(FALSE), $this->imgAttr ?? []);
		$imgAttr["alt"] = $this->alt ?? "";

		// loading attribute
		if (!empty($this->loading) && $this->loading !== 'auto') {
			$imgAttr['loading'] = $this->loading;
		}
		// fetchpriority attribute
		if (!empty($this->fetchPriority) && $this->fetchPriority !== 'auto') {
			$imgAttr['fetchpriority'] = $this->fetchPriority;
		}

		$out .= '<img ' . stringify_attributes($imgAttr) . '>' . $this->nl();
		return $out;
	}

	/**
	 * Render the col-* wrapper div
	 */
	protected function renderColWrapper(): string
	{
		if (!$this->colWrapper) return "";
		// add the string supplied in cols() call
		$wrapperAttr = $this->ensureAttr('class', $this->colClasses . " dyn_colwrapper", $this->colWrapperAttr);
		$this->wrapCount++;
		return '<div ' . stringify_attributes($wrapperAttr) . '>' . $this->nl();
	}

	/**
	 *  Set the src and alt attributes of LQIP <img>
	 */
	protected function getLqipAttr(bool $isOwnImg): array
	{
		$attr = $isOwnImg ? [] : $this->lqipAttr ?? [];
		$attr["data-dyn_lqip"] = $isOwnImg ? "separate" : "integrated";
		switch ($this->lqip) {
			case static::LQIP_XS:
				// use smallest container width
				$attr['src'] = $this->getPublicFileName($this->getSmallestWidth());
				break;
			case static::LQIP_PIXEL:
				// transparent pixel base64
				$attr['src'] = $this->pixel64();
				break;
			default:
				if (is_string($this->lqip) && substr($this->lqip, 0, 1) === '#') {
					// hex color
					$attr['src'] = $this->svgRect64($this->lqip, $this->origWidth, $this->origHeight);
				} else if (is_numeric($this->lqip)) {
					// it's a specific width
					$attr['src'] = $this->getPublicFileName(intval($this->lqip));
				} else if (!empty($this->lqip)) {
					// something custom, I guess
					$attr['src'] = $this->lqip;
				} else {
					// empty/null. use the first image as presribed by resolutionDict
					$attr['src'] = $this->getPublicFileName($this->getSmallestWidth());
				}
		} // endswitch
		return $attr;
	}

	protected function getSmallestWidth(): int
	{
		$w = PHP_INT_MAX;
		foreach ($this->resolutionDict as $res => $data) {
			$w = min($w, min($data));
		}
		return $w === PHP_INT_MAX ? 1 : $w;
	}

	/**
	 * Render the ratio wrapper div
	 */
	protected function renderRatioWrapper(): string
	{
		// is ratio NULL/FALSE? get out.
		if (empty($this->ratio)) return "";

		$this->ratioWrapperAttr ??= [];
		$containerRatio = $this->parseRatio($this->ratio === TRUE ? "$this->origWidth/$this->origHeight" : $this->ratio);
		$sourceRatio = $this->sourceRatio();
		$out = "";

		$ratioWrapperAttr = $this->getRatioAttr($this->ratioWrapperAttr);
		$ratioWrapperAttr = $this->ensureAttr("class", "dyn_wrapper", $ratioWrapperAttr);
		$containerOrient = $containerRatio < 1 ? "portrait" : "landscape";
		$ratioWrapperAttr = $this->ensureAttr("class", "dyn_orient_wrapper-$containerOrient", $ratioWrapperAttr);
		$srcOrient = $this->getOrientation($this->origWidth, $this->origHeight);
		$ratioWrapperAttr = $this->ensureAttr("class", "dyn_orient_src-$srcOrient", $ratioWrapperAttr);

		$fit = "none";
		$cropAttr = NULL;
		// is the ratio different than the source image? 
		if (round($containerRatio, 4) !== round($sourceRatio, 4)) {
			//write the cropping div, if ratioCrop is true
			if ($this->ratioCrop) {
				$fit = "crop";
				$cropAttr = $this->getRatioAttr();
				$containerRatio *= 100;
				$sourceRatio *= 100;
				$cropAttr["style"] .= ";padding-bottom:$sourceRatio%";
				/*
				if ($containerRatio !== $sourceRatio) {
					if ($sourceRatio > $containerRatio) {
						$sourceRatio = (100 * 100) / $sourceRatio;
					}	
					$ratioSide = ($srcOrient === 'landscape') ? 'right' : 'bottom';
					$otherSide = ($srcOrient === 'landscape') ? 'bottom' : 'right';
					$cropAttr["style"] .= ";padding-$otherSide:$containerRatio%;padding-$ratioSide:$sourceRatio%";
					
				}
				*/
			} else {
				// ratioCrop is false, so we just want to fit the image inside the container
				$fit = "contain";
			}
		}
		$ratioWrapperAttr = $this->ensureAttr("class", "dyn_$fit", $ratioWrapperAttr);
		$out .= '<div' . stringify_attributes($ratioWrapperAttr) . '>' . $this->nl();
		$this->wrapCount++;
		if ($cropAttr) {
			$out .= '<div data-dyn_crop="' . $this->origWidth . '/' . $this->origHeight . '" ' . stringify_attributes($cropAttr) . '>' . $this->nl();
			$this->wrapCount++;
		}
		return $out;
	}

	/**
	 * Take an array of column classes (col-*) and transform them into media widths and container widths
	 * input: ['col-10', 'col-md-6', 'col-xl-2'] output: [1200=>190, 992=>480, 768=>360, 0=>450]
	 */
	protected function cols2Grid(bool $forceZeroMediaSize = TRUE): array
	{
		// create col dictionary: [10=>'xs' 6=>'md', 2=>'lg']
		$colDict = [];
		foreach ($this->cols as $colClass) {
			$matches = [];
			if (!preg_match('/^col-([a-z]+)-(\d+)/', $colClass, $matches)) continue;
			$colDict[intval($matches[2])] = $matches[1];
		}
		// sort the dictionary by $colSize, from smallest bootstrap size to largest, so bigger cols overwrite mediaDict keys generated by smaller ones
		uasort($colDict, function (string $a, string $b) {
			$aVal = ($a === 'xs') ? 0 : $this->config->container($a);
			$bVal = ($b === 'xs') ? 0 : $this->config->container($b);
			return ($aVal <=> $bVal);
		});

		$grid = [];
		// are there no specified cols? then assume it's full container width
		if (empty($colDict)) {
			// no columns specified, so we just loop the containers
			$grid[0] = min($this->config->containers()); // handle xs size, since it's not defined in containers
			// pull media width from breakpoints, and image widths from containers
			foreach ($this->config->containers() as $containerSize => $containerWidth) {
				$mediaWidth = $this->config->breakpoint($containerSize);
				$grid[$mediaWidth] = $this->config->container($containerSize) - ($this->gutterWidth * 2);
			}
		} else {
			// col-* classes were indicated, so we must calculate the image widths
			foreach ($this->config->containers() as $containerSize => $containerWidth) {
				foreach ($colDict as $colNum => $colSize) {
					$fraction = $colNum / $this->config->gridCols; // divide by 12, or however many cols there are
					if ($colSize === 'xs' || $this->config->container($colSize) <= $this->config->container($containerSize)) {
						// this col is smaller or equal to the container we're looking at. process it.
						$imageWidth = ceil($containerWidth * $fraction) - ($this->gutterWidth * 2);
						$mediaWidth = $this->config->breakpoint($containerSize);
					} else {
						// out of bounds/not needed
						continue;
					}
					// we only want one <source> for each media width. Our colDict sorting ensures it'll be the correct one.
					$grid[$mediaWidth] = $imageWidth;
				}
			}
		} //endif
		if ($forceZeroMediaSize && !isset($grid[0])) {
			$minContainer = min($this->config->containers());
			// if we have a col class without a size (ie col-6), we need to divide the container width
			if (in_array('xs', $colDict)) {
				$colNum = array_search('xs', $colDict);
				$fraction = $colNum / $this->config->gridCols;
				$grid[0] = ceil($minContainer * $fraction) - ($this->gutterWidth * 2);
			} else {
				$grid[0] = $minContainer - ($this->gutterWidth * 2);
			}
		}
		// is there containerMaxHeight? set it
		if ($this->containerMaxHeight) {
			foreach ($grid as $media => $width) {
				$grid[$media] = "$width,$this->containerMaxHeight";
			}
		}
		// put our biggest media queries first
		krsort($grid);
		return $grid;
	}

	/**
	 * Array of screen widths, values are array of resolutions and widths
	 * Input: [1200=>190, 992=>480, 768=>360, 0=>450]
	 * Output:
	 * [
	 * 1200 		=> ['4'=>760, '3'=>570, '2'=>380, '1'=>190],
	 * 992			=> ['4'=>1920, '3'=>1440, '2'=>960, '1'=>480],
	 * 0			=> ['4'=>1800, '3'=>1350, '2'=>900, '1'=>450]
	 * ]
	 */
	protected function makeResolutionDict(array $mediaDict)
	{
		$maxResolution = $this->maxResolutionFactor;
		// figure out our max width
		if (is_numeric($this->hiresX) && (float) $this->hiresX <= 10) {
			// we were given a resolution, ensure maxWidth doesn't exceed orig image
			$maxResolution = (float) $this->hiresX;
			$maxWidth = min($this->origWidth, $this->origWidth * $maxResolution);
		} else if (is_numeric($this->hiresX)) {
			// we were given a hard px value
			$maxWidth = min($this->origWidth, (int) $this->hiresX);
		} else {
			// default to the source image width
			$maxWidth = $this->origWidth;
		}
		if ($maxResolution < 1) throw new \Exception("Invalid max resolution: $maxResolution");

		// if hiresY was specified, ensure we use it
		$maxHeight = $this->hiresY === self::HIRES_SOURCE ? $this->origHeight : $this->hiresY;
		if ($maxHeight !== NULL && !is_int($maxHeight)) throw new \Exception("Invalid hires height: $maxHeight");

		$this->resolutionDict = [];
		foreach ($mediaDict as $mediaWidth => $dim) {
			if (is_string($dim)) {
				list($containerWidth, $containerHeight) = explode(",", $dim);
				$containerWidth = intval($containerWidth);
				$containerHeight = intval($containerHeight);
			} else {
				$containerWidth = $dim;
				$containerHeight = 0;
			}
			// loop through possible resolutions, highest first
			for ($i = $maxResolution; $i >= 1; $i -= $this->resolutionStep) {
				// calculate the final dimensions at this resolution, but limiting the width (and height if needed)
				list($hiresWidth, $hiresHeight) = $this->reproportion(floor($containerWidth * $i), floor($containerHeight * $i));
				if ($hiresWidth > $maxWidth || ($maxHeight && $hiresHeight > $maxHeight)) continue; // resulting image was too big, skip this resolution
				$this->resolutionDict[$mediaWidth] ??= [];
				$this->resolutionDict[$mediaWidth][(string) $i] = $hiresWidth;
			}
		}
		// ensure we have a zero width option!
		if (!isset($this->resolutionDict[0])) {
			$this->resolutionDict[0][1] = $maxWidth;
		}
	}

	/**
	 * Filter and sort the col-* class names
	 */
	protected function parseColNames()
	{
		$this->cols = [];
		if (!empty($this->colClasses)) {
			$colClasses = is_string($this->colClasses) ? explode(' ', $this->colClasses) : $this->colClasses;
			foreach ($colClasses as $className) {
				if ($className === "col") $className = "col-xs-12"; // make this explicit for our class
				if (!preg_match('/^col-([a-z]+)/', $className)) continue; // not a col class
				if (!preg_match('/^col-[a-z]+-\d+/', $className)) $className .= "-12"; // col that fills remaining space. (ex "col-md") Assume the widest.
				$this->cols[] = $className;
			}
			$this->cols = array_unique($this->cols);
		}
	}

	/**
	 * Ensures file is an Image and we have width/height
	 */
	protected function checkFile()
	{
		if (!is_a($this->file, "CodeIgniter\Images\Image")) $this->file = new Image($this->file, FALSE);
		if (!$this->origWidth || !$this->origHeight) {
			$props = $this->file->getProperties(TRUE);
			$this->origWidth = $props["width"];
			$this->origHeight = $props["height"];
		}
		$this->publicFile ??= str_replace("." . $this->file->getExtension(), "", $this->file);
		$this->publicFileExt ??= $this->file->getExtension();
	}

	/**
	 * Gets the public-facing file name based on width
	 */
	protected function getPublicFileName(int $width): string
	{
		$file = $this->config->dynamicImageFileName($this->publicFile, $this->publicFileExt, $width);
		// query string
		$q = '';
		if (!empty($this->query)) {
			if (is_string($this->query)) {
				$q = strpos($this->query, '?') === FALSE ? $this->query : substr($this->query, 1);
			} else if (is_array($this->query)) {
				$q = http_build_query($this->query);
			}
			$q = strpos($file, "?") === FALSE ? "?$q" : "&$q";
		}
		return $file . $q;
	}

	/**
	 * Gets float ratio
	 */
	public function sourceRatio(): float
	{
		return round($this->origHeight / $this->origWidth, 5);
	}

	/**
	 * Gets attributes for ratio wrapper
	 */
	protected function getRatioAttr(array $attr = []): array
	{
		if ($this->ratio === TRUE) {
			// use source image ratio
			$style = "--aspect-ratio:$this->origWidth/$this->origHeight";
		} else {
			// 16/9 or 16:9 or 1.775
			$style = "--aspect-ratio:$this->ratio";
		}
		return $this->ensureAttr('style', $style, $attr);
	}

	/**
	 * Parses ratio string like "16/9" and "16:9" to float
	 */
	protected function parseRatio($ratio): ?float
	{
		if (is_string($ratio)) {
			$matches = [];
			if (preg_match('/^(\d+)[:\/](\d+)$/', $ratio, $matches)) {
				array_shift($matches);
				list($width, $height) = $matches;
				return floatval($width) / floatval($height);
			}
		}
		return floatval($ratio);
	}

	/**
	 * Ensures the array key is set, and if already set, it adds the attributes. Also works with inline styles.
	 */
	protected function ensureAttr(string $attrName, string $attrValue, ?array $attr = NULL): array
	{
		$attr ??= [];
		$sep = $attrName === 'style' ? ';' : ($attrName === 'class' ? ' ' : '');
		if (isset($attr[$attrName])) {
			$attr[$attrName] .= $sep . $attrValue;
		} else {
			$attr[$attrName] = $attrValue;
		}
		return $attr;
	}

	/**
	 * Returns portrait or landscape
	 */
	protected function getOrientation(int $width, int $height): string
	{
		return $height > $width ? "portrait" : "landscape";
	}

	/**
	 * Gets newline if prettyPrint is TRUE
	 */
	protected function nl(): string
	{
		return $this->prettyPrint ? "\n" : '';
	}

	/**
	 * Utility - transparent pixel LQIP
	 */
	public function pixel64()
	{
		return 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
	}

	/**
	 * Utility - base64 data to generate solid color LQIP using SVG rect
	 */
	public function svgRect64(string $color, int $width, int $height): string
	{
		$svg = '<svg preserveAspectRatio="none" viewBox="0 0 ' . $width . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg"><rect width="' . $width . '" height="' . $height . '" fill="' . $color . '" /></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode($svg);
	}

	protected function setVal($name, $value)
	{
		switch ($name) {
			case "cols":
				if (is_string($value)) {
					// assume we want colClasses string, which must be parsed
					$this->colClasses = $value;
					return;
				}
				break;
			case "size":
				if (is_string($value)) {
					$sep = stristr(",", $value) ? "," : "x";
					list($width, $height) = explode($sep, $value);
					$this->origWidth = intval($width);
					$this->origHeight = intval($height);
				} else {
					$this->origWidth = intval($value[0]);
					$this->origHeight = intval($value[1]);
				}
				return;
			case "loop":
				break;
			default:
				if (!property_exists($this, $name)) {
					throw new \Exception("Invalid option: $name");
				}
		}
		$this->$name = $value;
	}
}

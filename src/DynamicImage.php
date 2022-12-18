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
	 * @var array
	 */
	public $pictureAttr = [];

	/**
	 * Attributes for the <img> element
	 * @var array
	 */
	public $imgAttr = [];

	/**
	 * The image file
	 * @var Image
	 */
	public $file;

	/**
	 * Source image width/height
	 * @var int
	 */
	public $origWidth, $origHeight;

	/**
	 * The public-facing filename (.htaccess rewrite)
	 * @var string
	 */
	public $publicFile;

	/**
	 * The public-facing file extension
	 * @var string
	 */
	public $publicFileExt;

	/**
	 * <img> alt attribute
	 * @var string
	 */
	public $alt;

	/**
	 * GET query to append to the publicFile
	 * @var null|array|string
	 */
	public $query;

	/**
	 * Raw grid layout dimensions, screen widths as keys and values as container widths (can also specify heights with CSV string) (ex: [1200=>190, 992=>480, 768=>"500,350"])
	 * @var array
	 */
	public $grid;

	/**
	 * Original col- classes
	 * @var string
	 */
	public $colClasses;

	/**
	 * Whether to create the col-* class div on render
	 * @var bool
	 */
	public $colWrapper = FALSE;

	/**
	 * Attributes for the col wrapper
	 * @var null|array
	 */
	public $colWrapperAttr;

	/**
	 * Bootstrap gutter width
	 * @var int
	 */
	public $gutterWidth = 0;

	/**
	 * Max-height of container to prevent larger images from being used
	 * @var int
	 */
	public $containerMaxHeight = 0;

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
	 * Maximum width/height to offer the public. These are hard limits that will never be surpassed.
	 * @var int
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
	public $ratioCrop = FALSE;

	/**
	 * Ratio wrapper div attributes
	 * @var array
	 */
	public $ratioWrapperAttr = [];

	/**
	 * Whether image is lazy-loaded (requires lazysizes JS)
	 * @var bool
	 */
	public $lazy = FALSE;

	/**
	 * Low quality image placeholder setting
	 * @var string|int|null
	 */
	public $lqip;

	/**
	 * LQIP attributes
	 * @var null|array
	 */
	public $lqipAttr;

	/**
	 * LQIP is separate element? (Requires CSS positioning)
	 * @var bool
	 */
	public $lqipSeparate = FALSE;

	/**
	 * Prints newlines
	 * @var bool
	 */
	public $prettyPrint = FALSE;

	/**
	 * Reset grid after render. Set to FALSE to optimize loops that use the same grid
	 * @var bool
	 */
	public $resetGrid = TRUE;

	/**
	 * Dictionary of screen widths and resolution factors
	 * @var array
	 */
	protected $resolutionDict;

	/**
	 * Tracks wrappers to close divs
	 * @var int
	 */
	protected $wrapCount;

	/**
	 * Array of col widths parsed from $colClasses
	 */
	protected $cols;

	/**
	 * Config instance
	 * @var BootstrapConfig $config
	 */
	protected $config;

	public function __construct(?BootstrapConfig $config = NULL)
	{
		$this->config = $config ?? new BootstrapConfig();
		$this->reset();
	}

	/**
	 * Reset grid calculations and preferences to the config/defaults
	 */
	public function reset()
	{
		$this->file = NULL;
		$this->origWidth = $this->origHeight = NULL;
		$this->publicFile = NULL;
		$this->publicFileExt = NULL;
		$this->query = NULL;
		$this->alt = NULL;

		if ($this->resetGrid) {
			$this->resetGrid();
		}
		$this->maxResolutionFactor = $this->config->defaultMaxResolution;
		$this->ratio($this->config->defaultUseRatio);
		$this->lazy($this->config->defaultIsLazy);
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
		$this->colClasses = NULL;
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
	 * Enable lazy-load - if true, HTML uses data-src and data-srcset attributes
	 */
	public function lazy(bool $value)
	{
		$this->lazy = $value;
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
	 * @param bool $lqipSeparate This must be true if LQIP is an lazy-loaded <img> element. Positioning CSS is required to lay it on top of the <picture>
	 */
	public function lqip(?string $src = NULL, array $attr = [], bool $lqipSeparate = FALSE)
	{
		$this->lqip = $src;
		$this->lqipAttr = $attr;
		$this->lqipSeparate = $lqipSeparate;
		if ($this->lqipSeparate) {
			$this->lazy(TRUE);
		}
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
		// reset grid is true? then do it now so we don't keep the previous grid
		if (!empty($options["resetGrid"])) {
			$this->resetGrid();
		}

		// set public properties
		foreach ($options as $option => $val) {
			// anything passed in $config takes precedent
			if (property_exists($this, $option)) {
				$this->$option = $val;
			}
		}

		// validate Image file
		$this->checkFile();
		// validate grid
		if (!$this->grid) {
			$this->parseColNames();
			// make grid using col class names and bootstrap breakpoints
			$this->grid($this->cols2Grid());
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

		// reset stuff
		$this->reset();

		return $out;
	}

	/**
	 * Render <picture> element
	 */
	protected function renderPicture(): string
	{
		$this->pictureAttr ??= [];
		// LQIP outside of <picture>.. we place it first, so it's behind the picture
		$out = $this->renderLqipOwnImg();
		// picture
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
			$attrName = $this->lazy ? 'data-srcset' : 'srcset';
			$sourceAttr[$attrName] = $this->nl() . implode(', ' . $this->nl(), array_values($sources));
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

		// lazyload
		if ($this->lazy) {
			$imgAttr = $this->ensureAttr('class', 'lazyload', $imgAttr);
			// is LQIP it's own image? then set the picture img to a transparent pixel
			if ($this->lqipSeparate) {
				$imgAttr['src'] = $this->pixel64();
			} else {
				// never lazy load inlined image data
				$isInline = substr($imgAttr["src"], 0, 5) === "data:";
				if (!$isInline) {
					$imgAttr['data-src'] = $imgAttr['src'];
					unset($imgAttr['src']);
				}
			}
		}
		$out .= '<img ' . stringify_attributes($imgAttr) . '>' . $this->nl();
		return $out;
	}

	/**
	 * Render the <img> for LQIP when it's a separate element from <picture>
	 */
	protected function renderLqipOwnImg(): string
	{
		if (!$this->lqip || !$this->lqipSeparate) return "";
		return '<img ' . stringify_attributes($this->getLqipAttr(TRUE)) . '>' . $this->nl();
	}

	/**
	 * Render the col-* wrapper div
	 */
	protected function renderColWrapper(): string
	{
		if (!$this->colWrapper) return "";
		// add the string supplied in cols() call
		$wrapperAttr = $this->ensureAttr('class', $this->colClasses, $this->colWrapperAttr);
		$this->wrapCount++;
		return '<div data-dyn_wrapper="col" ' . stringify_attributes($wrapperAttr) . '>' . $this->nl();
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
		$containerRatio = $this->parseRatio($this->ratio);
		$sourceRatio = $this->sourceRatio();
		$out = "";

		$ratioWrapperAttr = $this->getRatioAttr($this->ratioWrapperAttr);
		$ratioWrapperAttr["dyn_wrapper_orient"] = $this->parseRatio($this->ratio) > 1 ? "portrait" : "landscape";
		$ratioWrapperAttr["data-dyn_src_orient"] = $this->getOrientation($this->origWidth, $this->origHeight);

		$fit = "contain";
		$cropAttr = NULL;
		// is the ratio different than the source image? write the cropping div, if ratioCrop is true
		if ($this->ratioCrop && round($containerRatio, 4) !== round($sourceRatio, 4)) {
			$fit = "crop";
			$orientation = $this->getOrientation($this->origWidth, $this->origHeight);
			$cropAttr = $this->getRatioAttr();
			$containerRatio *= 100;
			$sourceRatio *= 100;
			if ($containerRatio === $sourceRatio) {
				$cropAttr["style"] .= ";padding-bottom:$sourceRatio%";
			} else {
				if ($sourceRatio < $containerRatio) {
					$sourceRatio = (100 * 100) / $sourceRatio;
				}
				$ratioSide = ($orientation === 'landscape') ? 'right' : 'bottom';
				$otherSide = ($orientation === 'landscape') ? 'bottom' : 'right';
				$cropAttr["style"] .= ";padding-$otherSide:$containerRatio%;padding-$ratioSide:$sourceRatio%";
			}
		}
		$out .= '<div data-dyn_fit="' . $fit . '"' . stringify_attributes($ratioWrapperAttr) . '>' . $this->nl();
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
		// was everything too big? just show the max width
		if (empty($this->resolutionDict)) {
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
		$this->publicFile ??= $this->file->getBasename("." . $this->file->getExtension());
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
				return round($height / $width, 5);
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
}

<?php

namespace Tomkirsch\Bootstrap;

/**
 * DynamicImage allows you to utilize a dynamic image resizing script to output sizes needed for a Bootstrap layout.
 * Notes:
 * 1) This only works in regular .container elements (NOT .container-fluid)!
 * 2) This does not work in NESTED .container elements
 * 3) A .col container should be the ONLY col in the row. You will need to define sizes for this tool (sm, lg, etc.)
 * 
 * Supported elements: <img> and <picture>. Use <picture> for the most accurate sizing!
 * Example:
 * 
 * service('bootstrap')
 * ->dynamicImage('my-source-file.jpg')
 * ->withSize(1024, 768) // pass the size of the source file to avoid a getimagesize() call
 * ->cols('col-6 col-lg-4')
 * ->hires(2) // support 2x (the 1024px image will be downloaded for a 512px width retina display)
 * ->lazy(TRUE) // support data-src and data-srcset
 * ->element('picture', [], ['alt'=>'A cute kitten', 'class'=>'img-fluid'])
 * ->render();
 * 
 * See tests/app/Views/welcome_message.php for more examples
 */


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

	protected $config;
	protected $nl = "\n";
	protected $mediaDebug;
	protected $prettyPrint;
	protected $mediaCache = [];
	protected $el;
	protected $elAttr;
	protected $imgAttr;
	protected $colClassString; // class attribute for the col wrapper
	protected $colWrapperAttr; // attributes for a wrapper div to be placed around the element
	protected $ratioWrapperAttr; // attributes for a wrapper div to be placed around the element
	protected $src; // source path+filename without ext
	protected $srcExt; // no dot
	protected $dest; // destination path+filename
	protected $destExt; // no dot
	protected $destQuery;
	protected $srcWidth;
	protected $srcHeight;
	protected $lqip; // string source/command
	protected $lqipAttr;
	protected $lpiqIsOwnImg;
	protected $colClasses = [];
	protected $gutterWidth;
	protected $hiresX;
	protected $hiresY;
	protected $resolutionStep;
	protected $isLazy;
	protected $ratio;
	protected $ratioCrop;
	protected $fileList;
	protected $wrapCount = 0;
	protected $alt;

	public function __construct(?BootstrapConfig $config = NULL)
	{
		$this->config = $config ?? new BootstrapConfig();
		$this->prettyPrint = $this->config->prettyPrint ?? FALSE;
		$this->mediaDebug = $this->config->mediaDebug ?? FALSE;
		$this->resetAll();
	}

	/**
	 * Reset everything to config defaults
	 */
	public function resetAll()
	{
		$this->resetFile();
		$this->elAttr = [];
		$this->imgAttr = [];
		$this->lqipAttr = [];
		$this->lpiqIsOwnImg = NULL;
		$this->el = $this->config->defaultElement ?? 'picture';
		$this->isLazy = $this->config->defaultIsLazy ?? FALSE;
		$this->hiresX = $this->config->defaultHiresWidth ?? static::HIRES_SOURCE;
		$this->hiresY = $this->config->defaultHiresHeight ?? static::HIRES_SOURCE;
		$this->resolutionStep = $this->config->defaultResolutionStep ?? 1;
		$this->lqip = $this->config->defaultLqip ?? NULL;
		$this->ratio = $this->config->defaultUseRatio ?? FALSE;
		$this->ratioWrapperAttr = NULL;
		$this->gutterWidth = $this->config->defaultGutterWidth ?? 0;
		$this->colClasses = [];
		return $this;
	}

	/**
	 * Reset the file parameter, but leaves all other settings intact from the last call (lqip, lazy, etc). Useful for bulk actions.
	 */
	public function resetFile()
	{
		// these will almost always be different
		foreach (['src', 'srcExt', 'dest', 'destExt', 'srcWidth', 'srcHeight', 'alt'] as $prop) {
			$this->$prop = NULL;
		}
		$this->fileList = [];
		return $this;
	}

	/**
	 * Sets the debugger to be on or off to easily see what <source>s are generated
	 */
	public function debug(bool $value)
	{
		$this->mediaDebug = $value;
		return $this;
	}

	/**
	 *  Sets the source file to read; $dest can be used to dynamically rename the file; $query can pass a query string to the dest filename string
	 * 
	 * @param string $src Source file
	 * @param string|null $alt The alt attribute for the <img>. If one is set in element(), it will override this value.
	 * @param string|null $dest Destination (public-facing file, possibly rewritten with .htaccess)
	 * @param string|array|null $query GET parameters to append to the destination
	 */
	public function withFile(string $src, ?string $alt = NULL, ?string $dest = NULL, $query = NULL)
	{
		// remove ext from filename(s)
		list($this->src, $this->srcExt) = $this->stripExt($src);
		if ($dest) {
			list($this->dest, $this->destExt) = $this->stripExt($dest);
		} else {
			$this->dest = $this->src;
			$this->destExt = $this->srcExt;
		}
		$this->destQuery = $query;
		$this->alt = $alt;
		return $this;
	}

	/**
	 * If you know the source image size, use this to avoid expensive getimagesize() calls
	 */
	public function withSize(int $width, int $height)
	{
		$this->srcWidth = $width;
		$this->srcHeight = $height;
		return $this;
	}

	/**
	 * Tell the library what col-* classes you'll be using. If $wrapperAttr is set, a wrapper div will be automagically be printed with the passed col classes
	 * @param string|array|null $colClasses The column names (ex "col-md-5 col-lg-2")
	 * @param array|null $wrapperAttr If not NULL, a column wrapper element will automatically be added with the col classes
	 */
	public function cols($colClasses = NULL, ?array $wrapperAttr = NULL, ?int $gutterWidth = NULL)
	{
		// record the original string so we can put it in the wrapper div
		$this->colClassString = $colClasses ?? "";
		if (empty($colClasses)) {
			$this->colClasses = [];
		} else {
			$colClasses = is_string($colClasses) ? explode(' ', $colClasses) : $colClasses;
			foreach ($colClasses as $className) {
				if ($className === "col") $className = "col-xs-12"; // make this explicit for our class
				if (!preg_match('/^col-([a-z]+)/', $className)) continue; // not a col class
				if (!preg_match('/^col-[a-z]+-\d+/', $className)) $className .= "-12"; // ex col-md. Assume the widest.
				$this->colClasses[] = $className;
			}
			$this->colClasses = array_unique($this->colClasses);
			// always sort the classes for the caching mechanism
			asort($this->colClasses);
		}
		$this->colWrapperAttr = $wrapperAttr;
		$this->gutterWidth = $gutterWidth ?? $this->gutterWidth ?? 0;
		return $this;
	}

	/**
	 * Sets the ratio
	 * 
	 * @param bool|string|float $value FALSE will disable ratio padding, TRUE will use the image's original ratio. Strings like "16:9" or "16/9" work as well.
	 * @param bool $crop Crop the image inside the ratio container. If FALSE, the image will scale down and be centered
	 * @param bool|array|null $wrapperAttr Attributes to add to the ratio wrapper html
	 */
	public function ratio($value, bool $ratioCrop = TRUE, $wrapperAttr = NULL)
	{
		$this->ratio = $value;
		$this->ratioCrop = $ratioCrop;
		$this->ratioWrapperAttr = ($wrapperAttr === TRUE) ? [] : $wrapperAttr;
		return $this;
	}

	/**
	 * Enable lazy-load - if true, HTML uses data-src and data-srcset attributes
	 */
	public function lazy(bool $value)
	{
		$this->isLazy = $value;
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
	 * Sets the Low-Quality Image Placeholder
	 * @param string|int|null $src Possible values - string 'xs': the source image at the smallest bootstrap container size, string 'pixel': a transparent pixel, int width: dynamically resized width in pixels (ie. 100px), string hex: a solid color (ie. '#FF0000'), string otherFileName: an alternate file
	 * @param array $attr Attributes to attach to the LQIP element
	 * @param bool $lpiqIsOwnImg This must be true if LQIP is an lazy-loaded <img> element. Positioning CSS is required to lay it on top of the <picture>
	 */
	public function lqip(?string $src = NULL, array $attr = [], bool $lpiqIsOwnImg = FALSE)
	{
		$this->lqip = $src;
		$this->lqipAttr = $attr;
		$this->lpiqIsOwnImg = $lpiqIsOwnImg;
		if ($this->lpiqIsOwnImg) {
			$this->lazy(TRUE);
		}
		return $this;
	}

	/**
	 * Set the element to <picture> (with <source>s and <img>) or just an <img> with srcset attributes
	 * @param string $value Must be either 'picture' or 'img'
	 * @param array $attr HTML attributes to set on the element
	 * @param array $attr HTML attributes to set on the nested <img> element (only valid if the element is a <picture>)
	 */
	// set the desired element (img or picture), with optional attributes
	public function element(string $value, array $elAttr = [], array $imgAttr = [])
	{
		switch ($value) {
			case 'img':
			case 'picture':
				$this->el = $value;
				break;
			default:
				throw new \Exception("Unknown element: $value");
		}
		$this->elAttr = $elAttr;
		$this->imgAttr = $imgAttr;
		return $this;
	}

	/**
	 * Renders the HTML
	 * @param bool $resetAll Set to FALSE when working in loops
	 * @param bool $resetFile Set to FALSE when making multiple calls with the same file
	 */
	public function render(bool $resetAll = TRUE, bool $resetFile = TRUE): string
	{
		if (empty($this->src)) throw new \Exception("You must call DynamicImage::withFile()");
		if (empty($this->el)) $this->el = 'img'; //default to something

		$this->wrapCount = 0;
		$out = $this->nl();

		// do we not have an image size? then we need to call getimagesize()
		$this->ensureSourceDim();

		// set the data-orientation attribute for CSS/JS
		$this->elAttr['data-dyn_src_orient'] = $this->getOrientation();

		// check cache to avoid recalculating everything in loops and such
		$cacheKey = implode('', $this->colClasses);
		$mediaDict = $this->getMediaCache($cacheKey);
		if (!$mediaDict) {
			// generate media and image widths from the column class names
			$mediaDict = $this->mediaDict($this->colDict($this->colClasses), TRUE);
			// write cache
			$this->setMediaCache($cacheKey, $mediaDict);
		}

		// are we using a custom ratio that is larger than our own ratio? then offset the container widths since it will be "zoomed in" behind the crop, and we want the correct-sized image file
		if ($this->ratio && $this->ratio !== TRUE) {
			$sourceRatio = $this->sourceRatio();
			$containerRatio = $this->parseRatio($this->ratio);
			if ($sourceRatio < $containerRatio) {
				foreach ($mediaDict as $media => $width) {
					$mediaDict[$media] = $width * ($containerRatio + $sourceRatio);
				}
			}
		}
		// get resolution dictionary
		$resolutionDict = $this->resolutionDict($mediaDict, $this->srcWidth);

		// make col wrapper
		$out .= $this->renderColWrapper();

		// make ratio wrapper(s)
		$out .= $this->renderRatioWrapper();

		// elements
		$out .= $this->el === 'img' ? $this->renderSrcsetImg($mediaDict, $resolutionDict) : $this->renderPicture($mediaDict, $resolutionDict);

		// close the wrappers
		$out .= str_repeat('</div>' . $this->nl(), $this->wrapCount);

		if ($this->mediaDebug) {
			$out = '';
			foreach ($this->fileList as $index => $file) {
				$out .= $file . '<br>';
			}
		}
		if ($resetAll) {
			$this->resetAll();
		} else if ($resetFile) {
			$this->resetFile();
		}
		return $out;
	}

	protected function renderPicture(array $mediaDict, array $resolutionDict): string
	{
		$out = '';
		$pictureAttr = $this->elAttr;

		// LQIP outside of <picture>.. we place it first, so it's behind the picture
		$out .= $this->renderLqipOwnImg($mediaDict);

		// picture
		$out .= '<picture' . stringify_attributes($pictureAttr) . '>' . $this->nl();
		foreach ($resolutionDict as $mediaWidth => $data) {
			$sourceAttr = [];
			$sources = [];
			$sourceAttr['media'] = '(min-width:' . $mediaWidth . 'px)';
			foreach ($data as $factor => $width) {
				// are we not supporting hi res devices? then skip
				if (floatval($factor) > 1 && empty($this->hiresX)) continue;
				$src = $this->destFileName($width, $this->getResolutionMedia($factor, $sourceAttr['media']));
				// use a key here, so we don't get a bloated thing like "foo-800.jpg 4x, foo-800.jpg 3x, foo-800.jpg 2x"
				$key = $src;
				if (floatval($factor) > 1) $src .= ' ' . $factor . 'x';
				$sources[$key] = $src;
			}
			if ($this->destExt === 'webp' || $this->destExt === 'jp2') {
				$sourceAttr['type'] = 'image/' . $this->destExt;
			}
			$attrName = $this->isLazy ? 'data-srcset' : 'srcset';
			$sourceAttr[$attrName] = $this->nl() . implode(', ' . $this->nl(), array_values($sources));
			$out .= '<source' . stringify_attributes($sourceAttr) . '>' . $this->nl();
		}
		// write the <img>
		$out .= $this->renderPictureImg($mediaDict);
		$out .= '</picture>' . $this->nl();
		return $out;
	}

	protected function renderLqipOwnImg(array $mediaDict): string
	{
		if (!$this->lpiqIsOwnImg) return "";
		return '<img ' . stringify_attributes($this->getLqipAttr($mediaDict, TRUE)) . '>' . $this->nl();
	}

	// render the <img> element(s) for a <picture>
	protected function renderPictureImg(array $mediaDict): string
	{
		$out = '';
		$imgAttr = array_merge($this->getLqipAttr($mediaDict, FALSE), $this->imgAttr);
		$imgAttr["alt"] ??= $this->alt ?? "";

		// lazyload
		if ($this->isLazy) {
			$imgAttr = $this->ensureAttr('class', 'lazyload', $imgAttr);
			// is LQIP it's own image? then set the picture img to a transparent pixel
			if ($this->lpiqIsOwnImg) {
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

	// render <img> element(s) using srcset
	protected function renderSrcsetImg(array $mediaDict, array $resolutionDict): string
	{
		$out = '';
		$sources = [];
		$sizes = [];

		// since we can't arrage using picture, we must only specify one file for each resolution. make it the biggest it'll possibly be to preven upscaling
		$factorWidths = [];
		foreach ($resolutionDict as $screenSize => $data) {
			foreach ($data as $factor => $width) {
				$factorWidths[$factor] = max($factorWidths[$factor] ?? 0, $width);
			}
		}

		$minWidth = min($factorWidths);
		foreach ($factorWidths as $factor => $width) {
			$mediaQuery = '(min-width:' . $width . 'px)';
			$file = $this->destFileName($width, ($width === $minWidth) ? NULL : $mediaQuery);
			if (empty($this->hiresX)) {
				// foo-800.jpg 800w, foo-400.jpg 400w, foo-100.jpg
				$src = $file . ' ' . $width . 'w';
				// (min-width: 860px) 800px
				$size = ($width === $minWidth) ? '' : $mediaQuery . ' ';
				$size .= $width . 'px';
				$sizes[] = $size;
			} else {
				// foo-800.jpg 2x, foo-400.jpg
				$file = $this->destFileName($width, $this->getResolutionMedia($factor));
				$src = $file;
				if (floatval($factor) > 1) $src .= ' ' . $factor . 'x';
			}
			$sources[$file] = $src; // use a key here, so we don't get a bloated thing like "foo-800.jpg 4x, foo-800.jpg 3x, foo-800.jpg 2x"
		}

		$imgAttr = array_merge($this->elAttr, $this->imgAttr);
		$imgAttr["alt"] = $this->elAttr["alt"] ?? $this->alt ?? "";

		if ($this->lpiqIsOwnImg) {
			$out .= '<img ' . stringify_attributes($this->getLqipAttr($mediaDict, TRUE)) . '>' . $this->nl();
		} else {
			$imgAttr = array_merge($this->getLqipAttr($mediaDict, FALSE), $imgAttr);
		}
		// lazyload class
		if ($this->isLazy) {
			$imgAttr = $this->ensureAttr('class', 'lazyload', $imgAttr);
		}
		// sizes
		if (!empty($sizes)) {
			$imgAttr = $this->ensureAttr('sizes', implode(',', $sizes), $imgAttr);
		}
		// srcset or data-srcset
		$attrName = $this->isLazy ? 'data-srcset' : 'srcset';
		$imgAttr[$attrName] = $this->nl() . implode(', ' . $this->nl(), array_values($sources));
		$out .= '<img ' . stringify_attributes($imgAttr) . '>' . $this->nl();
		return $out;
	}

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
		$fit = "contain";
		$cropAttr = NULL;
		// is the ratio different than the source image? write the cropping div, if ratioCrop is true
		if ($this->ratioCrop && round($containerRatio, 4) !== round($sourceRatio, 4)) {
			$fit = "crop";
			$orientation = $this->getOrientation();
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
			$out .= '<div data-dyn_crop="' . $this->srcWidth . '/' . $this->srcHeight . '" ' . stringify_attributes($cropAttr) . '>' . $this->nl();
			$this->wrapCount++;
		}

		return $out;
	}

	protected function renderColWrapper(): string
	{
		$wrapperAttr = $this->colWrapperAttr;
		if ($wrapperAttr === NULL) return "";
		// add the string supplied in cols() call
		$wrapperAttr = $this->ensureAttr('class', $this->colClassString, $wrapperAttr);
		$this->wrapCount++;
		return '<div data-dyn_wrapper="col" ' . stringify_attributes($wrapperAttr) . '>' . $this->nl();
	}

	/**
	 *  Set the src and alt attributes of LQIP <img>
	 */
	protected function getLqipAttr(array $mediaDict, bool $isOwnImg): array
	{
		$attr = $isOwnImg ? [] : $this->lqipAttr;
		$attr["data-dyn_lqip"] = $isOwnImg ? "separate" : "integrated";
		switch ($this->lqip) {
			case static::LQIP_XS:
				// use xs container width
				// if we didn't see a given width for xs (ie. col-6), then use the smallest in bootstrap containers
				$width = floor($mediaDict[0] ?? min($this->config->containers()));
				$attr['src'] = $this->destFileName($width);
				break;
			case static::LQIP_PIXEL:
				// transparent pixel base64
				$attr['src'] = $this->pixel64();
				break;
			default:
				if (is_string($this->lqip) && substr($this->lqip, 0, 1) === '#') {
					// hex color
					$attr['src'] = $this->svgRect64($this->lqip);
				} else if (is_numeric($this->lqip)) {
					// it's a specific width
					$attr['src'] = $this->destFileName(intval($this->lqip));
				} else if (!empty($this->lqip)) {
					// something custom, I guess
					$attr['src'] = $this->lqip;
				} else {
					// empty/null. use the first image as presribed by mediaDict
					$width = floor($mediaDict[0] ?? min($this->config->containers()));
					$attr['src'] = $this->destFileName($width);
				}
		} // endswitch
		return $attr;
	}

	/*
		Take an array of column classes (col-*) and make keys from the column number.
		input: ['col-10', 'col-md-6', 'col-xl-2'] output: [10=>'xs' 6=>'md', 2=>'lg']
	*/
	protected function colDict(array $colClasses): array
	{
		$colDict = [];
		foreach ($colClasses as $colClass) {
			$matches = [];
			if (!preg_match('/^col-([a-z]+)-(\d+)/', $colClass, $matches)) continue;
			$colDict[intval($matches[2])] = $matches[1];
		}
		// sort the dictionary by $colSize, from smallest bootstrap size to largest, so bigger cols overwrite mediaWidth keys generated by smaller ones
		uasort($colDict, function (string $a, string $b) {
			$aVal = ($a === 'xs') ? 0 : $this->config->container($a);
			$bVal = ($b === 'xs') ? 0 : $this->config->container($b);
			return ($aVal <=> $bVal);
		});
		return $colDict;
	}

	/*
		Take a column dictionary from colDict() and make array with media-width keys and max widths for the images inside them.
		Use $forceZeroMediaSize when your regular <img> element is a LQIP
		input: [10=>'xs' 6=>'md', 2=>'lg'] output: [1200=>190, 992=>480, 768=>360, 0=>450]
	*/
	protected function mediaDict(array $colDict, ?bool $forceZeroMediaSize = FALSE): array
	{
		$imgMediaDict = [];
		// are there no specified cols? then assume it's full container width
		if (empty($colDict)) {
			// no columns specified, so we just loop the containers
			$imgMediaDict[0] = min($this->config->containers()); // handle xs size, since it's not defined in containers
			// pull media width from breakpoints, and image widths from containers
			foreach ($this->config->containers() as $containerSize => $containerWidth) {
				$mediaWidth = $this->config->breakpoint($containerSize);
				$imgMediaDict[$mediaWidth] = $this->config->container($containerSize) - ($this->gutterWidth * 2);
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
					$imgMediaDict[$mediaWidth] = $imageWidth;
				}
			}
		} //endif
		if ($forceZeroMediaSize && !isset($imgMediaDict[0])) {
			$minContainer = min($this->config->containers());
			// if we have a col class without a size (ie col-6), we need to divide the container width
			if (in_array('xs', $colDict)) {
				$colNum = array_search('xs', $colDict);
				$fraction = $colNum / $this->config->gridCols;
				$imgMediaDict[0] = ceil($minContainer * $fraction) - ($this->gutterWidth * 2);
			} else {
				$imgMediaDict[0] = $minContainer - ($this->gutterWidth * 2);
			}
		}
		// put our biggest media queries first
		krsort($imgMediaDict);
		return $imgMediaDict;
	}

	/*
		Takes an array from imgMediaDict() and creates an array that can be used
		to create 'srcset' and/or 'sizes' sttributes for <img> or <src>.
		Input: [1200=>190, 992=>480, 768=>360, 0=>450] Output: 
		[
			// media	=> [factor=>imageWidth, ...]
			1200 		=> ['4'=>760, '3'=>570, '2'=>380, '1'=>190],
			992			=> ['4'=>1920, '3'=>1440, '2'=>960, '1'=>480],
			0			=> ['4'=>1800, '3'=>1350, '2'=>900, '1'=>450]
		]
	*/

	protected function resolutionDict(array $imgMediaDict, int $srcMaxWidth): array
	{
		$maxResolution = $this->config->defaultMaxResolution ?? 1;
		// figure out our max width
		if (is_numeric($this->hiresX) && (float) $this->hiresX <= 10) {
			// we were given a resolution, ensure maxWidth doesn't exceed orig image
			$maxResolution = (float) $this->hiresX;
			$maxWidth = min($this->srcWidth, $this->srcWidth * $maxResolution);
		} else if (is_numeric($this->hiresX)) {
			// we were given a hard px value
			$maxWidth = min($this->srcWidth, (int) $this->hiresX);
		} else {
			$maxWidth = $this->srcWidth;
		}
		if ($maxResolution < 1) throw new \Exception("Invalid max resolution: $maxResolution");

		$maxHeight = $this->hiresY === "source" ? $this->srcHeight : $this->hiresY;
		if ($maxHeight !== NULL && !is_int($maxHeight)) throw new \Exception("Invalid hires height: $maxHeight");

		$resolutionDict = [];
		foreach ($imgMediaDict as $mediaWidth => $imageWidth) {
			// loop through possible resolutions, highest first
			for ($i = $maxResolution; $i >= 1; $i -= $this->resolutionStep) {
				// calculate the final dimensions at this resolution, but limiting the width (and height if needed)
				list($hiresWidth, $hiresHeight) = $this->reproportion(floor($imageWidth * $i), $maxHeight ?? 0);
				if ($hiresWidth > $maxWidth) continue;
				if (!isset($resolutionDict[$mediaWidth])) $resolutionDict[$mediaWidth] = [];
				$resolutionDict[$mediaWidth][(string) $i] = $hiresWidth;
			} // endfor
		}
		return $resolutionDict;
	}

	/**
	 * Returns "portrait" or "lanscape". Square images are landscape.
	 */
	public function getOrientation(): string
	{
		$this->ensureSourceDim();
		return $this->orientation($this->srcWidth, $this->srcHeight);
	}

	protected function orientation(int $width, int $height): string
	{
		return $height > $width ? "portrait" : "landscape";
	}

	protected function getMediaCache(string $key)
	{
		return $this->mediaCache[$key] ?? NULL;
	}

	protected function setMediaCache(string $key, array $value)
	{
		$this->mediaCache[$key] = $value;
	}

	protected function getRatioAttr(array $attr = []): array
	{
		if ($this->ratio === TRUE) {
			// use source image ratio
			$style = "--aspect-ratio:$this->srcWidth/$this->srcHeight";
		} else {
			// 16/9 or 16:9 or 1.775
			$style = "--aspect-ratio:$this->ratio";
		}
		return $this->ensureAttr('style', $style, $attr);
	}

	public function sourceRatio(): float
	{
		$this->ensureSourceDim();
		return round($this->srcHeight / $this->srcWidth, 5);
	}

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
	protected function ensureAttr(string $attrName, string $attrValue, $attr = NULL): array
	{
		if (empty($attr)) {
			$attr = [];
		} else if (!is_array($attr)) {
			throw new \Exception("Attributes must be passed as an associative array");
		}
		$sep = $attrName === 'style' ? ';' : ($attrName === 'class' ? ' ' : '');
		if (isset($attr[$attrName])) {
			$attr[$attrName] .= $sep . $attrValue;
		} else {
			$attr[$attrName] = $attrValue;
		}
		return $attr;
	}

	protected function destFileName(int $width, ?string $media = NULL): string
	{
		$file = $this->config->dynamicImageFileName($this->dest, $this->destExt, $width);
		// add to our file list, so we can output filename instead of the elements
		if ($this->mediaDebug) {
			$id = 'bs-' . md5($media . $file . uniqid()); // prevent ids from starting with a number
			$parts = explode('/', $file);
			$f = array_pop($parts);
			$style = $media ? '<style type="text/css">@media ' . $media . '{#' . $id . '{font-weight:bold;}}</style>' : '<style type="text/css">#' . $id . '{font-weight:bold;}</style>';
			$this->fileList[] = $style . '<span id="' . $id . '">' . $f . '</span>';
		}

		// query string
		$q = '';
		if (!empty($this->destQuery)) {
			if (is_string($this->destQuery)) {
				$q = strpos($this->destQuery, '?') === FALSE ? '?' : '';
				$q .= $this->destQuery;
			} else if (is_array($this->destQuery)) {
				$q = '?' . http_build_query($this->destQuery);
			}
		}
		return $file . $q;
	}

	protected function ensureSourceDim()
	{
		if (!$this->srcWidth || !$this->srcHeight) {
			$size = getimagesize($this->src . '.' . $this->srcExt);
			if (!$size) throw new \Exception("Cannot read image size for $this->src.$this->srcExt");
			list($this->srcWidth, $this->srcHeight) = $size;
		}
	}

	protected function stripExt(string $src): array
	{
		$parts = explode('.', $src);
		$ext = array_pop($parts);
		return [implode('.', $parts), $ext];
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
	public function svgRect64(string $color): string
	{
		$svg = '<svg preserveAspectRatio="none" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="' . $color . '" /></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode($svg);
	}

	// for debug only. but yuck!!
	protected function getResolutionMedia($factor, ?string $otherMedia = NULL): string
	{
		if ($factor > 1) {
			$parts = [];
			$dpi = $factor * 96;
			$parts[] = "-webkit-min-device-pixel-ratio: $factor";
			$parts[] = "min--moz-device-pixel-ratio: $factor";
			$parts[] = "-o-min-device-pixel-ratio: $factor/1";
			$parts[] = "min-device-pixel-ratio: $factor";
			$parts[] = "min-resolution: $dpi" . 'dpi';
			$parts[] = "min-resolution: $factor" . 'dppx';
			$otherMedia = $otherMedia ? ' and ' . $otherMedia : '';
			$out = '(' . implode(')' . $otherMedia . ', (', $parts) . ')' . $otherMedia;
		} else {
			$out = $otherMedia ?? '';
		}
		return $out;
	}

	protected function nl(): string
	{
		return $this->prettyPrint ? $this->nl : '';
	}
	protected function tab(): string
	{
		return $this->prettyPrint ? '	' : '';
	}

	protected function reproportion(int $width, int $height = 0, string $masterDim = 'auto'): array
	{
		if ($masterDim !== 'width' && $masterDim !== 'height') {
			if ($width > 0 && $height > 0) {
				$masterDim = ((($this->srcHeight / $this->srcWidth) - ($height / $width)) < 0) ? 'width' : 'height';
			} else {
				$masterDim = ($height === 0) ? 'width' : 'height';
			}
		} elseif (($masterDim === 'width' && $width === 0) || ($masterDim === 'height' && $height === 0)
		) {
			throw new \Exception("Invalid sizes passed");
		}

		if ($masterDim === 'width') {
			$height = (int) floor($width * $this->srcHeight / $this->srcWidth);
		} else {
			$width = (int) floor($this->srcWidth * $height / $this->srcHeight);
		}
		return [$width, $height];
	}
}

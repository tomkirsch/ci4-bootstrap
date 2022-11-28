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
	protected $wrapperAttr; // attributes for a wrapper div to be placed around the element
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
	protected $colClasses;
	protected $hires;
	protected $resolutionStep;
	protected $isLazy;
	protected $ratio;
	protected $ratioPaddingClass;
	protected $ratioCropClass;
	protected $ratioWrapper;
	protected $fileList;

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
		$this->hires = $this->config->defaultHires ?? static::HIRES_SOURCE;
		$this->resolutionStep = $this->config->defaultResolutionStep ?? 1;
		$this->lqip = $this->config->defaultLqip ?? NULL;
		$this->ratioPaddingClass = $this->config->defaultRatioPaddingClass ?? NULL;
		$this->ratioCropClass = $this->config->defaultRatioCropClass ?? NULL;
		$this->ratio = $this->config->defaultUseRatio ?? FALSE;
		$this->ratioWrapper = NULL;
		return $this;
	}

	/**
	 * Reset the file parameter, but leaves all other settings intact from the last call (lqip, lazy, etc). Useful for bulk actions.
	 */
	public function resetFile()
	{
		// these will almost always be different
		foreach (['src', 'srcExt', 'dest', 'destExt', 'srcWidth', 'srcHeight',] as $prop) {
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
	 * @param string|null $dest Destination (public-facing file, possibly rewritten with .htaccess)
	 * @param string|array|null $query GET parameters to append to the destination
	 */
	public function withFile(string $src, ?string $dest = NULL, $query = NULL)
	{
		// remove ext from filename(s)
		$parts = explode('.', $src);
		list($this->src, $this->srcExt) = $this->stripExt($src);
		if ($dest) {
			list($this->dest, $this->destExt) = $this->stripExt($dest);
		} else {
			$this->dest = $this->src;
			$this->destExt = $this->srcExt;
		}
		$this->destQuery = $query;
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
	 * @param string|array|null $cols The column names (ex "col-md-5 col-lg-2")
	 * @param array|null $wrapperAttr Attributes to add to the wrapper
	 */
	public function cols($cols = NULL, ?array $wrapperAttr = NULL)
	{
		$this->colClasses = ['col-12'];
		if (!empty($cols)) {
			$this->colClasses = is_string($cols) ? explode(' ', $cols) : $cols;
		}
		// always sort the classes for the caching mechanism
		asort($this->colClasses);
		$this->colClasses = array_unique($this->colClasses);
		$this->wrapperAttr = $wrapperAttr;
		return $this;
	}

	/**
	 * Sets the padding-bottom on the <picture>
	 * 
	 * @param bool|float $value FALSE will disable ratio padding, TRUE will use the image's original ratio. You can pass a float too, like (9/16) for 16:9
	 * @param bool|array|null $wrapperAttr Attributes to add to the ratio wrapper html
	 * @param string|null $className Useful for when you have common ratio padding classes, like .square or .sixteenbynine
	 */
	public function ratio($value, $wrapperAttr = NULL, ?string $className = NULL)
	{
		$this->ratio = $value;
		$this->ratioWrapper = ($wrapperAttr === TRUE) ? [] : $wrapperAttr;
		if ($className) $this->ratioPaddingClass = $className;
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
	 * @param int|string|null $value Pass string 'source' to match the source image width, a scale factor (2-10), or pixel width to limit how large we want to display the image
	 * @param float|null $resolutionStep Determines how many steps we want to offer. For example, hires(2, 0.5) will generate 2x and 1.5x versions
	 */
	public function hires($value, float $resolutionStep = 1)
	{
		$this->hires = is_numeric($value) ? floatval($value) : $value;
		$this->resolutionStep = $resolutionStep;
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
	public function element(string $value, array $attr = [], array $imgAttr = [])
	{
		switch ($value) {
			case 'img':
			case 'picture':
				$this->el = $value;
				break;
			default:
				throw new \Exception("Unknown element: $value");
		}
		$this->elAttr = $attr;
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
		if (empty($this->colClasses)) throw new \Exception("You must call DynamicImage::cols()");
		if (empty($this->src)) throw new \Exception("You must call DynamicImage::withFile()");
		if (empty($this->el)) $this->el = 'img'; //default to something
		$out = $this->nl();

		// do we not have an image size? then we need to call getimagesize()
		if (!$this->srcWidth || !$this->srcHeight) {
			$size = getimagesize($this->src . '.' . $this->srcExt);
			if (!$size) throw new \Exception("Cannot read image size for $this->src.$this->srcExt");
			list($this->srcWidth, $this->srcHeight) = $size;
		}

		// set the data-orientation attribute for CSS/JS
		$this->elAttr['data-orientation'] = $this->getOrientation();

		// check cache to avoid recalculating everything in loops and such
		$cacheKey = implode('', $this->colClasses);
		$mediaDict = $this->getMediaCache($cacheKey);
		if (!$mediaDict) {
			// generate media and image widths from the column class names
			$mediaDict = $this->mediaDict($this->colDict($this->colClasses), TRUE);
			// write cache
			$this->setMediaCache($cacheKey, $mediaDict);
		}
		// are we using a custom ratio that is larger than our own ratio? then offset the container widths since it will be "zoomed in" behind the crop
		$myRatio = $this->getRatio();
		if ($this->ratio && $this->ratio !== TRUE && $myRatio < $this->ratio) {
			foreach ($mediaDict as $media => $width) {
				$mediaDict[$media] = $width * ($this->ratio + $myRatio);
			}
		}
		// get resolution dictionary
		$resolutionDict = $this->resolutionDict($mediaDict, $this->srcWidth);

		// make wrappers?
		$wrapCount = 0;
		$wrapperAttr = $this->wrapperAttr;
		if ($wrapperAttr !== NULL) {
			// add col classes to the wrapper
			$colString = implode(' ', $this->colClasses);
			$wrapperAttr = $this->ensureAttr('class', $colString, $wrapperAttr);
			// if a ratio wrapper wasn't specified, apply the ratio to this wrapper element
			if ($this->ratio && $this->ratioWrapper === NULL) {
				$wrapperAttr = $this->setRatio($wrapperAttr);
			}
			$out .= '<div' . stringify_attributes($wrapperAttr) . '>' . $this->nl();
			$wrapCount++;
		}
		// if it's an <img> with ratio padding, assume we need the ratioWrapper
		if ($this->el === 'img' && $this->ratio) $this->ratioWrapper = [];
		// write the ratio wrapper
		if ($this->ratio && $this->ratioWrapper !== NULL) {
			// is it a custom ratio? then we'll need to wrap it with a div with THAT padding to crop it
			if ($this->ratio !== TRUE) {
				$pad = $this->ratio * 100;
				$ratioAttr = $this->ensureAttr('style', "padding-bottom:$pad%;", []);
				if ($this->ratioCropClass) {
					$ratioAttr = $this->ensureAttr('class', $this->ratioCropClass, $ratioAttr);
				}
				$out .= '<div' . stringify_attributes($ratioAttr) . '>' . $this->nl();
				$wrapCount++;
			}
			$attr = $this->setRatio($this->ratioWrapper);
			$out .= '<div' . stringify_attributes($attr) . '>' . $this->nl();
			$wrapCount++;
		}

		// start writing the actual elements
		if ($this->el === 'img') {
			// <img> with srcset
			$out .= $this->renderSrcsetImg($mediaDict, $resolutionDict);
		} else {
			// <picture> with <source>s
			$out .= $this->renderPicture($mediaDict, $resolutionDict);
		}

		// close the wrappers
		$out .= str_repeat('</div>' . $this->nl(), $wrapCount);

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
		// if we don't have a wrapper and the user wants the raio padding, use it here
		if ($this->ratio && $this->ratioWrapper === NULL) {
			$pictureAttr = $this->setRatio($pictureAttr);
		}
		// LQIP outside of <picture>
		if ($this->lpiqIsOwnImg) {
			$out .= '<img ' . stringify_attributes($this->getLqipAttr($mediaDict)) . '>' . $this->nl();
		}
		$out .= '<picture' . stringify_attributes($pictureAttr) . '>' . $this->nl();
		foreach ($resolutionDict as $mediaWidth => $data) {
			$sourceAttr = [];
			$sources = [];
			$sourceAttr['media'] = '(min-width:' . $mediaWidth . 'px)';
			foreach ($data as $factor => $width) {
				// are we not supporting hi res devices? then skip
				if (floatval($factor) > 1 && empty($this->hires)) continue;
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

	// render the <img> element(s) for a <picture>
	protected function renderPictureImg(array $mediaDict): string
	{
		$out = '';
		$imgAttr = $this->imgAttr;
		$lqipAttr = $this->getLqipAttr($mediaDict); // set the 'src'
		$imgAttr = array_merge($lqipAttr, $imgAttr);

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
			if (empty($this->hires)) {
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

		$imgAttr = $this->elAttr;
		$lqipAttr = $this->getLqipAttr($mediaDict); // set the 'src'
		if ($this->lpiqIsOwnImg) {
			$out .= '<img ' . stringify_attributes($lqipAttr) . '>' . $this->nl();
			$imgAttr = $this->ensureAttr('alt', '', $imgAttr);
		} else {
			$imgAttr = array_merge($lqipAttr, $imgAttr);
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

	// set the src attribute of LQIP. This could also be the base <img> element
	protected function getLqipAttr(array $mediaDict): array
	{
		$attr = $this->lqipAttr;
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
		// ensure we have 'alt'
		$attr = $this->ensureAttr('alt', '', $attr);
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
			$parts = explode('-', $colClass);
			if (count($parts) < 2) continue;
			$colSize = count($parts) === 3 ? $parts[1] : 'xs';
			$colNum = count($parts) === 3 ? $parts[2] : $parts[1];
			$colDict[$colNum] = $colSize;
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
				$imgMediaDict[$mediaWidth] = $this->config->container($containerSize);
			}
		} else {
			// col-* classes were indicated, so we must calculate the image widths
			foreach ($this->config->containers() as $containerSize => $containerWidth) {
				foreach ($colDict as $colNum => $colSize) {
					$fraction = $colNum / $this->config->gridCols; // divide by 12, or however many cols there are
					if ($colSize === 'xs' || $this->config->container($colSize) <= $this->config->container($containerSize)) {
						// this col is smaller or equal to the container we're looking at. process it.
						$imageWidth = ceil($containerWidth * $fraction);
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
				$imgMediaDict[0] = ceil($minContainer * $fraction);
			} else {
				$imgMediaDict[0] = $minContainer;
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
		// figure out our max width and max resolution
		// $maxSize value can be a resolution (1-10) or a pixel width (>10), or NULL (image source width)
		$maxSize = ($this->hires === static::HIRES_SOURCE) ? $this->srcWidth : $this->hires;
		$maxPx = PHP_INT_MAX; // used for min() operations
		$maxResolution = $this->config->defaultMaxResolution ?? 1;
		if ($maxSize !== NULL && $maxSize <= 10) {
			// assume its a resolution factor, not pixel width
			$maxResolution = $maxSize;
		} else if ($maxSize !== NULL && $maxSize > 10) {
			// we were given a max pixel width to NEVER go over
			$maxPx = $maxSize;
		}
		if ($maxResolution < 1) throw new \Exception("Invalid max resolution: $maxResolution");
		$resolutionDict = [];
		foreach ($imgMediaDict as $mediaWidth => $imageWidth) {
			// loop through possible resolutions, highest first
			for ($i = $maxResolution; $i >= 1; $i -= $this->resolutionStep) {
				$hiresWidth = floor(min($maxPx, $imageWidth * $i)); // never go over a specified max width
				if ($hiresWidth > $srcMaxWidth) {
					// too big... but see if our original is bigger than the "normal" resolution. At least we can sample up.
					if ($srcMaxWidth > $imageWidth) {
						$w = min($maxPx, $srcMaxWidth); // never go over a specified max width
						if (!isset($resolutionDict[$mediaWidth])) $resolutionDict[$mediaWidth] = [];
						$resolutionDict[$mediaWidth][(string) $i] = $w;
					} else {
						// nada... don't add to srcset
					}
				} else {
					// the high res width is acceptable to use
					if (!isset($resolutionDict[$mediaWidth])) $resolutionDict[$mediaWidth] = [];
					$resolutionDict[$mediaWidth][(string) $i] = $hiresWidth;
				}
			} // endfor
		}
		return $resolutionDict;
	}

	// squre/portrait/landscape
	public function getOrientation(): string
	{
		if (empty($this->srcWidth) || empty($this->srcHeight)) throw new \Exception("Cannot get orientation of source file");
		$orientation = 'square';
		if ($this->srcWidth > $this->srcHeight) {
			$orientation = 'landscape';
		} else if ($this->srcWidth < $this->srcHeight) {
			$orientation = 'portrait';
		}
		return $orientation;
	}

	protected function getMediaCache(string $key)
	{
		return $this->mediaCache[$key] ?? NULL;
	}

	protected function setMediaCache(string $key, array $value)
	{
		$this->mediaCache[$key] = $value;
	}

	protected function setRatio(array $attr = []): array
	{
		$attr = $this->ensureAttr('class', $this->ratioPaddingClass, $attr);

		$orientation = $this->getOrientation();
		$myRatio = $this->getRatio();
		$containerRatio = ($this->ratio === TRUE) ? round($this->srcHeight / $this->srcWidth, 5) : $this->ratio;
		$myRatio *= 100;
		$containerRatio *= 100;
		if ($containerRatio === $myRatio) {
			$style = "padding-bottom:$myRatio%;";
		} else {
			if ($myRatio < $containerRatio) {
				$myRatio = (100 * 100) / $myRatio;
			}
			$ratioSide = ($orientation === 'landscape') ? 'right' : 'bottom';
			$otherSide = ($orientation === 'landscape') ? 'bottom' : 'right';
			$style = "padding-$otherSide:$containerRatio%;padding-$ratioSide:$myRatio%;";
		}
		return $this->ensureAttr('style', $style, $attr);
	}

	protected function getRatio()
	{
		return round($this->srcHeight / $this->srcWidth, 5);
	}

	// ensure attributes are an array with the given class(es)
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
}

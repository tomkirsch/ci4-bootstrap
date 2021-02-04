<?php namespace Tomkirsch\Bootstrap;

class DynamicImage{
	const LQIP_XS = 'xs';
	const LQIP_PIXEL = 'pixel';
	
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
	protected $isLazy;
	protected $ratioPaddingClass;
	protected $ratioWrapper;
	protected $fileList;
	
	public function __construct(?BootstrapConfig $config=NULL){
		$this->config = $config ?? new BootstrapConfig();
		$this->prettyPrint = $this->config->prettyPrint ?? FALSE;
		$this->mediaDebug = $this->config->mediaDebug ?? FALSE;
		$this->resetAll();
	}
	
	// resets the file parameters, but leaves all other settings intact from the last call (lqip, lazy, etc)
	public function resetFile(){
		// these will almost always be different
		foreach(['src', 'srcExt', 'dest', 'destExt', 'srcWidth', 'srcHeight',] as $prop){
			$this->$prop = NULL;
		}
		$this->fileList = [];
		return $this;
	}
	
	// reset everything back to config defaults
	public function resetAll(){
		$this->resetFile();
		$this->elAttr = [];
		$this->imgAttr = [];
		$this->lqipAttr = [];
		$this->lpiqIsOwnImg = NULL;
		$this->el = $this->config->defaultElement ?? 'picture';
		$this->isLazy = $this->config->defaultIsLazy ?? FALSE;
		$this->hires = $this->config->defaultHires ?? static::HIRES_SOURCE;
		$this->lqip = $this->config->defaultLqip ?? NULL;
		$this->ratioPaddingClass = $this->config->defaultRatioPaddingClass ?? NULL;
		$this->ratioWrapper = FALSE;
		return $this;
	}
	
	public function debug(bool $value){
		$this->mediaDebug = $value;
		return $this;
	}
	
	// sets the source file to read. $dest can be used to dynamically rename the file. $query can pass a query string to the dest filename string
	public function withFile(string $src, ?string $dest=NULL, $query=NULL){
		// remove ext from filename(s)
		$parts = explode('.', $src);
		list($this->src, $this->srcExt) = $this->stripExt($src);
		if($dest){
			list($this->dest, $this->destExt) = $this->stripExt($dest);
		}else{
			$this->dest = $this->src;
			$this->destExt = $this->srcExt;
		}
		$this->destQuery = $query;
		return $this;
	}
	
	// use this to avoid expensive getimagesize() calls
	public function withSize(int $width, int $height){
		$this->srcWidth = $width;
		$this->srcHeight = $height;
		return $this;
	}
	
	// supply col-* classes so we can figure out the container widths. If $wrapperAttr is set, a wrapper div will be created with the col classes
	public function cols($cols=NULL, ?array $wrapperAttr=NULL){
		$this->colClasses = ['col-12'];
		if(!empty($cols)){
			$this->colClasses = is_string($cols) ? explode(' ', $cols) : $cols;
		}
		// always sort the classes for the caching mechanism
		asort($this->colClasses);
		$this->wrapperAttr = $wrapperAttr;
		return $this;
	}
	
	// sets ratio padding class, or removes the functionality
	public function ratio(?string $className, bool $makeWrapper=FALSE){
		$this->ratioPaddingClass = $className;
		$this->ratioWrapper = $makeWrapper;
		return $this;
	}
	
	// use data-src and data-srcset
	public function lazy(bool $value){
		$this->isLazy = $value;
		return $this;
	}
	
	// supply a maximum factor (3 for 3x), a pixel width (800), 'source' to match source width, or a falsy value to disable high resolution support
	public function hires($value){
		$this->hires = is_numeric($value) ? floatval($value) : $value;
		return $this;
	}
	
	/* 
		Sets the low quality image placeholder. $src can be:
			'xs' 		- the smallest bootstrap container size (default)
			'pixel'		- a transparent pixel
			int width	- dynamically resized width in pixels (ie. 100px)
			string hex 	- a solid color (ie. '#FF0000')
			string otherFileName - an alternate file
		Setting $this->lpiqIsOwnImg to TRUE assumes lazyload.
	*/
	public function lqip(?string $src=NULL, array $attr=[], bool $lpiqIsOwnImg=FALSE){
		$this->lqip = $src;
		$this->lqipAttr = $attr;
		$this->lpiqIsOwnImg = $lpiqIsOwnImg;
		if($this->lpiqIsOwnImg) $this->lazy(TRUE);
		return $this;
	}
	
	// set the desired element (img or picture), with optional attributes
	public function element(string $value, array $attr=[], array $imgAttr=[]){
		switch($value){
			case 'img':
			case 'picture':
				$this->el = $value;
				break;
			default:
				throw new \Exception("Unknown element: $el");
		}
		$this->elAttr = $attr;
		$this->imgAttr = $imgAttr;
		return $this;
	}
	
	// set $resetAll to FALSE when working in loops. $resetFile can be set to FALSE when resizing the same file
	public function render(bool $resetAll=TRUE, bool $resetFile=TRUE):string{
		if(empty($this->colClasses)) throw new \Exception("You must call Bootstrap::cols()");
		if(empty($this->src)) throw new \Exception("You must call Bootstrap::withFile()");
		if($this->el === 'picture' && $this->lpiqIsOwnImg) throw new \Exception("The element must be 'img' if LQIP is it's own element (not a 'picture')");
		if(empty($this->el)) $this->el = 'img'; //default to something
		$out = $this->nl();
		
		// do we not have an image size? then we need to call getimagesize()
		if(!$this->srcWidth || !$this->srcHeight){
			$size = getimagesize($this->src.'.'.$this->srcExt);
			if(!$size) throw new \Exception("Cannot read image size for $this->src.$this->srcExt");
			list($this->srcWidth, $this->srcHeight) = $size;
		}
		
		// calculate padding for wrapper/picture
		$padding = round(($this->srcHeight / $this->srcWidth) * 100, 3);
		
		// set the data-orientation attribute for CSS/JS
		$this->elAttr['data-orientation'] = $this->getOrientation();
		
		// check cache to avoid recalculating everything in loops and such
		$cacheKey = implode('', $this->colClasses);
		$mediaDict = $this->getMediaCache($cacheKey);
		if(!$mediaDict){
			// generate media and image widths from the column class names
			$mediaDict = $this->mediaDict($this->colDict($this->colClasses), TRUE);
			// write cache
			$this->setMediaCache($cacheKey, $mediaDict);
		}
		// get resolution dictionary
		$maxSize = ($this->hires === static::HIRES_SOURCE) ? $this->srcWidth : $this->hires;
		$resolutionDict = $this->resolutionDict($mediaDict, $this->srcWidth, $maxSize);
		
		// make wrappers?
		$wrapCount = 0;
		$wrapperAttr = $this->wrapperAttr;
		if($wrapperAttr !== NULL){
			$colString = implode(' ', $this->colClasses);
			$wrapperAttr = $this->ensureAttr('class', $colString, $wrapperAttr);
			if($this->ratioPaddingClass && !$this->ratioWrapper){
				$wrapperAttr = $this->ensureAttr('class', $this->ratioPaddingClass, $wrapperAttr);
				$wrapperAttr = $this->ensureAttr('style', "padding-bottom:$padding".'%', $wrapperAttr);
			}
			$out .= '<div'.stringify_attributes($wrapperAttr).'>'.$this->nl();
			$wrapCount++;
		}
		// if it's an <img> with ratio padding, assume we need the ratioWrapper
		if($this->el === 'img' && $this->ratioPaddingClass) $this->ratioWrapper = TRUE;
		$ratioAttr = NULL;
		if($this->ratioWrapper){
			$ratioAttr = $this->ensureAttr('class', $this->ratioPaddingClass, []);
			$ratioAttr = $this->ensureAttr('style', "padding-bottom:$padding".'%', $ratioAttr);
			$out .= '<div'.stringify_attributes($ratioAttr).'>'.$this->nl();
			$wrapCount++;
		}
		
		// start writing the actual elements
		if($this->el === 'img'){
			// <img> with srcset
			$out .= $this->renderSrcsetImg($mediaDict, $resolutionDict);
		}else{
			// <picture>
			$pictureAttr = $this->elAttr;
			// if we don't have a wrapper and the user wants the raio padding, use it here
			if($this->ratioPaddingClass){
				$pictureAttr = $this->ensureAttr('class', $this->ratioPaddingClass, $pictureAttr);
				$pictureAttr = $this->ensureAttr('style', "padding-bottom:$padding".'%', $pictureAttr);
			}
			$out .= '<picture'.stringify_attributes($pictureAttr).'>'.$this->nl();
			foreach($resolutionDict as $mediaWidth => $data){
				$sourceAttr = [];
				$sources = [];
				$sourceAttr['media'] = '(min-width:'.$mediaWidth.'px)';
				foreach($data as $factor=>$width){
					// are we not supporting hi res devices? then skip
					if($factor > 1 && empty($this->hires)) continue;
					$src = $this->destFileName($width, $this->getResolutionMedia($factor, $sourceAttr['media']));
					// use a key here, so we don't get a bloated thing like "foo-800.jpg 4x, foo-800.jpg 3x, foo-800.jpg 2x"
					$key = $src;
					if($factor > 1) $src .= ' '.$factor.'x';
					$sources[$key] = $src;
				}
				if($this->destExt === 'webp' || $this->destExt === 'jp2'){
					$sourceAttr['type'] = 'image/'.$this->destExt;
				}
				$attrName = $this->isLazy ? 'data-srcset' : 'srcset';
				$sourceAttr[$attrName] = $this->nl().implode(', '.$this->nl(), array_values($sources));
				$out .= '<source'.stringify_attributes($sourceAttr).'>'.$this->nl();
			}
			// write the <img>
			$out .= $this->renderPictureImg($mediaDict);
			$out .= '</picture>'.$this->nl();
		} // end <picture>
		
		// close the wrappers
		$out .= str_repeat('</div>'.$this->nl(), $wrapCount);
		
		if($this->mediaDebug){
			$out = '';
			foreach($this->fileList as $index=>$file){
				$out .= $file.'<br>';
			}
		}
		if($resetAll){
			$this->resetAll();
		}else if($resetFile){
			$this->resetFile();
		}
		return $out;
	}
	
	// render the <img> element(s) for a <picture>
	protected function renderPictureImg(array $mediaDict):string{
		$out = '';
		$imgAttr = $this->imgAttr;
		$lqipAttr = $this->getLqipAttr($mediaDict); // set the 'src'
		$imgAttr = array_merge($lqipAttr, $imgAttr);
		
		// lazyload
		if($this->isLazy){
			$imgAttr = $this->ensureAttr('class', 'lazyload', $imgAttr);
			$imgAttr['data-src'] = $imgAttr['src'];
			unset($imgAttr['src']);
		}
		$out .= '<img '.stringify_attributes($imgAttr).'>'.$this->nl();
		return $out;
	}
	
	// render <img> element(s) using srcset
	protected function renderSrcsetImg(array $mediaDict, array $resolutionDict):string{
		$out = '';
		$sources = [];
		$sizes = [];
		// for <img>, we only process the zero media width (use <picture> for better control)
		$data = array_pop($resolutionDict);
		$minWidth = min($data);
		foreach($data as $factor=>$width){
			if(!$this->hires){
				$file = $this->destFileName($width, ($width === $minWidth) ? NULL : '(min-width:'.$width.'px)');
				// foo-800.jpg 800w
				$src = $file.' '.$width.'w';
				// (min-width: 860px) 800px
				$size = ($width === $minWidth) ? '' : '(min-width:'.$width.'px) ';
				$size .= $width.'px';
				$sizes[] = $size;
			}else{
				// foo-800.jpg 2x
				$file = $this->destFileName($width, $this->getResolutionMedia($factor));
				$src = $file;
				if($factor > 1) $src .= ' '.$factor.'x';
			}
			$sources[$file] = $src; // use a key here, so we don't get a bloated thing like "foo-800.jpg 4x, foo-800.jpg 3x, foo-800.jpg 2x"
		}
		
		$imgAttr = $this->elAttr;
		$lqipAttr = $this->getLqipAttr($mediaDict); // set the 'src'
		if($this->lpiqIsOwnImg){
			$out .= '<img '.stringify_attributes($lqipAttr).'>'.$this->nl();
			$imgAttr = $this->ensureAttr('alt', '', $imgAttr);
		}else{
			$imgAttr = array_merge($lqipAttr, $imgAttr);
		}
		// lazyload class
		if($this->isLazy){
			$imgAttr = $this->ensureAttr('class', 'lazyload', $imgAttr);
		}
		// srcset or data-srcset
		$attrName = $this->isLazy ? 'data-srcset' : 'srcset';
		$imgAttr[$attrName] = $this->nl().implode(', '.$this->nl(), array_values($sources));
		$out .= '<img '.stringify_attributes($imgAttr).'>'.$this->nl();
		return $out;
	}
	
	// set the src attribute of LQIP. This could also be the base <img> element
	protected function getLqipAttr(array $mediaDict):array{
		$attr = $this->lqipAttr;
		switch($this->lqip){
			case static::LQIP_XS:
				// use xs container width
				// if we didn't see a given width for xs (ie. col-6), then use the smallest in bootstrap containers
				$width = $mediaDict[0] ?? min($this->config->containers());
				$attr['src'] = $this->destFileName($width);
				break;
			case static::LQIP_PIXEL:
				// transparent pixel base64
				$attr['src'] = $this->pixel64();
				break;
			default:
				if(is_string($this->lqip) && substr($this->lqip, 0, 1) === '#'){
					// hex color
					$attr['src'] = $this->svgRect64($this->lqip);
				}else if(is_numeric($this->lqip)){
					// it's a specific width
					$attr['src'] = $this->destFileName(intval($this->lqip));
				}else if(!empty($this->lqip)){
					// something custom, I guess
					$attr['src'] = $this->lqip;
				}else{
					// empty/null. use the first image as presribed by mediaDict
					$width = $mediaDict[0] ?? min($this->config->containers());
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
	protected function colDict(array $colClasses):array{
		$colDict = [];
		foreach($colClasses as $colClass){
			$parts = explode('-', $colClass);
			if(count($parts) < 2) continue;
			$colSize = count($parts) === 3 ? $parts[1] : 'xs';
			$colNum = count($parts) === 3 ? $parts[2] : $parts[1];
			$colDict[$colNum] = $colSize;
		}
		// sort the dictionary by $colSize, from smallest bootstrap size to largest, so bigger cols overwrite mediaWidth keys generated by smaller ones
		uasort($colDict, [$this, '_colDictSort']);
		return $colDict;
	}
	
	/*
		Take a column dictionary from colDict() and make array with media-width keys and max widths for the images inside them.
		Use $forceZeroMediaSize when your regular <img> element is a LQIP
		input: [10=>'xs' 6=>'md', 2=>'lg'] output: [1200=>190, 992=>480, 768=>360, 0=>450]
	*/
	protected function mediaDict(array $colDict, ?bool $forceZeroMediaSize=FALSE):array{
		$imgMediaDict = [];
		// are there no specified cols? then assume it's full container width
		if(empty($colDict)){
			// no columns specified, so we just loop the containers
			$imgMediaDict[0] = min($this->config->containers()); // handle xs size, since it's not defined in containers
			// pull media width from breakpoints, and image widths from containers
			foreach($this->config->containers() as $containerSize=>$containerWidth){
				$mediaWidth = $this->config->breakpoint($containerSize);
				$imgMediaDict[$mediaWidth] = $this->config->container($containerSize);
			}
		}else{
			// col-* classes were indicated, so we must calculate the image widths
			foreach($this->config->containers() as $containerSize=>$containerWidth){
				foreach($colDict as $colNum=>$colSize){
					$fraction = $colNum / $this->config->gridCols; // divide by 12, or however many cols there are
					if($colSize === 'xs' || $this->config->container($colSize) <= $this->config->container($containerSize)){
						// this col is smaller or equal to the container we're looking at. process it.
						$imageWidth = ceil($containerWidth * $fraction);
						$mediaWidth = $this->config->breakpoint($containerSize);
					}else{
						// out of bounds/not needed
						continue;
					}
					// we only want one <source> for each media width. Our colDict sorting ensures it'll be the correct one.
					$imgMediaDict[$mediaWidth] = $imageWidth; 
				}
			}
		}//endif
		if($forceZeroMediaSize && !isset($imgMediaDict[0])){
			$minContainer = min($this->config->containers());
			// if we have a col class without a size (ie col-6), we need to divide the container width
			if(in_array('xs', $colDict)){
				$colNum = array_search('xs', $colDict);
				$fraction = $colNum / $this->config->gridCols;
				$imgMediaDict[0] = ceil($minContainer * $fraction);
			}else{
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
		$maxSize value can be a resolution (1-10) or a pixel width (>10), or NULL (image source width)
		Input: [1200=>190, 992=>480, 768=>360, 0=>450] Output: 
		[
			// media	=> [factor=>imageWidth, ...]
			1200 		=> [4=>760, 3=>570, 2=>380, 1=>190],
			992			=> [4=>1920, 3=>1440, 2=>960, 1=>480],
			0			=> [4=>1800, 3=>1350, 2=>900, 1=>450]
		]
	*/
	
	protected function resolutionDict(array $imgMediaDict, int $srcMaxWidth, int $maxSize=NULL):array{
		// figure out our max width and max resolution
		$maxPx = PHP_INT_MAX; // used for min() operations
		$maxResolution = $this->config->maxResolutionFactor;
		if($maxSize <= 10){
			// assume its a resolution factor, not pixel width. Ensure we're not over the max setting
			$maxResolution = ($maxSize > $this->config->maxResolutionFactor) ? $this->config->maxResolutionFactor : $maxSize;
		}else if($maxSize > 10){
			// we were given a max pixel width to NEVER go over
			$maxPx = $maxSize;
		}
		$resolutionDict = [];
		foreach($imgMediaDict as $mediaWidth=>$imageWidth){
			// loop through possible resolutions, highest first
			for($i=$maxResolution; $i>=1; $i--){
				$hiresWidth = min($maxPx, $imageWidth * $i); // never go over a specified max width
				if($hiresWidth > $srcMaxWidth){
					// too big... but see if our original is bigger than the "normal" resolution. At least we can sample up.
					if($srcMaxWidth > $imageWidth){
						$w = min($maxPx, $srcMaxWidth); // never go over a specified max width
						if(!isset($resolutionDict[$mediaWidth])) $resolutionDict[$mediaWidth] = [];
						$resolutionDict[$mediaWidth][$i] = $w;
					}else{
						// nada... don't add to srcset
					}
				}else{
					// the high res width is acceptable to use
					if(!isset($resolutionDict[$mediaWidth])) $resolutionDict[$mediaWidth] = [];
					$resolutionDict[$mediaWidth][$i] = $hiresWidth;
				}
			} // endfor
		}
		return $resolutionDict;
	}
	
	// sorting comparison
	public function _colDictSort(string $a, string $b){
		$aVal = ($a === 'xs') ? 0 : $this->config->container($a);
		$bVal = ($b === 'xs') ? 0 : $this->config->container($b);
		return ($aVal <=> $bVal); 
	}
	
	// squre/portrait/landscape
	public function getOrientation():string{
		if(empty($this->srcWidth) || empty($this->srcHeight)) throw new \Exception("Cannot get orientation of source file");
		$orientation = 'square';
		if($this->srcWidth > $this->srcHeight){
			$orientation = 'landscape';
		}else if($this->srcWidth < $this->srcHeight){
			$orientation = 'portrait';
		}
		return $orientation;
	}
	
	protected function getMediaCache(string $key){
		return $this->mediaCache[$key] ?? NULL;
	}
	
	protected function setMediaCache(string $key, array $value){
		$this->mediaCache[$key] = $value;
	}
	
	// ensure attributes are an array with the given class(es)
	protected function ensureAttr(string $attrName, string $attrValue, $attr=NULL):array{
		if(empty($attr)){
			$attr = [];
		}else if(!is_array($attr)){
			throw new \Exception("Attributes must be passed as an associative array");
		}
		$sep = $attrName === 'style' ? ';' : ($attrName === 'class' ? ' ' : '');
		if(isset($attr[$attrName])){
			$attr[$attrName] .= $sep.$attrValue;
		}else{
			$attr[$attrName] = $attrValue;
		}
		return $attr;
	}
	
	protected function destFileName(int $width, ?string $media=NULL):string{
		$file = $this->config->dynamicImageFileName($this->dest, $this->destExt, $width);
		// add to our file list, so we can output filename instead of the elements
		if($this->mediaDebug){
			$id = 'bs-'.md5($media.$file.uniqid()); // prevent ids from starting with a number
			$parts = explode('/', $file);
			$f = array_pop($parts);
			$style = $media ? '<style type="text/css">@media '.$media.'{#'.$id.'{font-weight:bold;}}</style>' : '<style type="text/css">#'.$id.'{font-weight:bold;}</style>';
			$this->fileList[] = $style.'<span id="'.$id.'">'.$f.'</span>';
		}
		
		// query string
		$q = '';
		if(!empty($this->destQuery)){
			if(is_string($this->destQuery)){
				$q = strpos($this->destQuery, '?') === FALSE ? '?' : '';
				$q .= $this->destQuery;
			}else if(is_array($this->destQuery)){
				$q = '?'.http_build_query($this->destQuery);
			}
		}
		return $file.$q;
	}
	
	protected function stripExt(string $src):array{
		$parts = explode('.', $src);
		$ext = array_pop($parts);
		return [implode('.', $parts), $ext];
	}
	
	// transparent pixel base64
	public function pixel64(){
		return 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
	}
	
	// svg rect base64
	public function svgRect64(string $color):string{
		$svg = '<svg preserveAspectRatio="none" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="'.$color.'" /></svg>';
		return 'data:image/svg+xml;base64,'.base64_encode($svg);
	}
	
	// for debug only. but yuck!!
	protected function getResolutionMedia($factor, ?string $otherMedia=NULL):string{
		if($factor > 1){
			$parts = [];
			$dpi = $factor * 96;
			$parts[] = "-webkit-min-device-pixel-ratio: $factor";
			$parts[] = "min--moz-device-pixel-ratio: $factor";
			$parts[] = "-o-min-device-pixel-ratio: $factor/1";
			$parts[] = "min-device-pixel-ratio: $factor";
			$parts[] = "min-resolution: $dpi".'dpi';
			$parts[] = "min-resolution: $factor".'dppx';
			$otherMedia = $otherMedia ? ' and '.$otherMedia : '';
			$out = '('.implode(')'.$otherMedia.', (', $parts).')'.$otherMedia;
		}else{
			$out = $otherMedia ?? '';
		}
		return $out;
	}
	
	protected function nl():string{
		return $this->prettyPrint ? $this->nl : '';
	}
	protected function tab():string{
		return $this->prettyPrint ? '	' : '';
	}
}
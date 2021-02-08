<?php namespace Tomkirsch\Bootstrap;

use CodeIgniter\Config\BaseConfig;

class BootstrapConfig extends BaseConfig{
	// return the file to be used
	public function dynamicImageFileName(string $src, string $ext, int $width){
		$file = $src.'.'.$ext;
		$params = ['f'=>$file, 'w'=>$width];
		return base_url('resize?'.http_build_query($params));
	}
	
	// newlines in output
	public $prettyPrint = FALSE;
	
	// default element, 'img' or 'picture'
	public $defaultElement = 'picture';
	
	// use lazyload by default
	public $defaultIsLazy = FALSE;
	
	// default hires setting
	public $defaultHires = 'source';
	
	// default LQIP (low quality image placeholder)
	public $defaultLqip = 'xs';
	
	// use padding-bottom hack by default
	public $defaultUseRatio = TRUE;
	
	// class name for padding hack
	public $defaultRatioPaddingClass = 'ratiobox';
	
	// class name for cropping to a ratio (overflow:hidden)
	public $defaultRatioCropClass = 'ratio-crop';
	
	// maximum supported resolution factor (2x, 3x, etc)
	public $defaultMaxResolution = 2;
	
	// default resolution step to get from 1 to $maxResolutionFactor
	public $defaultResolutionStep = 0.5;
	
	// number of columns in the grid
	public $gridCols = 12;
	
	// bootstrap version. used to get the correct container/breakpoint
	public $bsVersion = '4';
	
	// container widths and breakpoints. Make sure these are ordered LARGEST to SMALLEST!
	public $containers = [
		'v4'=>[
			'xl'=>1140,
			'lg'=>960,
			'md'=>720,
			'sm'=>540,
		],
		'v5'=> [
			'xxl'=>1320,
			'xl'=>1140,
			'lg'=>960,
			'md'=>720,
			'sm'=>540,
		],
	];
	
	public $breakpoints = [
		'v4'=>[
			'xl'=>1200,
			'lg'=>992,
			'md'=>768,
			'sm'=>576,
		],
		'v5'=> [
			'xxl'=>1400,
			'xl'=>1200,
			'lg'=>992,
			'md'=>768,
			'sm'=>576,
		],
	];
	
	public function containers(?string $version=NULL):array{
		$v = $version ?? $this->bsVersion;
		return $this->containers['v'.$v];
	}
	public function container(string $size, ?string $version=NULL):int{
		return $this->containers($version)[$size];
	}
	public function breakpoints(?string $version=NULL):array{
		$v = $version ?? $this->bsVersion;
		return $this->breakpoints['v'.$v];
	}
	public function breakpoint(string $size, ?string $version=NULL):int{
		return $this->breakpoints($version)[$size];
	}
}
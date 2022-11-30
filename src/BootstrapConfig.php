<?php

namespace Tomkirsch\Bootstrap;

use CodeIgniter\Config\BaseConfig;

class BootstrapConfig extends BaseConfig
{
	/**
	 * Bootstrap version. used to get the correct container/breakpoint 
	 */
	public $bsVersion = '5';

	/**
	 * Use newlines in HTML output
	 */
	public $prettyPrint = FALSE;

	/**
	 * Container widths and breakpoints. Make sure these are ordered LARGEST to SMALLEST! 
	 */
	public $containers = [
		'v4' => [
			'xl' => 1140,
			'lg' => 960,
			'md' => 720,
			'sm' => 540,
		],
		'v5' => [
			'xxl' => 1320,
			'xl' => 1140,
			'lg' => 960,
			'md' => 720,
			'sm' => 540,
		],
	];

	public $breakpoints = [
		'v4' => [
			'xl' => 1200,
			'lg' => 992,
			'md' => 768,
			'sm' => 576,
		],
		'v5' => [
			'xxl' => 1400,
			'xl' => 1200,
			'lg' => 992,
			'md' => 768,
			'sm' => 576,
		],
	];

	/**
	 * DynamicImage - You can use a custom function to generate the public-facing dynamic image filename
	 */
	public function dynamicImageFileName(string $src, string $ext, int $width)
	{
		$file = $src . '.' . $ext;
		$params = ['f' => $file, 'w' => $width];
		return base_url('resize?' . http_build_query($params));
	}

	/**
	 * DynamicImage - number of columns in the grid 
	 */
	public $gridCols = 12;

	/**
	 * DynamicImage - Default element to use, 'img' or 'picture'
	 */
	public $defaultElement = 'picture';

	/**
	 * DynamicImage - Default use data-src and data-srcset instead of src and srcset
	 */
	public $defaultIsLazy = FALSE;

	/**
	 * DynamicImage - Default size for hires
	 */
	public $defaultHires = 'source';

	/**
	 * DynamicImage - default LQIP (low quality image placeholder) 
	 */
	public $defaultLqip = 'xs';

	/**
	 * DynamicImage - use padding hack by default 
	 */
	public $defaultUseRatio = FALSE;

	/**
	 * DynamicImage - maximum supported resolution factor (2x, 3x, etc) 
	 */
	public $defaultMaxResolution = 2;

	/**
	 * DynamicImage - default resolution step to get from 1 to $maxResolutionFactor 
	 */
	public $defaultResolutionStep = 0.5;


	/**
	 * Utility
	 */
	public function containers(?string $version = NULL): array
	{
		$v = $version ?? $this->bsVersion;
		return $this->containers['v' . $v];
	}
	/**
	 * Utility
	 */
	public function container(string $size, ?string $version = NULL): int
	{
		return $this->containers($version)[$size];
	}
	/**
	 * Utility
	 */
	public function breakpoints(?string $version = NULL): array
	{
		$v = $version ?? $this->bsVersion;
		return $this->breakpoints['v' . $v];
	}
	/**
	 * Utility
	 */
	public function breakpoint(string $size, ?string $version = NULL): int
	{
		return $this->breakpoints($version)[$size];
	}
}

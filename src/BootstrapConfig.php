<?php

namespace Tomkirsch\Bootstrap;

use CodeIgniter\Config\BaseConfig;

class BootstrapConfig extends BaseConfig
{
	/**
	 * Bootstrap version. used to get the correct container/breakpoint 
	 */
	public string $bsVersion = '5';

	/**
	 * Use newlines in HTML output
	 */
	public bool $prettyPrint = FALSE;

	/**
	 * Container widths and breakpoints. Make sure these are ordered LARGEST to SMALLEST! 
	 */
	public array $containers = [
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

	public array $breakpoints = [
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
	 * DynamicImage - You can use a custom function to generate the public-facing dynamic image filename.
	 */
	public function dynamicImageFileName(string $src, string $ext, int $width): ?string
	{
		$file = $src . '.' . $ext;
		$params = ['f' => $file, 'w' => $width];
		return base_url('resize?' . http_build_query($params));
	}

	/**
	 * DynamicImage - Col gutter width in pixels
	 */
	public int $defaultGutterWidth = 12;

	/**
	 * DynamicImage - number of columns in the grid 
	 */
	public int $gridCols = 12;

	/**
	 * DynamicImage - Default element to use, 'img' or 'picture'
	 */
	public string $defaultElement = 'picture';

	/**
	 * DynamicImage - Default use data-src and data-srcset instead of src and srcset
	 */
	public bool $defaultIsLazy = FALSE;

	/**
	 * DynamicImage - Default size for hires. Use "source" for the source image's width, or a pixel value to restrict viewing
	 * @var string|int
	 */
	public $defaultHiresWidth = 'source';

	/**
	 * DynamicImage - Use a pixel value to restrict image height
	 * @var string|int
	 */
	public $defaultHiresHeight = 'source';

	/**
	 * DynamicImage - default LQIP (low quality image placeholder) 
	 */
	public string $defaultLqip = 'xs';

	/**
	 * DynamicImage - use padding hack by default 
	 */
	public bool $defaultUseRatio = FALSE;

	/**
	 * DynamicImage - maximum supported resolution factor (2x, 3x, etc) 
	 */
	public float $defaultMaxResolution = 2;

	/**
	 * DynamicImage - default resolution step to get from 1 to $maxResolutionFactor.
	 * A lower number will create more sources
	 */
	public float $defaultResolutionStep = 0.5;


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

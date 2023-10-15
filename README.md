# Bootstrap Library for CI4

## Installation

Add the requirement to `composer.json`:

```
    "require": {
		"tomkirsch/bootstrap":"^3"
	}
```

Update your project: `composer install --no-dev --optimize-autoloader`

Create the config file `Config\Bootstrap.php` and set your preferences. You MUST extend `Tomkirsch/Bootstrap/BootstrapConfig`

```
<?php namespace Config;

use Tomkirsch\Bootstrap\BootstrapConfig;

class Bootstrap extends BootstrapConfig{
	/**
	 * Bootstrap version. used to get the correct container/breakpoint
	 */
	public string $bsVersion = '5';

	/**
	 * Use newlines in HTML output
	 */
	public bool $prettyPrint = FALSE;

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
}
```

The `dynamicImageFileName()` function should generate a URL/path to a controller that serves dynamic images.

To use containers in a newer version of bootstrap, just add them to the `containers` and `breakpoints` arrays, then set $bsVersion to the array key. See `Tomkirsch\Bootstrap\BootstrapConfig` for more info.

Create the service in `Config\Services.php`:

```
use Tomkirsch\Bootstrap\Bootstrap;
class Services extends BaseService
{
	public static function bootstrap($config = null, bool $getShared = TRUE): Bootstrap
    {
        $config = $config ?? new Config\Bootstrap();
        return $getShared ? static::getSharedInstance('bootstrap', $config) : new Bootstrap($config);
    }
}
```

Create your image resizer controller. The directives here must match the output from `dynamicImageFileName()` in the config class. Here's a quick example:

```
class Resize extends BaseController{
	public function index(){
		$file = $this->request->getGet('f');
		$width = $this->request->getGet('w');
		$resource = service('image')
			->withFile($file)
			->resize($width, $width, TRUE, 'width')
			->getResource()
		;
		ob_end_flush();
		header('Content-Type: image/jpeg');
		imagejpeg($resource, NULL, 75);
		imagedestroy($resource);
		exit;
	}
}
```

If you'd like to use padding ratio elements, or lazyload transitions, define them in your CSS. Ensure the relative path gets you to the root folder.

```
@import "../vendor/tomkirsch/bootstrap/src/styles";
```

Include lazysizes JS
`<script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.0/lazysizes.min.js" integrity="sha512-JrL1wXR0TeToerkl6TPDUa9132S3PB1UeNpZRHmCe6TxS43PFJUcEYUhjJb/i63rSd+uRvpzlcGOtvC/rDQcDg==" crossorigin="anonymous"></script>`

## Usage

Static Image (no bootstrap cols)

```
<?= \Config\Services::bootstrap()->staticImage()->renderSources([
	"prettyPrint" => TRUE,
	"imgAttr" => ["class" => "img-fluid"], // optional - creates the <img>
	"widths" => [2080, 1040, 520, 260],
	"file" => function ($width, $resolution) {
		return "kitten-$width.jpg";
	},
]) ?>
```

Full example (see `tests/views/welcome_message.php` for more)

```
<?= \Config\Services::bootstrap()->dynamicImage([
	"file" => "foo.jpg", // source image. will be rewritten as foo-xxx.jpg, based on your config
	"size" => [2000, 1333], // always enter your src image size to prevent getimagesize() lag
	"query" => ["q" => rand()], // prevent caching
	"colClasses" => "col-md-4 py-2 dyn_fadebox", // use fadebox class
	"colWrapper" => TRUE, // create the wrapper div above
	"ratio"	=> 16/9, // container will be a 16:9 rectangle. use TRUE for the original image ratio, or 1 for a square
	"ratioCrop" => TRUE,  // crop & center the pic
	"lazy" => TRUE, // lazyload
	"lqipSeparate" => TRUE, // use a separate image for low quality placeholder
	"lqip" => "xs", // use the image at the xs container width
	"loop"=>TRUE, // set this when looping to optimize col calculations
]) ?>
```

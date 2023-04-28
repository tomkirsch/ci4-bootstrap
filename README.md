# Bootstrap Library for CI4

## Installation

Add the requirement to `composer.json`:

```
    "require": {
		"tomkirsch/bootstrap":"^1"
	}
```

Update your project: `composer install --no-dev --optimize-autoloader`

Create the config file `Config\Bootstrap.php` and set your preferences. You MUST extend `Tomkirsch/Bootstrap/BootstrapConfig`

```
<?php namespace Config;

use Tomkirsch\Bootstrap\BootstrapConfig;

class Bootstrap extends BootstrapConfig{
	// return the file to be used
	public function dynamicImageFileName(string $src, string $ext, int $width){
		$file = $src.'.'.$ext;
		$params = ['f'=>$file, 'w'=>$width];
		return base_url('resize?'.http_build_query($params));
	}

	// newlines in output
	public bool $prettyPrint = FALSE;

	// default element, 'img' or 'picture'
	public string $defaultElement = 'picture';

	// use lazyload by default
	public bool $defaultIsLazy = FALSE;

	// default hires setting
	public string $defaultHires = 'source';

	// default LQIP (low quality image placeholder)
	public string $defaultLqip = 'xs';

	// use padding-bottom hack on wrapper
	public string $defaultRatioPaddingClass = 'ratiobox';

	// maximum supported resolution factor (2x, 3x, etc)
	public float $defaultMaxResolution = 4;

	// default resolution step to get from 1 to $maxResolutionFactor
	public float $defaultResolutionStep = 1;

	// number of columns in the grid
	public int $gridCols = 12;

	// bootstrap version. used to get the correct container/breakpoint
	public string $bsVersion = '5';
}
```

The `dynamicImageFileName()` function should generate a URL/path to a controller that serves dynamic images.

To use containers in a newer version of bootstrap, just add them to the `containers` and `breakpoints` arrays, then set $bsVersion to the array key. See `Tomkirsch\Bootstrap\BootstrapConfig` for more info.

Create the service in `Config\Services.php`:

```
	public static function bootstrap($getShared = true, $config=NULL){
		if(!$config) $config = config('bootstrap');
		return $getShared ? static::getSharedInstance('bootstrap') : new \Tomkirsch\Bootstrap\Bootstrap($config);
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

If you'd like to use padding ratio elements, or lazyload transitions, define them in your CSS.

```
	.ratiobox{
		position: relative;
		height: 0;
		display: block;
		width: 100%;
	}
	.ratiobox *{
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		display: block;
	}
	.fadebox img{
		position: absolute;
		transition: opacity 2s;
	}
	.fadebox .lazyload,
	.fadebox .lazyloading {
		opacity: 0;
	}
	.fadebox .lazyloaded {
		opacity: 1;
	}
```

Include lazysizes JS
`<script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.0/lazysizes.min.js" integrity="sha512-JrL1wXR0TeToerkl6TPDUa9132S3PB1UeNpZRHmCe6TxS43PFJUcEYUhjJb/i63rSd+uRvpzlcGOtvC/rDQcDg==" crossorigin="anonymous"></script>`

## Usage

```
	<div class="container mb-3">
		<h3>Flex Columns</h3>
		<div class="card-deck">
			<?php
			$map = [
				'sm'=>2, // wrap every 2 cards on sm
				'md'=>3, // wrap every 3 cards on md
				'lg'=>2,
				'xl'=>5,
			];
			for($i=1; $i<=6; $i++): ?>
			<div class="card mb-3">
				<h1><?= $i ?></h1>
			</div>
			<?= service('bootstrap')->flexColumn($i, $map); ?>
			<?php endfor; ?>
		</div>
	</div>
	<hr>
	<div class="container mb-3">
		<h3>Max Resolution: 1x</h3>
		<?php foreach(['col', 'col-md-6 col-lg-4 col-xl-2'] as $cols): ?>
		<div class="row">
			<div class="<?= $cols ?>">
				<h5><?= $cols ?></h5>
				<?= service('bootstrap')
					->dynamicImage($file)
					->cols($cols)
					->debug($debug)
					->hires(NULL)
					->element('picture', [], ['alt'=>'A cute kitten', 'class'=>'img-fluid'])
					->render();
				?>
			</div>
		</div>
		<?php endforeach; ?>
		<hr>
		<h3>Max Resolution: 2x, Step 0.5, Lazyload</h3>
		<?php foreach(['col', 'col-md-6 col-lg-4 col-xl-2'] as $cols): ?>
		<div class="row">
			<div class="<?= $cols ?>">
				<h5><?= $cols ?></h5>
				<?= service('bootstrap')
					->dynamicImage($file)
					->debug($debug)
					->cols($cols)
					->hires(2, 0.5)
					->lazy(TRUE)
					->element('picture', [], ['alt'=>'A cute kitten', 'class'=>'img-fluid'])
					->render();
				?>
			</div>
		</div>
		<?php endforeach; ?>
		<hr>
		<h3>Max Width: 800px (Max Resolution <?= config('Tomkirsch\Bootstrap\BootstrapConfig')->defaultMaxResolution ?>x)</h3>
		<?php foreach(['col', 'col-md-6 col-lg-4 col-xl-2'] as $cols): ?>
		<div class="row">
			<div class="<?= $cols ?>">
				<h5><?= $cols ?></h5>
				<?= service('bootstrap')
					->dynamicImage($file)
					->debug($debug)
					->cols($cols)
					->hires(800)
					->element('picture', [], ['alt'=>'A cute kitten', 'class'=>'img-fluid'])
					->render();
				?>
			</div>
		</div>
		<?php endforeach; ?>
		<hr>
		<h3>LQIP Width:100px, Hex Color</h3>
		<div class="row">
			<?= service('bootstrap')
				->dynamicImage($file)
				->debug($debug)
				->cols('col-6', ['class'=>'wrapperClass'])
				->ratio(NULL)
				->lqip(100)
				->element('picture', [], ['alt'=>'A cute kitten', 'class'=>'img-fluid'])
				->render();
			?>
			<?= service('bootstrap')
				->dynamicImage($file)
				->debug($debug)
				->cols('col-6', ['class'=>'wrapperClass'])
				->ratio(NULL)
				->lqip('#FF0000')
				->element('picture', [], ['alt'=>'A cute kitten', 'class'=>'img-fluid'])
				->render();
			?>
		</div>
		<hr>
		<h3>LQIP Lazyload Fade-In</h3>
		<div class="row">
			<div class="col-6">
				<?= service('bootstrap')
					->dynamicImage($file)
					->debug($debug)
					->cols('col-6')
					->ratio('ratiobox fadebox') // add fadebox class for css transition & positioning
					->lazy(TRUE)
					->lqip(100, [], TRUE) // lqip must be a separate <img> element
					->element('img', ['alt'=>'A cute kitten', 'class'=>'img-fluid']) // fade transition won't work for <picture>!
					->render();
				?>
			</div>
			<div class="col-6">
				<?= service('bootstrap')
					->dynamicImage($file)
					->debug($debug)
					->cols('col-6')
					->ratio('ratiobox fadebox') // add fadebox class for css transition & positioning
					->lazy(TRUE)
					->lqip('#FF0000', [], TRUE) // lqip must be a separate <img> element
					->element('img', ['alt'=>'A cute kitten', 'class'=>'img-fluid']) // fade transition won't work for <picture>!
					->render();
				?>
			</div>
		</div>
	</div><!-- container -->
```

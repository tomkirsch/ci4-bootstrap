# Bootstrap Library for CI4

##Installation
Add the requirement to `composer.json`:
```
    "require": {
		"tomkirsch/bootstrap":"^1"
	}
```

Update: `composer install --no-dev --optimize-autoloader`

If not using the dynamic image library, then you're done. For dynamic images, continue to install -

Create the config file: `Config\Bootstrap.php` and set your preferences:
```
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
	
	// use padding-bottom hack on wrapper
	public $defaultRatioPaddingClass = 'ratiobox';
	
	// maximum supported resolution factor (2x, 3x, etc)
	public $defaultMaxResolution = 4;
	
	// default resolution step to get from 1 to $maxResolutionFactor
	public $defaultResolutionStep = 1;
	
	// number of columns in the grid
	public $gridCols = 12;
	
	// bootstrap version. used to get the correct container/breakpoint
	public $bsVersion = '4';
}
```
The `dynamicImageFileName()` function should generate a URL/path to a controller that serves dynamic images.

To use containers in a newer version of bootstrap, just add them to the `containers` and `breakpoints` arrays, then set $bsVersion to the array key. See `tomkirsch\Bootstrap\BootstrapConfig` for more info.

Create the service in `Config\Services.php`:
```
	public static function bootstrap($getShared = true, $config=NULL){
		if(!$config) $config = config('bootstrap');
		return $getShared ? static::getSharedInstance('bootstrap') : new \Tomkirsch\Bootstrap\Bootstrap($config);
	}
```

Create your image resizer controller. Here's a quick example:
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
Now you can use it in your views, or wherever:
```
<?= service('bootstrap')
	->dynamicImage('kitten.jpg')
	->cols('col-md-6')
	->element('picture', [], ['alt'=>'A cute kitten', 'class'=>'img-fluid'])
	->render();
?>
```
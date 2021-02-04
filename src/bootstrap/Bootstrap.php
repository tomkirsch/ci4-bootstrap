<?php namespace Tomkirsch\Bootstrap;

class Bootstrap{
	protected $config;
	protected $dynamicImage;
	
	public function __construct(?BsConfig $config=NULL){
		$this->config = $config ?? new BootstrapConfig();
	}
	
	public function bootstrapVersion($version){
		if(!array_key_exists('v'.$version, $this->config->containers)) throw new \Exception("Bootstrap v$version is not supported, please add data to BootstrapConfig.");
		$this->config->bsVersion = $version;
		return $this;
	}
	
	// perform dynamic image operations - see DynamicImage class
	public function dynamicImage(?string $src=NULL, ?string $dest=NULL, $query=NULL):DynamicImage{
		if(!$this->dynamicImage){
			$this->dynamicImage = new DynamicImage($this->config);
		}
		if($src){
			$this->dynamicImage->withFile($src, $dest, $query);
		}
		return $this->dynamicImage;
	}
	
	
	/* 
		Display children of flexbox with breakpoints. Iterator must be greater than zero.
		ex: 
		<div class="card-deck">
			<?php $i=0; foreach($items as $item): ?>
			<div class="card">
				<?= $item ?>
			</div>
			<!-- call flexColumns() AFTER the .card div -->
			<?= $bsUtils->flexColumns(++$i, [
				'sm'=>2, // wrap every 2 cards on sm
				'md'=>3, // wrap every 3 cards on md
				'lg'=>4,
				'xl'=>5,
			]); ?>
			<?php endforeach; ?>
		</div>
	*/
	public function flexColumns(int $i, array $config):string{
		$out = '';
		if($i === 0) throw new \Exception('Iterator must be greater than zero, use ++$i in a loop.');
		foreach($config as $size=>$num){
			if($i % $num === 0){
				$out .= '<div class="w-100 d-none ';
				switch($size){
					case 'sm': $out .= 'd-sm-block d-md-none'; break;
					case 'md': $out .= 'd-md-block d-lg-none'; break;
					case 'lg': $out .= 'd-lg-block d-xl-none'; break;
					case 'xl': $out .= $this->config->bsVersion > 5 ? 'd-xl-block d-xxl-none' : 'd-xl-block'; break;
					case 'xxl': $out .= $this->config->bsVersion > 5 ? 'd-xxl-block' : ''; break;
				}
				$out .= '"></div>';
			}
		}
		return $out;
	}
}
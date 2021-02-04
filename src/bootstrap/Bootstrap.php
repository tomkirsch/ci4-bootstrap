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
	
	public function dynamicImage(?string $src=NULL, ?string $dest=NULL, $query=NULL):DynamicImage{
		if(!$this->dynamicImage){
			$this->dynamicImage = new DynamicImage($this->config);
		}
		if($src){
			$this->dynamicImage->withFile($src, $dest, $query);
		}
		return $this->dynamicImage;
	}
	
}
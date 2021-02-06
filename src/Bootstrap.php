<?php namespace Tomkirsch\Bootstrap;

class Bootstrap{
	protected $config;
	protected $dynamicImage;
	
	public function __construct(?BootstrapConfig $config=NULL){
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
		Size detection for JS - output this anywhere in your <body>
		Now you can get the size in real time:
			let size = $('.js-bootstrap-sizes .d-block').getAttribute('data-size');
	*/
	public function sizeDetectHtml($className='js-bootstrap-sizes', $debug=FALSE):string{
		$out = '<div class="'.$className.'">';
		$containers = $this->config->containers();
		// add the xs
		$containers['xs'] = 0;
		foreach($containers as $size1=>$width1){
			$classes = '';
			foreach($containers as $size2=>$width2){
				$s = $size2 === 'xs' ? '' : '-'.$size2;
				$vis = ($width1 === $width2) ? 'block' : 'none';
				$classes .= " d$s-$vis";
			}
			$text = $debug ? $size1 : '';
			$out .= '<div class="'.trim($classes).'" data-size="'.$size1.'">'.$text.'</div>';
		}
		$out .= '</div>';
		return $out;
	}
	
	
	/* 
		Display children of flexbox with breakpoints. Iterator must be greater than zero.
		ex: 
		<div class="card-deck">
			<?php $i=0; foreach($items as $item): ?>
			<div class="card">
				<?= $item ?>
			</div>
			<!-- call flexColumn() AFTER the .card div -->
			<?= $bsUtils->flexColumn(++$i, [
				'sm'=>2, // wrap every 2 cards on sm
				'md'=>3, // wrap every 3 cards on md
				'lg'=>4,
				'xl'=>5,
			]); ?>
			<?php endforeach; ?>
		</div>
	*/
	public function flexColumn(int $i, array $map):string{
		$out = '';
		if($i === 0) throw new \Exception('Iterator must be greater than zero, use ++$i in a loop.');
		$containers = $this->config->containers();
		asort($containers); // smallest to biggest
		foreach($map as $size=>$num){
			if($i % $num === 0){
				$out .= '<div class="w-100 d-none ';
				$width = $this->config->container($size);
				$hiddenWasProcessed = FALSE;
				foreach($containers as $otherSize=>$otherWidth){
					if($otherWidth < $width) continue;
					if($otherWidth === $width){
						$out .= "d-$otherSize-block ";
					}else if(!$hiddenWasProcessed){
						$out .= "d-$otherSize-none ";
						$hiddenWasProcessed = TRUE;
					}
				}
				$out .= '"></div>';
			}
		}
		return $out;
	}
}
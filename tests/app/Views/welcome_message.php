<?php
$file = 'kitten-src.jpg';
$debug = FALSE;
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Welcome to CodeIgniter 4!</title>
	<meta name="description" content="The small framework with powerful features">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="shortcut icon" type="image/png" href="/favicon.ico"/>
	
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
	
	<style type="text/css">
		.col, [class*="col"]{
			border:#666 1px solid;
			background: #CCC;
			word-wrap: break-word;
		}
		h3{
			font-size: 1rem;
		}
		h5{
			font-size: 0.75rem;
		}
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
	</style>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.0/lazysizes.min.js" integrity="sha512-JrL1wXR0TeToerkl6TPDUa9132S3PB1UeNpZRHmCe6TxS43PFJUcEYUhjJb/i63rSd+uRvpzlcGOtvC/rDQcDg==" crossorigin="anonymous"></script>
</head>
<body>
	<div class="container mb-3">
		<h3>Max Resolution: 1x</h3>
		<?php foreach(['col', 'col-md-6 col-lg-4 col-xxl-2'] as $cols): ?>
		<div class="row">
			<div class="<?= $cols ?>">
				<h5><?= $cols ?></h5>
				<?= service('bootstrap')
					->bootstrapVersion(5)
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
		<h3>Max Resolution: 2x, Lazyload</h3>
		<?php foreach(['col', 'col-md-6 col-lg-4 col-xl-2'] as $cols): ?>
		<div class="row">
			<div class="<?= $cols ?>">
				<h5><?= $cols ?></h5>
				<?= service('bootstrap')
					->dynamicImage($file)
					->debug($debug)
					->cols($cols)
					->hires(2)
					->lazy(TRUE)
					->element('picture', [], ['alt'=>'A cute kitten', 'class'=>'img-fluid'])
					->render();
				?>
			</div>
		</div>
		<?php endforeach; ?>
		<hr>
		<h3>Max Width: 800px (Max Resolution <?= config('Tomkirsch\Bootstrap\BootstrapConfig')->maxResolutionFactor ?>x)</h3>
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
</body>
</html>

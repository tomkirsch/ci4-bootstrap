<?php
$file = 'kitten-src.jpg';
$debug = FALSE;
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<title>Bootstrap Tests</title>
	<meta name="description" content="The small framework with powerful features">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="shortcut icon" type="image/png" href="/favicon.ico" />

	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

	<style type="text/css">
		/******** Following rules are for testing visuals only ********/
		.col,
		[class*="col"] {
			border: #666 1px solid;
			background: #CCC;
			word-wrap: break-word;
		}

		.bootstrap-sizes div {
			background-color: cadetblue;
			height: 50px;
			text-align: center;
			padding: 15px;
		}

		img {
			max-width: 100%;
		}

		h3 {
			font-size: 1rem;
		}

		h5 {
			font-size: 0.75rem;
		}
	</style>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.0/lazysizes.min.js" integrity="sha512-JrL1wXR0TeToerkl6TPDUa9132S3PB1UeNpZRHmCe6TxS43PFJUcEYUhjJb/i63rSd+uRvpzlcGOtvC/rDQcDg==" crossorigin="anonymous"></script>

	<?= view("Tomkirsch\Bootstrap\styles", ["withTag" => TRUE]) ?>
	<?= view("Tomkirsch\Bootstrap\scripts") ?>
</head>

<body>

	<div class="container mb-3">
		<h3>Size Detection</h3>
		<?= \Config\Services::bootstrap()->sizeDetectHtml('bootstrap-sizes', TRUE) ?>

		<h3>Flex Columns</h3>
		<div class="card-group">
			<?php
			$map = [
				'sm' => 2, // wrap every 2 cards on sm
				'md' => 3, // wrap every 3 cards on md
				'lg' => 2,
				'xl' => 3,
				'xxl' => 6,
			];
			for ($i = 1; $i <= 6; $i++) : ?>
				<div class="card mb-3">
					<h1><?= $i ?></h1>
				</div>
				<?= \Config\Services::bootstrap()->flexColumn($i, $map); ?>
			<?php endfor; ?>
		</div>
	</div>
	<hr>
	<div class="container mb-3">
		<h3>Max Resolution: 1x</h3>
		<?php foreach (['col-xs-12', 'col-md-6 col-lg-4 col-xl-2'] as $cols) : ?>
			<div class="row">
				<?= \Config\Services::bootstrap()
					->dynamicImage($file, "kitties!")
					->cols($cols, [])
					->debug($debug)
					->hires(NULL) // no hi res support
					->render();
				?>
			</div>
		<?php endforeach; ?>
		<hr>
		<h3>Max Resolution: 2x, Step 0.5, Lazyload</h3>
		<?php foreach (['col-xs-12', 'col-md-6 col-lg-4 col-xl-2'] as $cols) : ?>
			<div class="row">
				<?= \Config\Services::bootstrap()
					->dynamicImage($file, "kitties!")
					->debug($debug)
					->cols($cols, [])
					->hires(2)
					->lazy(TRUE)
					->render();
				?>
			</div>
		<?php endforeach; ?>
		<hr>
		<h3>Max Width: 800px (Max Resolution <?= config('Tomkirsch\Bootstrap\BootstrapConfig')->defaultMaxResolution ?>x)</h3>
		<?php foreach (['col-xs-12', 'col-md-6 col-lg-4 col-xl-2'] as $cols) : ?>
			<div class="row">
				<?= \Config\Services::bootstrap()
					->dynamicImage($file, "kitties!")
					->debug($debug)
					->cols($cols, [])
					->hires(800)
					->render();
				?>
			</div>
		<?php endforeach; ?>
		<h3>Aspect Ratio 2:3 - with &amp; without cropping</h3>
		<div class="row">
			<?= \Config\Services::bootstrap()
				->dynamicImage($file, "kitties!")
				->debug($debug)
				->cols("col-md-4", [])
				->ratio("2/3", FALSE)
				->render();
			?>
			<?= \Config\Services::bootstrap()
				->dynamicImage($file, "kitties!")
				->debug($debug)
				->cols("col-md-4", [])
				->ratio("2/3", TRUE)
				->render();
			?>
		</div>
		<h3>Aspect Ratio 1:1 - with &amp; without cropping</h3>
		<div class="row">
			<?= \Config\Services::bootstrap()
				->dynamicImage("kitten-portrait-src.jpg", "kitties!")
				->debug($debug)
				->cols("col-md-4", [])
				->ratio(1, FALSE)
				->render();
			?>
			<?= \Config\Services::bootstrap()
				->dynamicImage("kitten-portrait-src.jpg", "kitties!")
				->debug($debug)
				->cols("col-md-4", [])
				->ratio(1, TRUE)
				->render();
			?>
		</div>
		<hr>
		<h3>LQIP Width:100px, LQIP Hex Color</h3>
		<div class="row">
			<?= \Config\Services::bootstrap()
				->dynamicImage($file, "kitties!")
				->debug($debug)
				->cols('col-6', ['class' => 'wrapperClass'])
				->lqip(100)
				->element("picture", [], ["class" => "img-fluid"]) // when not using ratio, we must specify max-width
				->render();
			?>
			<?= \Config\Services::bootstrap()
				->dynamicImage($file, "kitties!")
				->debug($debug)
				->cols('col-6', ['class' => 'wrapperClass'])
				->lqip('#FF0000')
				->element("picture", [], ["class" => "img-fluid"]) // when not using ratio, we must specify max-width
				->render();
			?>
		</div>
		<hr>
		<h3>LQIP Lazyload Fade-In (requires JS)</h3>
		<div class="row">
			<div class="col-6">
				<?= \Config\Services::bootstrap()
					->dynamicImage($file, "kitties!")
					->debug($debug)
					->cols('col-6')
					->ratio(1, TRUE, ["class" => "dyn_fadebox"]) // add fadebox class for css transition & positioning
					->lazy(TRUE)
					->lqip(100, [], TRUE) // lqip must be a separate <img> element
					->render();
				?>
			</div>
			<div class="col-6">
				<?= \Config\Services::bootstrap()
					->dynamicImage($file, "kitties!")
					->debug($debug)
					->cols('col-6')
					->ratio(1, TRUE, ["class" => "dyn_fadebox"]) // add fadebox class for css transition & positioning
					->lazy(TRUE)
					->lqip('#FF0000', [], TRUE) // lqip must be a separate <img> element
					->render();
				?>
			</div>
		</div>
	</div><!-- container -->
</body>

</html>
<?php
$file = 'kitten-src.jpg';
$file2 = 'kitten-portrait-src.jpg';
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

	<!-- output needed styles for cropping/ratio padding -->
	<style type="text/css">
		<?= require ROOTPATH . "../src/styles.css" ?>
	</style>
</head>

<body class="pb-3">

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

	<div class="container">
		<h2>Static Image (uses static image assets kitten-260.jpg, kitten-520.jpg, etc)</h2>
		<picture>
			<?= \Config\Services::bootstrap()->staticImage()->renderSources([
				"prettyPrint" => TRUE,
				"imgAttr" => ["class" => "img-fluid"], // optional - creates the <img>
				"widths" => [2080, 1040, 520, 260],
				"file" => function ($width, $resolution) {
					return "kitten-$width.jpg";
				},
			]) ?>
		</picture>

		<h2>Dynamic Image... all images are perfectly sized correctly to their containers!</h2>
		<p>Full container width</p>
		<div class="row">
			<div class="col py-2">
				<?= \Config\Services::bootstrap()->dynamicImage([
					"file" => $file,
				]) ?>
			</div>
		</div>

		<p>Custom grid with max-height of 300px, while supporting retina</p>
		<div class="row">
			<div class="col py-2">
				<?= \Config\Services::bootstrap()->dynamicImage([
					"file" => $file,
					"grid" => [
						1200 => "1200,300",
						992 => "480,300",
						0 => "360,300",
					],
				]) ?>
			</div>
		</div>

		<p>Bootstrap cols with various LQIP (xs container, transparent pixel, solid color, 100px image)</p>
		<div class="row">
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-6 col-lg-3 py-2",
				"colWrapper" => TRUE,
				"lqip" => "xs", // the image width at xs container (default)
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-6 col-lg-3 py-2",
				"colWrapper" => TRUE,
				"lqip" => "pixel", // transparent pixel
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-6 col-lg-3 py-2",
				"colWrapper" => TRUE,
				"lqip" => "#000000", // solid color
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-6 col-lg-3 py-2",
				"colWrapper" => TRUE,
				"lqip" => 100, // 100px image
			]) ?>
		</div>

		<p>Ratio padding (original ratio, 16:2 crop, 1:1 crop)</p>
		<div class="row">
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> TRUE, // natural ratio
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> "16/5",
				"ratioCrop" => TRUE,
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> 1, // crop to square
				"ratioCrop" => TRUE,
			]) ?>
		</div>

		<p>Also works with portrait orientation (original ratio, 16:2 crop, 1:1 crop)</p>
		<div class="row">
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file2,
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> TRUE, // natural ratio
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file2,
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> "6/2", // crop to 16:2
				"ratioCrop" => TRUE,
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file2,
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> 1, // crop to square
				"ratioCrop" => TRUE,
			]) ?>
		</div>

		<p>LQIP + Ratio + Lazyload (xs col, solid color, 100px image).</p>
		<div class="row">
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"query" => ["q" => rand()], // prevent caching
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> 1,
				"ratioCrop" => TRUE,
				"loading" => "lazy",
				"lqip" => "xs", // the image width at xs container (default)
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"query" => ["q" => rand()], // prevent caching
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> 1,
				"ratioCrop" => TRUE,
				"loading" => "lazy",
				"lqip" => "#FF0000", // solid color
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"query" => ["q" => rand()], // prevent caching
				"colClasses" => "col-md-4 py-2",
				"colWrapper" => TRUE,
				"ratio"	=> 1,
				"ratioCrop" => TRUE,
				"loading" => "lazy",
				"lqip" => 100, // 100px image
			]) ?>
		</div>

		<p>Hard limits on width and/or height (hiresX: 600px, hiresY: 300px)</p>
		<div class="row">
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-6 py-2",
				"colWrapper" => TRUE,
				"hiresX" => 600,
			]) ?>
			<?= \Config\Services::bootstrap()->dynamicImage([
				"file" => $file,
				"colClasses" => "col-md-6 py-2",
				"colWrapper" => TRUE,
				"hiresY" => 300,
			]) ?>
		</div>

		<p>Max-height on col (containerMaxHeight: 200px)</p>
		<div class="row">
			<div class="col-md-6 py-2" style="max-height: 350px; overflow:hidden;">
				<?= \Config\Services::bootstrap()->dynamicImage([
					"file" => $file,
					"colClasses" => "col-md-6 py-2",
					"containerMaxHeight" => 200,
				]) ?>
			</div>
		</div>
	</div>
</body>

</html>
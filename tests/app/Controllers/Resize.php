<?php

namespace App\Controllers;

class Resize extends BaseController
{
	public function index()
	{
		$file = $this->request->getGet('f');
		$width = $this->request->getGet('w');
		$img = service('image')
			->withFile($file)
			->resize($width, $width, TRUE, 'width');
		$resource = $img->getResource();
		// write the image size
		$bg = imagecolorallocate($resource, 255, 255, 255);
		$textcolor = imagecolorallocate($resource, 0, 0, 0);
		$string = $img->getWidth() . 'x' . $img->getHeight();
		$font  = 5;
		$fWidth = imagefontwidth($font) * strlen($string);
		$fHeight = imagefontheight($font);
		imagestring($resource, $font, floor(($img->getWidth() / 2) - ($fWidth / 2)), floor(($img->getHeight() / 2) - ($fHeight / 2)), $string, $textcolor);
		// remove any CI output buffering
		ob_end_flush();
		// send the image
		header('Content-Type: image/jpeg');
		imagejpeg($resource, NULL, 75);
		imagedestroy($resource);
		exit;
	}
}

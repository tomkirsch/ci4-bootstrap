<?php

namespace App\Controllers;

class Resize extends BaseController
{
	/**
	 * Resizes an image server-side. For more functionality, see the ci4 Resizer library on git.
	 */
	public function getIndex()
	{
		$file = $this->request->getGet('f');
		$width = $this->request->getGet('w');
		$img = service('image')
			->withFile($file)
			->resize($width, $width, TRUE, 'width');
		$resource = $img->getResource();
		// write the image size
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
		exit;
	}
}

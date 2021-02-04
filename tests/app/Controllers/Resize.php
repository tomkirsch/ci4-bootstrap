<?php namespace App\Controllers;

class Resize extends BaseController{
	public function index(){
		$file = $this->request->getGet('f');
		$width = $this->request->getGet('w');
		$resource = service('image')
			->withFile($file)
			->resize($width, $width, TRUE, 'width')
			->getResource()
		;
		$bg = imagecolorallocate($resource, 255, 255, 255);
		$textcolor = imagecolorallocate($resource, 0, 0, 0);
		imagestring($resource, 5, 0, 0, $width.'px', $textcolor);
		ob_end_flush();
		header('Content-Type: image/jpeg');
		imagejpeg($resource, NULL, 75);
		imagedestroy($resource);
		exit;
	}
}

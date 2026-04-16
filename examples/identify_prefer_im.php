<?php
/**
* Test script for MagickLite class.
*
* @author   Craig Manley
* @package  cmanley
*/
require_once(__DIR__ . '/../vendor/autoload.php');
use CraigManley\MagickLite;

$file = $argv[1] ?? __DIR__ . '/sample.avif';


$m = new MagickLite(new \SplFileInfo($file), [
	'debug'		=> true,
	'prefer'	=> 'im',	// Prefer ImageMagick over GraphicsMagick.
]);

// Get image info
$width; $height; $magic;
$m->identify($width, $height, $magic);
print "width=$width, height=$height, magic=$magic\n";

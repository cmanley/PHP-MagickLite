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
	'debug'	=> true,
]);


// Create a thumbnail
$m->convert(
	[
		'-resize', '100x100>',
		'-quality', 80,
		'+profile', '*',	// removes any ICM, EXIF, IPTC profiles that may be present
	]
	, 'thumbnail.jpg'
);

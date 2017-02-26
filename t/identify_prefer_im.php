<?php
/**
* Test script for MagickLite class.
*
* @author   Craig Manley
* @version  $Id: identify_prefer_im.php,v 1.1 2017/02/26 15:25:52 cmanley Exp $
* @package  cmanley
*/
require_once(__DIR__ . '/../lib/MagickLite.class.php');

// CLI options
if ($argc != 2) {
	error_log('You must specify an image file name as argument!');
	exit(1);
}
$file = $argv[1];


$m = new MagickLite($file, array(
	'debug'		=> true,
	'prefer'	=> 'im',	// Prefer ImageMagick over GraphicsMagick.
));

// Get image info
$width; $height; $magic;
$m->identify($width, $height, $magic);
print "width=$width, height=$height, magic=$magic\n";

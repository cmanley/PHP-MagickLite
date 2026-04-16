<?php
/**
* Test script for MagickLite class.
*
* @author   Craig Manley
* @version  $Id: identify_prefer_gm.php,v 1.2 2026/04/16 14:36:54 cmanley Exp $
* @package  cmanley
*/
require_once(__DIR__ . '/../lib/MagickLite.class.php');

// CLI options
if ($argc != 2) {
	error_log('You must specify an image file name as argument!');
	exit(1);
}
$file = $argv[1];


$m = new MagickLite($file, [
	'debug'		=> true,
	'prefer'	=> 'gm',	// Enable this if you prefer GraphicsMagick (the default choice anyway).
]);

// Get image info
$width; $height; $magic;
$m->identify($width, $height, $magic);
print "width=$width, height=$height, magic=$magic\n";

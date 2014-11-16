<?php
/**
* Test script for MagickLite class.
*
* @author   Craig Manley
* @version  $Id: identify.php,v 1.1 2014/11/16 02:08:17 cmanley Exp $
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
	'debug'	=> true,
	//'prefer'	=> 'im',	// Enable this if you prefer ImageMagick over GraphicsMagick. Detection of installed CLI is automatic, so this is not required.
));

// Get image info
$width; $height; $magic;
$m->identify($width, $height, $magic);
print "width=$width, height=$height, magic=$magic\n";

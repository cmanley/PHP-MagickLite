<?php
/**
* Test script for MagickLite class.
*
* @author   Craig Manley
* @version  $Id: thumbnail.php,v 1.1 2014/11/16 02:08:17 cmanley Exp $
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
));


// Create a thumbnail
$m->convert(
	array(
		'-resize', '100x100>',
		'-quality', 80,
		'-filter',	'Sinc',
		'-blur',	1,
		'+profile', '*',	// removes any ICM, EXIF, IPTC profiles that may be present
	)
	, 'thumbnail.jpg'
);

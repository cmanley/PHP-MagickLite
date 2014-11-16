<?php
/**
* Test script for MagickLite class.
*
* @author   Craig Manley
* @version  $Id: watermark.php,v 1.2 2014/11/16 22:11:01 cmanley Exp $
* @package  cmanley
*/
require_once(__DIR__ . '/../lib/MagickLite.class.php');

// CLI options
if ($argc != 3) {
	error_log('You must specify a source image file name and watermark file name as argument!');
	exit(1);
}
$file = $argv[1];
$watermark = $argv[2];


$m = new MagickLite($file, array(
	'debug'	=> true,
));


$m->composite(
	array(
		'-dissolve', 50,
		'-gravity', 'southeast',
		'-geometry', '+10+10',
	)
	, $watermark
	, 'output_with_watermark.jpg'
);

<?php
/**
* Test script for MagickLite class.
*
* @author   Craig Manley
* @version  $Id: watermark.php,v 1.3 2026/04/16 14:36:54 cmanley Exp $
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


$m = new MagickLite($file, [
	'debug'	=> true,
]);


$m->composite(
	[
		'-dissolve', 50,
		'-gravity', 'southeast',
		'-geometry', '+10+10',
	]
	, $watermark
	, 'output_with_watermark.jpg'
);

<?php
/**
* Test script for MagickLite class.
*
* @author   Craig Manley
* @version  $Id: chaining.php,v 1.1 2014/11/16 02:08:17 cmanley Exp $
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


// Method chaining: Shrink image to fit and convert to GIF, then identify, then get raw data.
$rawdata =
	$m->convert(
		array(
			'-resize', '100x100',
		),
		null,	// no file, store data internally
		'GIF'	// magic
	)
	->identify($width, $height, $magic)
	->data();
print "New width=$width, height=$height, magic=$magic\n"; // Magic should be GIF now
file_put_contents('output.gif', $rawdata);

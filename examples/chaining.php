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


// Method chaining: Shrink image to fit and convert to GIF, then identify, then get raw data.
$rawdata =
	$m->convert(
		[
			'-resize', '100x100',
		],
		null,	// no file, store data internally
		'GIF'	// magic
	)
	->identify($width, $height, $magic)
	->data();
print "New width=$width, height=$height, magic=$magic\n"; // Magic should be GIF now
file_put_contents('output.gif', $rawdata);

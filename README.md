MagickLite
==========

The MagickLite class is a lightweight wrapper for common GraphicsMagick OR ImageMagick CLI commands.
Method chaining is supported.

### Requirements:
*  PHP 5.3.0 or newer

### Usage:
All the classes contain PHP-doc documentation, so for now, take a look at the code of MagicLite.class.php or one of the test/example scripts in the t subdirectory.

**Example:**

		<?php
		require_once('lib/MagickLite.class.php');

		$file = $argv[1];

		$m = new MagickLite($file, array(
			//'debug'	=> true,
			//'prefer'	=> 'im',	// Enable this if you prefer ImageMagick over GraphicsMagick. Detection of installed CLI is automatic, so this is not required.
		));

		// Get image info
		$width; $height; $magic;
		$m->identify($width, $height, $magic);
		print "width=$width, height=$height, magic=$magic\n";

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

		// Place a transparent watermark over the botton-right corner
		$m->composite(
			array(
				'-dissolve', 50,
				'-gravity', 'southeast',
				'-geometry', '+10+10',
				'-resize', '800x800>',
				'-quality', 75,
			)
			, 'watermark.png'
			, 'output.jpg'
		);

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
		print "width=$width, height=$height, magic=$magic\n"; // Magic should be GIF now
		file_put_contents('output.gif', $rawdata);


### Licensing
All of the code in this library is licensed under the MIT license as included in the LICENSE file

MagickLite
==========

The MagickLite class is a lightweight wrapper for common GraphicsMagick OR ImageMagick CLI commands.
Method chaining is supported.

### Requirements:
*  PHP 5.3.0 or newer
*  Either the GraphicsMagick CLI, or the ImageMagick CLI, or both.

### Usage:
All the classes contain PHP-doc documentation, so for now, take a look at the code of MagicLite.class.php or one of the test/example scripts in the t subdirectory.
All options can be find in the GraphicsMagick documentation here: http://www.graphicsmagick.org/GraphicsMagick.html

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
				'-quality', 80,		// alternative: '-define', 'jpeg:preserve-settings',
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
			)
			, 'watermark.png'
			, 'output.jpg'
		);

		// Method chaining: Make image fit in 300x300 box and strip all profiles, then apply watermark and save as output.jpg
		$m->convert(
			array(
				'-resize', '300x300>',
				'+profile', '*',	// removes any ICM, EXIF, IPTC profiles that may be present
			),
			null,	// no file means store data internally
			'PNG'	// for lossless resize
		)
		->composite(
			array(
				'-dissolve', 50,
				'-gravity', 'southeast',
				'-geometry', '+10+10',
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

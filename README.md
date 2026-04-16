MagickLite
==========

The `MagickLite` class is a lightweight wrapper for common GraphicsMagick OR ImageMagick CLI commands.
Method chaining is supported.

### Requirements:
* PHP 8.2 or newer
* Either the GraphicsMagick CLI, or the ImageMagick CLI, or both.

### Installation:
```
composer require cmanley/magicklite
```

### Usage:
All classes contain PHP-doc documentation. See also the scripts in the `examples/` directory.
All options can be found in the GraphicsMagick documentation here: http://www.graphicsmagick.org/GraphicsMagick.html

**Example:**

```php
<?php declare(strict_types = 1);
require_once 'vendor/autoload.php';

use CraigManley\MagickLite;

$file = $argv[1] ?? 'examples/sample.avif';

$m = new MagickLite(new \SplFileInfo($file), [
    //'debug'  => true,
    //'prefer' => 'im',  // Prefer ImageMagick over GraphicsMagick. Detection is automatic, so this is optional.
]);

// Get image info
$m->identify($width, $height, $magic);
print "width=$width, height=$height, magic=$magic\n";

// Create a thumbnail
$m->convert(
    [
        '-resize', '100x100>',
        '-quality', 80,     // alternative: '-define', 'jpeg:preserve-settings',
        '+profile', '*',    // removes any ICM, EXIF, IPTC profiles that may be present
    ],
    'thumbnail.jpg'
);

// Place a transparent watermark over the bottom-right corner
$m->composite(
    [
        '-dissolve', 50,
        '-gravity', 'southeast',
        '-geometry', '+10+10',
    ],
    'watermark.png',
    'output.jpg'
);

// Method chaining: make image fit in 300x300 box and strip all profiles, then apply watermark and save as output.jpg
$m->convert(
    [
        '-resize', '300x300>',
        '+profile', '*',    // removes any ICM, EXIF, IPTC profiles that may be present
    ],
    null,   // no file means store data internally
    'PNG'   // for lossless resize
)
->composite(
    [
        '-dissolve', 50,
        '-gravity', 'southeast',
        '-geometry', '+10+10',
    ],
    'watermark.png',
    'output.jpg'
);

// Method chaining: shrink image to fit and convert to GIF, then identify, then get raw data.
$rawdata =
    $m->convert(
        ['-resize', '100x100'],
        null,   // no file, store data internally
        'GIF'   // output magic
    )
    ->identify($width, $height, $magic)
    ->data();
print "width=$width, height=$height, magic=$magic\n"; // magic should be GIF now
file_put_contents('output.gif', $rawdata);
```

### Licensing
All of the code in this library is licensed under the MIT license as included in the LICENSE file

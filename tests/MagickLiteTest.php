<?php declare(strict_types = 1);
namespace CraigManley;

use CraigManley\MagickLite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;


/**
 * Testable subclass that exposes protected static methods.
 */
class MagickLiteTestable extends MagickLite {

	public static function detectInputMagic(string $header): ?string {
		return static::_detect_input_magic($header);
	}

	// Expose CLI-check cache so tests can inject values.
	public static function setFoundGm(?bool $value): void {
		static::$found_gm = $value;
	}

	public static function setFoundIm(?bool $value): void {
		static::$found_im = $value;
	}

}


#[CoversClass(MagickLite::class)]
class MagickLiteTest extends TestCase {

	protected function setUp(): void {
		// Reset CLI-detection cache between tests.
		MagickLiteTestable::setFoundGm(null);
		MagickLiteTestable::setFoundIm(null);
	}


	// --- Class existence ---

	public function testClassExists(): void {
		$this->assertTrue(class_exists(MagickLite::class));
	}


	// --- Constructor validation ---

	public function testConstructorThrowsOnEmptyString(): void {
		$this->expectException(\InvalidArgumentException::class);
		new MagickLite('');
	}

	public function testConstructorThrowsOnInvalidPreferOptionViaSplFileInfo(): void {
		MagickLiteTestable::setFoundGm(true);
		$tmpfile = tempnam(sys_get_temp_dir(), 'magicklite_test_');
		file_put_contents($tmpfile, 'dummy');
		try {
			$this->expectException(\InvalidArgumentException::class);
			new MagickLiteTestable(new \SplFileInfo($tmpfile), ['prefer' => 'invalid']);
		} finally {
			unlink($tmpfile);
		}
	}

	public function testConstructorThrowsOnMissingFile(): void {
		$this->expectException(\InvalidArgumentException::class);
		new MagickLite(new \SplFileInfo('/nonexistent/path/to/image.jpg'));
	}

	public function testConstructorThrowsOnInvalidPreferOption(): void {
		// Ensure at least one CLI is "found" so we don't fail on the CLI check first.
		MagickLiteTestable::setFoundGm(true);
		$this->expectException(\InvalidArgumentException::class);
		// Pass a real file so the file check passes.
		$tmpfile = tempnam(sys_get_temp_dir(), 'magicklite_test_');
		file_put_contents($tmpfile, 'dummy');
		try {
			new MagickLiteTestable(new \SplFileInfo($tmpfile), ['prefer' => 'invalid']);
		} finally {
			unlink($tmpfile);
		}
	}

	public function testConstructorThrowsWhenNoCLIFound(): void {
		MagickLiteTestable::setFoundGm(false);
		MagickLiteTestable::setFoundIm(false);
		$tmpfile = tempnam(sys_get_temp_dir(), 'magicklite_test_');
		file_put_contents($tmpfile, 'dummy');
		try {
			$this->expectException(\RuntimeException::class);
			new MagickLiteTestable(new \SplFileInfo($tmpfile));
		} finally {
			unlink($tmpfile);
		}
	}


	// --- _detect_input_magic ---

	/**
	 * @return array<string, array{string, string|null}>
	 */
	public static function detectInputMagicProvider(): array {
		$avifBrands = ['avif', 'avis', 'mif1', 'msf1'];
		$cases = [];
		foreach ($avifBrands as $brand) {
			$header = str_repeat("\x00", 4) . 'ftyp' . $brand;
			$cases["brand=$brand"] = [$header, 'AVIF'];
		}
		// Not enough bytes → null
		$cases['too short'] = ["\x00\x00\x00\x00ftyp", null];
		// JPEG magic → null (no magic needed)
		$cases['JPEG'] = ["\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01", null];
		// Wrong ftyp brand → null
		$cases['non-avif ftyp brand'] = [str_repeat("\x00", 4) . 'ftyp' . 'mp42', null];
		return $cases;
	}

	#[DataProvider('detectInputMagicProvider')]
	public function testDetectInputMagic(string $header, ?string $expected): void {
		$this->assertSame($expected, MagickLiteTestable::detectInputMagic($header));
	}


	// --- getFile / data ---

	public function testGetFileReturnsNullForDataType(): void {
		MagickLiteTestable::setFoundGm(true);
		$obj = new MagickLiteTestable('fakeimagedata');
		$this->assertNull($obj->getFile());
	}

	public function testGetFileReturnsPathForFileType(): void {
		MagickLiteTestable::setFoundGm(true);
		$tmpfile = tempnam(sys_get_temp_dir(), 'magicklite_test_');
		file_put_contents($tmpfile, 'dummy');
		try {
			$obj = new MagickLiteTestable(new \SplFileInfo($tmpfile));
			$this->assertSame($tmpfile, $obj->getFile());
		} finally {
			unlink($tmpfile);
		}
	}

	public function testDataReturnsContentsForDataType(): void {
		MagickLiteTestable::setFoundGm(true);
		$obj = new MagickLiteTestable('fakeimagedata');
		$this->assertSame('fakeimagedata', $obj->data());
	}

	public function testDataReturnsFileContentsForFileType(): void {
		MagickLiteTestable::setFoundGm(true);
		$tmpfile = tempnam(sys_get_temp_dir(), 'magicklite_test_');
		file_put_contents($tmpfile, 'dummycontent');
		try {
			$obj = new MagickLiteTestable(new \SplFileInfo($tmpfile));
			$this->assertSame('dummycontent', $obj->data());
		} finally {
			unlink($tmpfile);
		}
	}

}

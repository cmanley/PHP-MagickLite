<?php declare(strict_types=1);
/**
* Contains the MagickLite class.
*
* Dependencies:
* <pre>
* GraphicsMagick OR ImageMagick CLI commands.
* Alpine packages: graphicsmagick
* Debian packages: graphicsmagick
* </pre>
*
* @author    Craig Manley
* @copyright Copyright © 2014, Craig Manley (craigmanley.com). All rights reserved.
* @version   $Id: MagickLite.class.php,v 1.9 2026/04/16 14:36:54 cmanley Exp $
* @package   cmanley
*/




/**
* MagickLite class.
* Lightweight wrapper class for common GraphicsMagick/ImageMagick CLI commands.
* Method chaining is supported.
*
* @package	cmanley
*/
class MagickLite {

	protected static ?bool $found_gm = null; // cached result of _check_exists_gm()
	protected static ?bool $found_im = null; // cached result of _check_exists_im()

	protected ?string $file = null;
	protected ?string $data = null;
	protected bool $debug = false;
	protected ?array $identify_cache = null;
	protected ?bool $use_gm = null;
	protected ?string $input_magic = null; // format hint prefix (e.g. 'AVIF') when filename has no recognisable extension

	/**
	* Constructor.
	*
	* Supported options:
	*	debug	- boolean
	*	prefer	- one of 'gm' (default) or 'im', to indicate your preference for GraphicsMagick or ImageMagick.
	*	type	- 1st argument refers to a 'file' (default) or 'data'.
	*
	* @param string $file_or_data
	* @param array|null $options
	*/
	public function __construct(string $file_or_data, ?array $options = null) {
		if (!strlen($file_or_data)) {
			throw new InvalidArgumentException('Invalid or missing file or data in first argument.');
		}
		$type = $options['type'] ?? 'file';
		if (!preg_match('/^(?:file|data)$/', $type)) {
			throw new InvalidArgumentException('Invalid "type" option (must be one of "file" or "data").');
		}
		if ($type === 'file') {
			if (!file_exists($file_or_data)) {
				throw new InvalidArgumentException('File not found: ' . (strlen($file_or_data) > 255 ? '(too much data, probably not a file)' : $file_or_data));
			}
			$this->file = $file_or_data;
			$this->input_magic = static::_detect_input_magic(file_get_contents($file_or_data, false, null, 0, 12) ?: '');
		}
		else {
			$this->data = $file_or_data;
			$this->input_magic = static::_detect_input_magic(substr($file_or_data, 0, 12));
		}

		$prefer = 'gm';

		if ($options) {
			$this->debug = (bool) ($options['debug'] ?? false);
			if (isset($options['prefer'])) {
				$prefer = $options['prefer'];
				if (!preg_match('/^(?:gm|im)$/', $prefer)) {
					throw new InvalidArgumentException('Invalid "prefer" option (must be one of "gm" or "im").');
				}
			}
		}

		// Set preferred CLI, if available.
		if ($prefer === 'gm') {
			if (static::_check_exists_gm()) {
				$this->use_gm = true;
			}
			else {
				$this->debug && error_log(__METHOD__ . ' Preferred GraphicsMagick not found');
				if (static::_check_exists_im()) {
					$this->use_gm = false;
				}
			}
		}
		else {
			if (static::_check_exists_im()) {
				$this->use_gm = false;
			}
			else {
				$this->debug && error_log(__METHOD__ . ' Preferred ImageMagick not found');
				if (static::_check_exists_gm()) {
					$this->use_gm = true;
				}
			}
		}
		if ($this->use_gm === null) {
			throw new Exception("Unable to locate 'gm' or 'identify' CLI commands");
		}
		$this->debug && error_log(__METHOD__ . ' Use ' . ($this->use_gm ? 'GraphicsMagick' : 'ImageMagick'));
	}


	/**
	* Checks if the GraphicsMagick CLI exists.
	*
	* @return bool
	*/
	protected static function _check_exists_gm(): bool {
		if (static::$found_gm === null) {
			$out = null;
			$rc = null;
			exec('gm -version >/dev/null 2>&1', $out, $rc);
			static::$found_gm = $rc === 0;
		}
		return static::$found_gm;
	}


	/**
	* Checks if the ImageMagick CLI exists.
	*
	* @return bool
	*/
	protected static function _check_exists_im(): bool {
		if (static::$found_im === null) {
			$out = null;
			$rc = null;
			exec('identify -version >/dev/null 2>&1', $out, $rc);
			static::$found_im = $rc === 0;
		}
		return static::$found_im;
	}


	/**
	* Detects the image format from the first bytes when it cannot be inferred from the filename.
	* Returns a format magic string (e.g. 'AVIF') to use as a CLI format prefix, or null if not needed.
	*
	* @param string $header First bytes of the image data (12 bytes is enough).
	* @return string|null
	*/
	protected static function _detect_input_magic(string $header): ?string {
		if (strlen($header) >= 12) {
			// AVIF/HEIF ISO Base Media File: bytes 4-7 = 'ftyp', bytes 8-11 = brand
			if (substr($header, 4, 4) === 'ftyp' && in_array(substr($header, 8, 4), ['avif', 'avis', 'mif1', 'msf1'])) {
				return 'AVIF';
			}
		}
		return null;
	}


	/**
	* Executes the given command with the given arguments.
	*
	* @param string $cmd Command name (don't escape).
	* @param array $args Array of arguments, if any (don't escape).
	* @param string|null $stdin Piped into the process.
	* @param string|null &$stdout Receives STDOUT.
	* @param string|null &$stderr Receives STDERR.
	* @return void
	*/
	protected function _proc_exec(string $cmd, array $args, ?string $stdin, ?string &$stdout, ?string &$stderr): void {
		$shell_cmd = '(' . escapeshellcmd($cmd) . ' ' . implode(' ', array_map(fn($arg) => escapeshellarg((string) $arg), $args)) . ') 3>/dev/null; echo $? >&3'; // unreliable proc_close exitcode workaround
		$this->debug && error_log(__METHOD__ . " $shell_cmd");
		$descriptors = [
			0 => ['pipe', 'r'],	// stdin is a pipe that the child will read from
			1 => ['pipe', 'w'],	// stdout is a pipe that the child will write to
			2 => ['pipe', 'w'],	// stderr is a pipe that the child will write to
			3 => ['pipe', 'w'],	// unreliable proc_close exitcode workaround
		];
		$process = proc_open($shell_cmd, $descriptors, $pipes);
		if (!is_resource($process)) {
			throw new Exception("Failed to open process '$shell_cmd'.\n");
		}
		// $pipes now looks like this:
		// 0 => writeable handle connected to child stdin
		// 1 => readable handle connected to child stdout
		// 2 => readable handle connected to child stderr
		// 3 => readable handle connected to child exitcode
		if ($stdin) {
			fwrite($pipes[0], $stdin);
		}
		fclose($pipes[0]);

		// It is important that you close any pipes before calling proc_close in order to avoid a deadlock.
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);
		$exitcode = stream_get_contents($pipes[3]);
		fclose($pipes[3]);

		$status = proc_get_status($process);
		$rc = proc_close($process); // will return -1 if PHP was compiled with --enable-sigchild
		if ($status !== false && !$status['running']) {
			$rc = $status['exitcode'];
		}
		if (($rc === -1) && preg_match('/^(-?\d+)\s*$/', $exitcode, $matches)) { // unreliable proc_close exitcode workaround
			$rc = (int) $matches[1];
		}
		if ($rc !== 0) {  // 0 is success
			$e = "Bad exit code $rc from command '$shell_cmd'";
			$e .= ($stderr !== null && strlen($stderr)) ? " and this error output: $stderr" : '.';
			throw new Exception($e);
		}
	}


	/**
	* Composite images together.
	* See also: http://www.graphicsmagick.org/composite.html
	* Example placing a watermark over an image:
	* <pre>
	*	$m = new MagickLite('input.jpg');
	*	$m->composite(
	*		array(
	*			'-dissolve', 30,
	*			'-gravity', 'southeast',
	*			'-geometry', '+10+10',
	*		)
	*		, 'watermark.png'
	*		, 'out.jpg'
	*	);
	* </pre>
	*
	* @param array $options As supported by the composite CLI command (don't escape).
	* @param string $change_image The file containing the changes (typically a watermark).
	* @param string|null $output_file Optional. If not given, the result is stored internally.
	* @param string|null $output_magic Optional magic of output if it cannot be deduced from the file name.
	* @return static
	*/
	public function composite(array $options, string $change_image, ?string $output_file = null, ?string $output_magic = null): static {
		if (!file_exists($change_image)) {
			throw new InvalidArgumentException("Change image file not found: '$change_image'");
		}

		$cmd = $this->use_gm ? 'gm' : 'composite';
		$args = $this->use_gm ? ['composite'] : [];
		$args = array_merge($args, $options);
		$args []= $change_image;
		if ($this->file) {
			$args []= $this->input_magic ? $this->input_magic . ':' . $this->file : $this->file;
		}
		else {
			#$args []= $this->use_gm
			#	? '-'      // GraphicsMagick can't parse frame number from arguments: https://sourceforge.net/tracker/?func=detail&aid=3385967&group_id=73485&atid=537937
			#	: '-[0]';
			$args []= '-[0]';
		}
		if ($output_file && ($output_file !== '-')) {
			$args []= $output_magic ? $output_magic . ':' . $output_file : $output_file;
		}
		else {
			$args[] = $output_magic ? $output_magic . ':-' : '-';
		}

		$stdout = null;
		$stderr = null;
		$this->_proc_exec($cmd, $args, $this->data, $stdout, $stderr);

		if ($output_file === null || ($output_file === '-')) {
			$this->identify_cache = null;
			$this->data = $stdout;
			$this->file = null;
		}

		return $this;
	}


	/**
	* Converts the image.
	* See also: http://www.graphicsmagick.org/convert.html
	* Example to shrink an image to fit within the given dimensions
	* <pre>
	*	$m = new MagickLite('input.jpg');
	*	$m->convert(
	*		[
	*			'-resize', '100x100>',
	*			'-quality', 90,
	*			'+profile', '*',		// removes any ICM, EXIF, IPTC profiles that may be present
	*		]
	*		, 'out.jpg'
	*	);
	* </pre>
	*
	* @param array $options As supported by the convert CLI command (don't escape).
	* @param string|null $output_file Optional. If not given, the result is stored internally.
	* @param string|null $output_magic Optional magic of output if it cannot be deduced from the file name.
	* @return static
	*/
	public function convert(array $options, ?string $output_file = null, ?string $output_magic = null): static {
		$cmd = $this->use_gm ? 'gm' : 'convert';
		$args = $this->use_gm ? ['convert'] : [];
		if ($this->file) {
			$args[] = $this->input_magic ? $this->input_magic . ':' . $this->file : $this->file;
		}
		else {
			#$args []= $this->use_gm
			#	? '-'      // GraphicsMagick can't parse frame number from arguments: https://sourceforge.net/tracker/?func=detail&aid=3385967&group_id=73485&atid=537937
			#	: '-[0]';
			$args []= '-[0]';
		}
		$args = array_merge($args, $options);
		if ($output_file && ($output_file !== '-')) {
			$args[] = $output_magic ? $output_magic . ':' . $output_file : $output_file;
		}
		else {
			$args[] = $output_magic ? $output_magic . ':-' : '-';
		}

		$stdout = null;
		$stderr = null;
		$this->_proc_exec($cmd, $args, $this->data, $stdout, $stderr);

		if ($output_file === null || ($output_file === '-')) {
			$this->identify_cache = null;
			$this->data = $stdout;
			$this->file = null;
		}

		return $this;
	}


	/**
	* Identifies the image.
	* See also: http://www.graphicsmagick.org/identify.html
	*
	* @param int|null &$width Optional reference that receives the width.
	* @param int|null &$height Optional reference that receives the height.
	* @param string|null &$magic Optional reference that receives the magic.
	* @return static
	*/
	public function identify(?int &$width = null, ?int &$height = null, ?string &$magic = null): static {
		if ($this->identify_cache) {
			$width  = $this->identify_cache['width'];
			$height = $this->identify_cache['height'];
			$magic  = $this->identify_cache['magic'];
		}
		else {
			$cmd = $this->use_gm ? 'gm' : 'identify';
			$args = $this->use_gm ? ['identify'] : [];
			$args[] = '-format';
			$args[] = '%w %h %m';	// http://www.graphicsmagick.org/GraphicsMagick.html#details-format
			if ($this->file) {
				$args[] = $this->input_magic ? $this->input_magic . ':' . $this->file : $this->file;
			}
			else {
				#$args []= $this->use_gm
				#	? '-'      // GraphicsMagick can't parse frame number from arguments: https://sourceforge.net/tracker/?func=detail&aid=3385967&group_id=73485&atid=537937
				#	: '-[0]';
				$args []= '-[0]';
			}

			$stdout = null;
			$stderr = null;
			$this->_proc_exec($cmd, $args, $this->data, $stdout, $stderr);

			// Parse STDOUT.
			if (!preg_match($this->use_gm ? '/^(\d{1,5})(?: \d+)* (\d{1,5}) (\b.+\b)$/' : '/^(\d{1,5}) (\d{1,5}) (\b.+\b)$/', $stdout, $matches)) {
				throw new Exception("Failed to parse output (\"$stdout\") of command \"$cmd\"; stderr=$stderr");
			}
			$width  = (int) $matches[1];
			$height = (int) $matches[2];
			$magic  = $matches[3];
			$this->identify_cache = [
				'width'  => $width,
				'height' => $height,
				'magic'  => $magic,
			];
		}
		return $this;
	}


	/**
	* Returns the internal image data if any.
	* Not chainable.
	*
	* @return string|null
	*/
	public function data(): ?string {
		return $this->data ?? ($this->file ? file_get_contents($this->file) : null);
	}


	/**
	* Returns the file this object operates on.
	* Not chainable.
	*
	* @return string|null
	*/
	public function getFile(): ?string {
		return $this->file;
	}

}

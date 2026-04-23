<?php declare(strict_types = 1);
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
 * @copyright Copyright © 2014-2026, Craig Manley (craigmanley.com). All rights reserved.
 * @package   CraigManley
 */
namespace CraigManley;



/**
 * MagickLite class.
 * Lightweight wrapper class for common GraphicsMagick/ImageMagick CLI commands.
 * Method chaining is supported.
 *
 * @package CraigManley
 */
class MagickLite {

	protected static ?bool $found_gm = null; // cached result of _check_exists_gm()
	protected static ?bool $found_im = null; // cached result of _check_exists_im()

	protected ?string $file = null;
	protected ?string $data = null;
	protected bool $debug = false;
	protected ?array $identify_cache = null;
	protected ?bool $use_gm = null;
	protected ?string $input_magic = null; // format hint prefix (e.g. 'AVIF') when filename has no recognisable extension; so far it only seems necessary for animated AVIF images.

	/**
	 * Constructor.
	 *
	 * Supported options:
	 *	debug	- boolean
	 *	prefer	- one of 'gm' (default) or 'im', to indicate your preference for GraphicsMagick or ImageMagick.
	 *
	 * @param string|\SplFileInfo $file_or_data Pass a \SplFileInfo to operate on a file, or a string of raw image data.
	 * @param array|null          $options
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	public function __construct(string|\SplFileInfo $file_or_data, ?array $options = null) {
		if ($options) {
			$this->debug = (bool) ($options['debug'] ?? false);
			if (isset($options['prefer'])) {
				$prefer = $options['prefer'];
				$allowed_prefer_values = ['gm', 'im'];
				if (!in_array($prefer, $allowed_prefer_values)) {
					throw new \InvalidArgumentException("Invalid prefer option ($prefer) must be one of " . join(', ', $allowed_prefer_values));
				}
			}
		}

		if ($file_or_data instanceof \SplFileInfo) {
			$path = $file_or_data->getPathname();
			if (!file_exists($path)) {
				throw new \InvalidArgumentException("File ($path) not found");
			}
			$this->file = $path;
			$this->input_magic = static::_detect_input_magic(file_get_contents($path, false, null, 0, 12) ?: '');
			$this->debug && error_log(__METHOD__ . ' $file_or_data is a file (' . $path . ') with magic=' . ($this->input_magic ?? 'null'));
		}
		else {
			if (!strlen($file_or_data)) {
				throw new \InvalidArgumentException('The data argument must not be empty');
			}
			$this->data = $file_or_data;
			$this->input_magic = static::_detect_input_magic(substr($file_or_data, 0, 12));

			$this->debug && error_log(__METHOD__ . ' $file_or_data is ' . strlen($file_or_data) . ' byte string with magic=' . ($this->input_magic ?? 'null'));
		}

		// Set preferred executable, if available.
		$prefer = 'gm';
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
			throw new \RuntimeException("Neither one of the 'gm' or 'identify' executables are available");
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
			$rc  = null;
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
			$rc  = null;
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
	 * Throws on non-zero exit code.
	 *
	 * @param array       $command Command and arguments as unescaped strings, e.g. ['gm', 'convert', '-resize', '100x100', 'in.jpg', 'out.jpg'].
	 * @param string|null $stdin   Optional data to pipe into the process.
	 * @param string|null &$stdout Receives STDOUT.
	 * @param string|null &$stderr Receives STDERR.
	 * @return void
	 * @throws \RuntimeException
	 */
	protected function _proc_exec(array $command, ?string $stdin, ?string &$stdout, ?string &$stderr): void {
		$cmd = escapeshellcmd(array_shift($command));
		if ($command) {
			$cmd .= ' ' . implode(' ', array_map(fn($arg) => escapeshellarg((string) $arg), $command));
		}
		$this->debug && error_log(__METHOD__ . " $cmd");
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$process = proc_open($cmd, $descriptors, $pipes);
		if (!is_resource($process)) {
			throw new \RuntimeException("Failed to open process '$cmd'");
		}

		// Use a stream_select loop to write stdin and read stdout/stderr concurrently,
		// preventing pipe-buffer deadlocks and EPIPE errors for large payloads.
		$stdin_buf = ($stdin !== null && strlen($stdin)) ? $stdin : null;
		$stdin_buf_offset = 0;
		$stdin_pipe_open = ($stdin_buf !== null);
		if ($stdin_pipe_open) {
			stream_set_blocking($pipes[0], false);
		}
		else {
			fclose($pipes[0]);
		}

		$stdout = '';
		$stderr = '';
		$pipes_to_read = [1 => $pipes[1], 2 => $pipes[2]];
		while ($stdin_pipe_open || $pipes_to_read) {
			$readable = array_values($pipes_to_read);
			$writable = $stdin_pipe_open ? [$pipes[0]] : [];
			$except   = null;
			if (stream_select($readable, $writable, $except, null) === false) {
				throw new \RuntimeException("stream_select failed for command ($cmd)");
			}
			if ($writable) {
				$chunk = substr($stdin_buf, $stdin_buf_offset, 8192);
				error_clear_last();
				$n = @fwrite($pipes[0], $chunk);
				if ($n === false || $n === 0) {
					// EPIPE: child closed stdin early (likely errored). Exit code check below will catch it.
					fclose($pipes[0]);
					$stdin_pipe_open = false;
				}
				else {
					$stdin_buf_offset += $n;
					if ($stdin_buf_offset >= strlen($stdin_buf)) {
						fclose($pipes[0]);
						$stdin_pipe_open = false;
					}
				}
			}
			foreach ($readable as $pipe) {
				$key = array_search($pipe, $pipes_to_read);
				$chunk = fread($pipe, 8192);
				if ($chunk === false || $chunk === '') {
					fclose($pipe);
					unset($pipes[$key], $pipes_to_read[$key]);
				}
				elseif ($key === 1) {
					$stdout .= $chunk;
				}
				else {
					$stderr .= $chunk;
				}
			}
		}

		$status = proc_get_status($process);
		$rc = proc_close($process); // may return -1 if PHP was compiled with --enable-sigchild
		if (!$status['running']) { // use proc_get_status exitcode when process already finished
			$rc = $status['exitcode'];
		}
		if ($rc !== 0) {
			$e = "Error exit code $rc from command ($cmd) given " . strlen($stdin ?? '') . ' bytes of stdin';
			if (strlen($stderr)) {
				$e .= " with this stderr: $stderr";
			}
			throw new \RuntimeException($e);
		}
	}


	/**
	 * Composite images together.
	 * See also: http://www.graphicsmagick.org/composite.html
	 * Example placing a watermark over an image:
	 * <pre>
	 *	$m = new MagickLite('input.jpg');
	 *	$m->composite(
	 *		[
	 *			'-dissolve', 30,
	 *			'-gravity', 'southeast',
	 *			'-geometry', '+10+10',
	 *		]
	 *		, 'watermark.png'
	 *		, 'out.jpg'
	 *	);
	 * </pre>
	 *
	 * @param array       $options      As supported by the composite CLI command (don't escape).
	 * @param string      $change_image The file containing the changes (typically a watermark).
	 * @param string|null $output_file  Optional. If not given, the result is stored internally.
	 * @param string|null $output_magic Optional magic of output if it cannot be deduced from the file name.
	 * @return static
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	public function composite(array $options, string $change_image, ?string $output_file = null, ?string $output_magic = null): static {
		if (!file_exists($change_image)) {
			throw new \InvalidArgumentException("Change image file not found: '$change_image'");
		}

		$cmd = $this->use_gm ? ['gm', 'composite'] : ['composite'];
		$cmd = array_merge($cmd, $options);
		$cmd []= $change_image;

		$input_file_arg = $this->file ? $this->file : '-';
		if ($this->input_magic) {
			$input_file_arg = $this->input_magic . ':' . $input_file_arg;
		}
		$cmd []= $input_file_arg;

		$output_file_arg = $output_file && ($output_file !== '-') ? $output_file : '-';
		if ($output_magic) {
			$output_file_arg = $output_magic . ':' . $output_file_arg;
		}
		$cmd []= $output_file_arg;

		$stdout = null;
		$stderr = null;
		$this->_proc_exec($cmd, $this->data, $stdout, $stderr);

		if ($output_file === null || ($output_file === '-')) {
			$this->identify_cache = null;
			if ($output_magic) {
				$this->input_magic = $output_magic;
			}
			$this->data = $stdout;
			$this->file = null;
		}

		return $this;
	}


	/**
	 * Converts the image.
	 * See also: http://www.graphicsmagick.org/convert.html
	 * Example to shrink an image to fit within the given dimensions:
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
	 * @param array       $options      As supported by the convert CLI command (don't escape).
	 * @param string|null $output_file  Optional. If not given, the result is stored internally.
	 * @param string|null $output_magic Optional magic of output if it cannot be deduced from the file name.
	 * @return static
	 * @throws \RuntimeException
	 */
	public function convert(array $options, ?string $output_file = null, ?string $output_magic = null): static {
		$cmd = $this->use_gm ? ['gm', 'convert'] : ['convert'];

		$input_file_arg = $this->file ? $this->file : '-';
		if ($this->input_magic) {
			$input_file_arg = $this->input_magic . ':' . $input_file_arg;
		}
		$cmd []= $input_file_arg;

		$cmd = array_merge($cmd, $options);
		if ($output_file && ($output_file !== '-')) {
			$cmd []= $output_magic ? $output_magic . ':' . $output_file : $output_file;
		}
		else {
			$cmd []= $output_magic ? $output_magic . ':-' : '-';
		}

		$stdout = null;
		$stderr = null;
		$this->_proc_exec($cmd, $this->data, $stdout, $stderr);

		if ($output_file === null || ($output_file === '-')) {
			$this->identify_cache = null;
			if ($output_magic) {
				$this->input_magic = $output_magic;
			}
			$this->data = $stdout;
			$this->file = null;
		}

		return $this;
	}


	/**
	 * Identifies the image.
	 * See also: http://www.graphicsmagick.org/identify.html
	 *
	 * @param int|null    &$width  Optional reference that receives the width.
	 * @param int|null    &$height Optional reference that receives the height.
	 * @param string|null &$magic  Optional reference that receives the magic.
	 * @return static
	 * @throws \RuntimeException
	 */
	public function identify(?int &$width = null, ?int &$height = null, ?string &$magic = null): static {
		if ($this->identify_cache) {
			$width  = $this->identify_cache['width'];
			$height = $this->identify_cache['height'];
			$magic  = $this->identify_cache['magic'];
		}
		else {
			$cmd  = $this->use_gm ? ['gm', 'identify'] : ['identify'];
			$cmd []= '-format';
			$cmd []= '%w %h %m';	// http://www.graphicsmagick.org/GraphicsMagick.html#details-format

			$input_file_arg = $this->file ? $this->file : '-';
			if ($this->input_magic) {
				$input_file_arg = $this->input_magic . ':' . $input_file_arg;
			}
			$cmd []= $input_file_arg;

			$stdout = null;
			$stderr = null;
			$this->_proc_exec($cmd, $this->data, $stdout, $stderr);

			// Parse STDOUT.
			if (!preg_match($this->use_gm ? '/^(\d{1,5})(?: \d+)* (\d{1,5}) (\b.+\b)$/' : '/^(\d{1,5}) (\d{1,5}) (\b.+\b)$/', $stdout, $matches)) {
				$e = "Failed to parse stdout ($stdout) of command ($cmd)";
				if ($stderr !== null && strlen($stderr)) {
					$e .= " with this stderr: $stderr";
				}
				throw new \RuntimeException($e);
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

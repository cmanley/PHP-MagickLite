<?php
/**
* Contains the MagickLite class.
*
* Dependencies:
* <pre>
* GraphicsMagick OR ImageMagick CLI commands.
* </pre>
*
* @author    Craig Manley
* @copyright Copyright © 2014, Craig Manley (craigmanley.com). All rights reserved.
* @version   $Id: MagickLite.class.php,v 1.1 2014/11/16 02:08:17 cmanley Exp $
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

	protected static $found_gm = null; // cached result of _check_exists_gm()
	protected static $found_im = null; // cached result of _check_exists_im()

	protected $file;
	protected $data;
	protected $debug;
	protected $identify_cache;
	protected $use_gm;

	/**
	* Constructor.
	*
	* Supported options:
	*	debug	- boolean
	*	prefer	- one of 'gm' (default) or 'im', to indicate your preference for GraphicsMagick or ImageMagick.
	*	type	- 1st argument refers to a 'file' (default) or 'data'.
	*
	* @param string $file_or_data
	* @param array $options
	*/
	public function __construct($file_or_data, array $options = null) {
		if (!(is_string($file_or_data) && strlen($file_or_data))) {
			throw new InvalidArgumentException('Invalid or missing file or data in first argument.');
		}
		$type = $options && array_key_exists('type', $options) ? $options['type'] : null;
		if (is_null($type)) {
			$type = 'file';
		}
		elseif (!(is_string($type) && preg_match('/^(?:file|data)$/', $type))) {
			throw new InvalidArgumentException('Invalid or missing $type argument.');
		}
		if ($type == 'file') {
			if (!file_exists($file_or_data)) {
				throw new InvalidArgumentException('File not found: ' . (strlen($file_or_data) > 255 ? '(too much data, probably not a file)' : $file_or_data));
			}
			$this->file = $file_or_data;
		}
		else {
			$this->data = $file_or_data;
		}

		$prefer = 'gm';

		if ($options) {
			$this->debug = @$options['debug'];
			if (@$options['prefer']) {
				$prefer = $options['prefer'];
				if (!(is_string($prefer) && preg_match('/^(?:pm|im)$/', $prefer))) {
					throw new InvalidArgumentException('Invalid "prefer" option (must be one of "pm" or "im").');
				}
			}
		}

		// Set preferred CLI, if available.
		if ($prefer == 'gm') {
			if (static::_check_exists_gm()) {
				$this->use_gm = true;
			}
			elseif (static::_check_exists_im()) {
				$this->use_gm = false;
			}
		}
		else {
			if (static::_check_exists_im()) {
				$this->use_gm = false;
			}
			elseif (static::_check_exists_gm()) {
				$this->use_gm = true;
			}
		}
		if (is_null($this->use_gm)) {
			throw new Exception("Unable to locate 'gm' or 'identify' CLI commands");
		}
		$this->debug && error_log(__METHOD__ . ' Use ' . ($this->use_gm ? 'GraphicsMagick' : 'ImageMagick'));
	}


	/**
	* Checks if the GraphicsMagick CLI exists.
	*
	* @return boolean
	*/
	protected static function _check_exists_gm() {
		if (is_null(static::$found_gm)) {
			$out = null;
			$rc = null;
			$cmd = exec('which gm', $out, $rc);
			static::$found_gm = $rc == 0;
		}
		return static::$found_gm;
	}


	/**
	* Checks if the ImageMagick CLI exists.
	*
	* @return boolean
	*/
	protected static function _check_exists_im() {
		if (is_null(static::$found_im)) {
			$out = null;
			$rc = null;
			$cmd = exec('which identify', $out, $rc);
			static::$found_im = $rc == 0;
		}
		return static::$found_im;
	}


	/**
	* Executes the given command with the given arguments.
	*
	* @param string $cmd (don't escape)
	* @param array $args array of arguments, if any (don't escape)
	* @param string|null $stdin this is piped into the process
	* @param string &$stdout receives the processes STDOUT.
	* @param string &$stderr receives the processes STDERR.
	* @return void
	*/
	protected function _proc_exec($cmd, array $args, $stdin = null, &$stdout, &$stderr) {
		$cmd = '(' . escapeshellcmd($cmd) . ' ' . join(' ', array_map(function($arg) { return escapeshellarg($arg); }, $args)) . ') 3>/dev/null; echo $? >&3'; // unreliable proc_close exitcode workaround
		$this->debug && error_log(__METHOD__ . " $cmd");
		$descriptors = array(
			0 => array('pipe', 'r'),	// stdin is a pipe that the child will read from
			1 => array('pipe', 'w'),	// stdout is a pipe that the child will write to
			2 => array('pipe', 'w'),	// stderr is a pipe that the child will write to
			3 => array('pipe', 'w'),	// unreliable proc_close exitcode workaround
		);
		$pipes = null;
		$process = proc_open($cmd, $descriptors, $pipes);
		if (!is_resource($process)) {
			throw new Exception("Failed to open process '$cmd'.\n");
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
		$rc = $status && $status['running'] ? $rc : $status['exitcode'];
		if (($rc == -1) && preg_match('/^(-?\d+)\s*$/', $exitcode, $matches)) { // unreliable proc_close exitcode workaround
			$rc = (int) $matches[1];
		}
		if ($rc != 0) {  // 0 is success
			$e = "Bad exit code $rc from command '$cmd'";
			if (isset($stderr) && strlen($stderr)) {
				$e .=	" and this error output: $stderr";
			}
			else {
				$e .= '.';
			}
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
	*			'-resize', '1000x1000>',
	*			'-quality', 75,
	*		)
	*		, 'watermark.png'
	*		, 'out.jpg'
	*	);
	* </pre>
	*
	* @param array	$options as supported by the composite CLI command (don't escape)
	* @param string	$change_image - The file containing the changes (typically a watermark).
	* @param string	$output_file - optional. If not given, then the result is stored internally and can only be saved by calling convert again with an output file name.
	* @param string	$output_magic - optional magic of output if it cannot be deduced from the file name
	* @return this (for method chaining)
	*/
	public function composite(array $options, $change_image, $output_file = null, $output_magic = null) {
		if (!($change_image && is_string($change_image))) {
			throw new InvalidArgumentException('Missing or invalid change_image argument.');
		}
		if (!file_exists($change_image)) {
			throw new InvalidArgumentException("Change image file not found: '$change_image'");
		}

		$cmd = $this->use_gm ? 'gm' : 'composite';
		$args = $this->use_gm ? array('composite') : array();
		$args = array_merge($args, $options);
		$args []= $change_image;
		if ($this->file) {
			$args []= $this->file;
		}
		else {
			if ($this->use_gm) {
				$args []= '-'; // GraphicsMagick can't parse frame number from arguments: https://sourceforge.net/tracker/?func=detail&aid=3385967&group_id=73485&atid=537937
			}
			else {
				$args []= '-[0]';
			}
		}
		if ($output_file && ($output_file != '-')) {
			$args []= $output_magic ? $output_magic . ':' . $output_file : $output_file;
		}
		else {
			$args []= $output_magic ? $output_magic . ':-' : '-';
		}

		$stdout;
		$stderr;
		$this->_proc_exec($cmd, $args, $this->data, $stdout, $stderr);

		if (is_null($output_file) || ($output_file == '-')) {
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
	*		array(
	*			'-resize', '100x100>',
	*			'-quality', 90,			// JPEG quality
	*			'-filter',	'Sinc',
	*			'-blur',	1,
	*			'+profile', '*',		// removes any ICM, EXIF, IPTC profiles that may be present
	*		)
	*		, 'out.jpg'
	*	);
	* </pre>
	*
	* @param array	$options as supported by the convert CLI command (don't escape)
	* @param string	$output_file - optional. If not given, then the result is stored internally and can only be saved by calling convert again with an output file name.
	* @param string	$output_magic - optional magic of output if it cannot be deduced from the file name
	* @return this (for method chaining)
	*/
	public function convert(array $options, $output_file = null, $output_magic = null) {
		$cmd = $this->use_gm ? 'gm' : 'convert';
		$args = $this->use_gm ? array('convert') : array();
		if ($this->file) {
			$args []= $this->file;
		}
		else {
			if ($this->use_gm) {
				$args []= '-'; // GraphicsMagick can't parse frame number from arguments: https://sourceforge.net/tracker/?func=detail&aid=3385967&group_id=73485&atid=537937
			}
			else {
				$args []= '-[0]';
			}
		}
		$args = array_merge($args, $options);
		if ($output_file && ($output_file != '-')) {
			$args []= $output_magic ? $output_magic . ':' . $output_file : $output_file;
		}
		else {
			$args []= $output_magic ? $output_magic . ':-' : '-';
		}

		$stdout;
		$stderr;
		$this->_proc_exec($cmd, $args, $this->data, $stdout, $stderr);

		if (is_null($output_file) || ($output_file == '-')) {
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
	* @param string	&$width optional reference that receives the width.
	* @param string	&$height optional reference that receives the height.
	* @param string	&$magic optional reference that receives the magic.
	* @return this (for method chaining)
	*/
	public function identify(&$width = null, &$height = null, &$magic = null) {
		if ($this->identify_cache) {
			$width	= $this->identify_cache['width'];
			$height	= $this->identify_cache['height'];
			$magic	= $this->identify_cache['magic'];
		}
		else {
			$cmd = $this->use_gm ? 'gm' : 'identify';
			$args = $this->use_gm ? array('identify') : array();
			$args []= '-format';
			$args []= '%w %h %m';	// http://www.graphicsmagick.org/GraphicsMagick.html#details-format
			if ($this->file) {
				$args []= $this->file;
			}
			else {
				if ($this->use_gm) {
					$args []= '-'; // GraphicsMagick can't parse frame number from arguments: https://sourceforge.net/tracker/?func=detail&aid=3385967&group_id=73485&atid=537937
				}
				else {
					$args []= '-[0]';
				}
			}

			$stdout;
			$stderr;
			$this->_proc_exec($cmd, $args, $this->data, $stdout, $stderr);

			// Parse STDOUT.
			if (!preg_match($this->use_gm ? '/^(\d{1,5})(?: \d+)* (\d{1,5}) (\b.+\b)$/' : '/^(\d{1,5}) (\d{1,5}) (\b.+\b)$/', $stdout, $matches)) {
				throw new Exception("Failed to parse output (\"$stdout\") of command \"$cmd\".");
			}
			$width	= intval($matches[1]);
			$height	= intval($matches[2]);
			$magic	= $matches[3];
			$this->identify_cache = array(
				'width'		=> $width,
				'height'	=> $height,
				'magic'		=> $magic,
			);
		}
		return $this;
	}


	/**
	* Returns the internal image data if any.
	* Not chainable.
	*
	* @return string|null
	*/
	public function data() {
		if ($this->data) {
			return $this->data;
		}
		elseif ($this->file) {
			return file_get_contents($this->file);
		}
		return null;
	}

}

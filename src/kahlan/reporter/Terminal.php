<?php
/**
 * Kahlan: PHP Testing Framework
 *
 * @copyright     Copyright 2013, CrysaLEAD
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace kahlan\reporter;

use kahlan\util\String;
use kahlan\analysis\Debugger;

class Terminal extends Reporter {

	/**
	 * ANSI/VT100 color/format sequences
	 *
	 * @var array
	 */
	protected $_vt100 = [
		'colors' => [
			'black' => 30,
			'red' => 31,
			'green' => 32,
			'yellow' => 33,
			'blue' => 34,
			'magenta' => 35,
			'cyan' => 36,
			'white' => 37,
			'default' => 39
		],
		'formats' => [
			'n' => 0,   //normal
			'b' => 1,   //bold
			'd' => 2,   //dim
			'u' => 4,   //underline
			'r' => 7,   //reverse
			'h' => 7,   //hidden
			's' => 9    //strike
		]
	];

	/**
	 * The default color
	 *
	 * @var string
	 */
	protected $_vtcolor = 'default';

	/**
	 * The default background color
	 *
	 * @var string
	 */
	protected $_vtbackground = 'default';

	/**
	 * The default style
	 *
	 * @var string
	 */
	protected $_vtstyle = 'default';

	/**
	 * Return the ANSI/VT100 number from a color name.
	 *
	 * @param  mixed $name A color name string or a ANSI/VT100 number
	 * @return integer a ANSI/VT100 number
	 */
	protected function _vtcolor($name) {
		return $this->_vt100($name);
	}

	/**
	 * Return the ANSI/VT100 number from a backgound color name.
	 *
	 * @param  mixed $name A backgound color name string or a ANSI/VT100 number
	 * @return integer a ANSI/VT100 number
	 */
	protected function _vtbackground($name) {
		if (is_numeric($name)) {
			return $name + 10;
		}
		return $this->_vtcolor($name) + 10;
	}

	/**
	 * Return a ANSI/VT100 number from a style name.
	 *
	 * @param  mixed $name A style name string or a ANSI/VT100 number
	 * @return integer a ANSI/VT100 number
	 */
	protected function _vtstyle($name) {
		return isset($this->_vt100['formats'][$name]) ? $this->_vt100['formats'][$name] : 0;
	}

	/**
	 * Return a ANSI/VT100 number from a color name.
	 *
	 * @param  mixed $name A color name string or a ANSI/VT100 number
	 * @return integer a ANSI/VT100 number
	 */
	protected function _vt100($name) {
		if (is_numeric($name)) {
			return $name;
		}
		$value = 0;
		$items = explode(' ', $name);

		if (($name = array_shift($items)) === 'light') {
			$value += 100;
			$name = array_shift($items);
		}

		if (isset($this->_vt100['colors'][$name])) {
			$value += $this->_vt100['colors'][$name];
		} else {
			$value = 39;
		}
		return $value;
	}

	/**
	 * Print a string to STDOUT.
	 *
	 * @param mixed        $string  The string to print.
	 * @param string|array $options The possible values for an array are:
	 *                     - `'style`: a style code.
	 *                     - `'color'`: a color code.
	 *                     - `'background'`: a background color code.
	 *                     The string must respect one of the following format:
	 *                     - `'style;color;background'`
	 *                     - `'style;color'`
	 *                     - `'color'`
	 *
	 */
	public function console($string, $options = null) {
		if ($options === null) {
			echo $string;
			return;
		}

		if (is_string($options)) {
			$options = explode(';', $options);
			if (strlen($options[0]) === 1) {
				$options = array_pad($options, 3, 'default');
				$options = array_combine(['style', 'color', 'background'], $options);
			} else {
				$options = ['color' => reset($options)];
			}
		}

		$options += [
			'style' => 'default',
			'color' => 'default',
			'background' => 'default'
		];

		$format = "\e[";
		$format .= $this->_vtstyle($options['style']) . ';';
		$format .= $this->_vtcolor($options['color']) . ';';
		$format .= $this->_vtbackground($options['background']) . 'm';

		echo $format . $string . "\e[0m";
	}

	/**
	 * Callback called before any specs processing.
	 *
	 * @param array $params The suite params array.
	 */
	public function begin($params) {
		parent::begin($params);
		$this->console("Kahlan : PHP Testing Framework\n\n");
	}

	/**
	 * Print a report to STDOUT
	 *
	 * @param array $report A report array.
	 */
	protected function _report($report) {
		switch($report['type']) {
			case 'fail':
				$this->_reportFailure($report);
			break;
			case 'incomplete':
				$this->_reportIncomplete($report);
			break;
			case 'exception':
				$this->_reportException($report);
			break;
		}
	}

	/**
	 * Print a failure report to STDOUT
	 *
	 * @param array $report A report array.
	 */
	protected function _reportFailure($report) {
		$matcher = $report['class'];
		$not = $report['not'];
		$params = $report['params'];
		$description = $matcher::description();

		$this->console("[Failure] ", "n;red");
		$this->_messages($report['messages']);
		foreach ($params as $key => $value) {
			$this->console("{$key}: ", 'n;yellow');
			$this->console(String::dump($value) . "\n");
		}
		$this->console("Description:", "n;magenta");
		$this->console(" {$report['matcher']} expected actual to ");
		if ($not) {
			$this->console("NOT ", 'n;magenta');
		}
		$this->console("{$description}\n");
		$this->console("Trace: ", "n;yellow");
		$this->console(Debugger::trace([
			'trace' => $report['exception'], 'depth' => 1
		]));
		$this->console("\n\n");
	}

	/**
	 * Print an incomplete exception report to STDOUT
	 *
	 * @param array $report A report array.
	 */
	protected function _reportIncomplete($report) {
		$this->console("[Incomplete test] ", "n;yellow");
		$this->_messages($report['messages']);
		$this->console("Description:", "n;magenta");
		$this->console(" You are using an unexisting class.\n");
		$this->console("Trace: ", "n;yellow");
		$this->console(Debugger::trace([
			'trace' => $report['exception'], 'start' => 1, 'depth' => 1
		]));
		$this->console("\n\n");
	}

	/**
	 * Print an exception report to STDOUT
	 *
	 * @param array $report A report array.
	 */
	protected function _reportException($report) {
		$this->console("[Uncatched Exception] ", "n;magenta");
		$this->_messages($report['messages']);
		$this->console("Trace:\n", "n;yellow");
		$this->console(Debugger::trace(['trace' => $report['exception']]));
		$this->console("\n\n");
	}

	/**
	 * Print an array of description messages to STDOUT
	 *
	 * @param array $messages An array of description message.
	 */
	protected function _messages($messages) {
		$tab = 0;
		foreach ($messages as $message) {
			$this->console(str_repeat("    ", $tab));
			preg_match('/^((?:it|when)?\s*(?:not)?)(.*)$/', $message, $matches);
			$this->console($matches[1], "n;magenta");
			$this->console($matches[2]);
			$this->console("\n");
			$tab++;
		}
		$this->console("\n");
	}

	/**
	 * Print a summary of specs execution to STDOUT
	 *
	 * @param array $results The results array of the execution.
	 */
	public function _summary($results) {
		$passed = count($results['pass']) + count($results['skip']);
		$failed = 0;
		foreach (['exception', 'incomplete', 'fail'] as $value) {
			${$value} = count($results[$value]);
			$failed += ${$value};
		}
		$total = $passed + $failed;

		$this->console('Executed ' . $passed . " of {$total} ");

		if ($failed) {
			$this->console("FAIL ", "red");
			$this->console("(");
			$comma = false;
			if ($fail) {
				$this->console("FAILURE: " . $fail , "red");
				$comma = true;
			}
			if ($incomplete) {
				if ($comma) {
					$this->console(", ");
				}
				$this->console("INCOMPLETE: " . $incomplete , "yellow");
				$comma = true;
			}
			if ($exception) {
				if ($comma) {
					$this->console(", ");
				}
				$this->console("EXCEPTION: " . $exception , "magenta");
			}
			$this->console(")");
		} else {
			$this->console("PASS\n", "green");
		}
		$this->console("\n");
	}
}

?>
<?php
// $Id: lang.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - conversions and argument helpers
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * The bare global functions: String() / Number() / Boolean(), the lenient
 * parseInt() / parseFloat() pair and their isNaN() / isFinite() tests, and
 * PHP's func_get_*(). Registered under 'lang' so dispatch works like any
 * other module, but the name is internal - a script only ever sees the bare
 * spellings listed in Pkript_Stdlib::$globals.
 */
class Pkript_Std_Lang extends Pkript_Std_Module {
	public static function members() {
		return array(
			'String', 'Number', 'Boolean',
			'parseInt', 'parseFloat', 'isNaN', 'isFinite',
			'func_get_args', 'func_num_args', 'func_get_arg',
		);
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'String':
				return Pkript_Interpreter::toStringValue($this->arg($args, 0, ''));
			case 'Number':
				return $this->rt->toNumber($this->arg($args, 0, 0), $node);
			case 'Boolean':
				return Pkript_Interpreter::toBool($this->arg($args, 0, FALSE));

			case 'parseInt':
				return $this->parseInt($args, $node);
			case 'parseFloat':
				return $this->parseFloat($args);

			case 'isNaN':
				$v = $this->coerce($this->arg($args, 0, NULL));
				return is_float($v) && is_nan($v);

			case 'isFinite':
				$v = $this->coerce($this->arg($args, 0, NULL));
				if (!is_int($v) && !is_float($v))
					return FALSE;
				return !is_nan((float) $v) && is_finite((float) $v);

			case 'func_get_args':
				return new Pkript_Arr($this->rt->currentCallArgs());
			case 'func_num_args':
				return count($this->rt->currentCallArgs());

			case 'func_get_arg':
				$callArgs = $this->rt->currentCallArgs();
				$i = (int) $this->rt->toNumber($this->arg($args, 0, NULL), $node);
				return isset($callArgs[$i]) ? $callArgs[$i] : NULL;
		}
	}

	/**
	 * What isNaN() and isFinite() test, which is Number()'s reading rather
	 * than parseFloat()'s: the whole text has to be a number, so isNaN('1x')
	 * is true, while the empty string and null count as 0.
	 *
	 * @return int|float|NULL NULL when the value is not number-like at all
	 */
	private function coerce($v) {
		if (is_int($v) || is_float($v))
			return $v;
		if (is_bool($v))
			return $v ? 1 : 0;
		if ($v === NULL)
			return 0;
		if (!is_string($v))
			return NULL;

		$s = trim(Pkript_Interpreter::stripHtmlMarks($v));
		if ($s === '')
			return 0;
		if ($s === 'Infinity' || $s === '+Infinity')
			return INF;
		if ($s === '-Infinity')
			return -INF;
		return is_numeric($s) ? $s + 0 : NAN;
	}

	/**
	 * Radix 2..36, defaulting to 10 - not to JS's octal-ish guess, which is
	 * a trap: parseInt('08') is 8 here, always.  A leading 0x is still read
	 * as hexadecimal when the radix is 16 or left out.
	 */
	private function parseInt($args, $node) {
		$s = trim($this->strArg($args, 0));
		$radix = (int) $this->numArg($args, 1, $node, 10);
		if ($radix === 0)
			$radix = 10;
		if ($radix < 2 || $radix > 36)
			return NAN;

		$sign = 1;
		if ($s !== '' && ($s[0] === '+' || $s[0] === '-')) {
			$sign = $s[0] === '-' ? -1 : 1;
			$s = substr($s, 1);
		}
		if (($radix === 16 || !array_key_exists(1, $args)) &&
		    preg_match('/^0[xX][0-9a-fA-F]/', $s)) {
			$radix = 16;
			$s = substr($s, 2);
		}

		// The longest prefix that is a numeral in this radix, as in JS
		$digits = substr('0123456789abcdefghijklmnopqrstuvwxyz', 0, $radix);
		$len = strspn(strtolower($s), $digits);
		if ($len === 0)
			return NAN;

		$n = intval(substr($s, 0, $len), $radix);
		return $sign * $n;
	}

	private function parseFloat($args) {
		return $this->parseFloatOf($this->strArg($args, 0));
	}

	/** The shared reading, so isNaN('1.5x') and parseFloat('1.5x') agree. */
	private function parseFloatOf($v) {
		$s = trim(Pkript_Interpreter::toStringValue($v));
		if (preg_match('/^[+-]?(Infinity)/', $s, $m))
			return $s[0] === '-' ? -INF : INF;
		if (!preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?/', $s, $m))
			return NAN;

		$n = (float) $m[0];
		// An integer stays an integer, so parseFloat('3') prints as 3
		return $n == (int) $n && abs($n) < 1e15 ? (int) $n : $n;
	}
}

<?php
// $Id: number_methods.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Number methods
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Std_NumberMethods extends Pkript_Std_Methods {
	public static function methods() {
		return array('toFixed', 'toPrecision', 'toString', 'valueOf');
	}

	public function call($n, $name, $args, $node) {
		switch ($name) {
			case 'toFixed':
				$digits = (int) $this->numArg($args, 0, $node, 0);
				if ($digits < 0 || $digits > 20)
					$this->rt->fail('toFixed digits out of range', $node);
				return number_format((float) $n, $digits, '.', '');

			case 'toPrecision':
				if (!array_key_exists(0, $args))
					return Pkript_Interpreter::toStringValue($n);
				$digits = (int) $this->numArg($args, 0, $node, 1);
				if ($digits < 1 || $digits > 100)
					$this->rt->fail('toPrecision digits out of range', $node);
				return $this->toPrecision((float) $n, $digits);

			case 'toString':
				if (array_key_exists(0, $args)) {
					$radix = (int) $this->numArg($args, 0, $node, 10);
					if ($radix < 2 || $radix > 36)
						$this->rt->fail('toString radix out of range', $node);
					if ($radix !== 10) {
						if (is_float($n) && !is_finite($n))
							return Pkript_Interpreter::toStringValue($n);
						$i = (int) $n;
						return ($i < 0 ? '-' : '') . base_convert((string) abs($i), 10, $radix);
					}
				}
				return Pkript_Interpreter::toStringValue($n);

			case 'valueOf':
				return $n;
		}
	}

	/**
	 * $digits significant digits, spelled the way JS spells them: plain
	 * notation while the exponent is in -6..$digits, exponential outside it.
	 */
	private function toPrecision($n, $digits) {
		if (is_nan($n) || !is_finite($n))
			return Pkript_Interpreter::toStringValue($n);
		if ($n == 0.0) {
			return $digits === 1
				? '0' : '0.' . str_repeat('0', $digits - 1);
		}

		$exp = (int) floor(log10(abs($n)));
		// log10 lands on the wrong side of a power of ten now and then
		if (abs($n) < pow(10, $exp))
			$exp--;
		elseif (abs($n) >= pow(10, $exp + 1))
			$exp++;

		if ($exp < -6 || $exp >= $digits) {
			$out = sprintf('%.' . ($digits - 1) . 'e', $n);
			// PHP writes 'e+1' where JS writes 'e+1' too, but pads to two
			// digits on some platforms
			return preg_replace('/e([+-])0*(\d)/', 'e$1$2', $out);
		}
		return number_format($n, max(0, $digits - 1 - $exp), '.', '');
	}
}

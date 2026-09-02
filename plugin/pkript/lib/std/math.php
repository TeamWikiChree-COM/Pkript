<?php
// $Id: math.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Math namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Std_Math extends Pkript_Std_Module {
	public static function members() {
		return array(
			'floor', 'ceil', 'round', 'trunc', 'abs', 'sign', 'min', 'max',
			'random', 'sqrt', 'cbrt', 'pow', 'hypot',
			'exp', 'log', 'log2', 'log10',
			'sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'atan2',
		);
	}

	/**
	 * Math.PI and friends are values, not functions, so they are published
	 * here rather than in members(): the registry hands them to the global
	 * scope as plain properties of the `Math` object.
	 */
	public static function constants() {
		return array(
			'PI' => M_PI,
			'E' => M_E,
			'LN2' => M_LN2,
			'LN10' => M_LN10,
			'LOG2E' => M_LOG2E,
			'LOG10E' => M_LOG10E,
			'SQRT2' => M_SQRT2,
			'SQRT1_2' => M_SQRT1_2,
		);
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'floor':
				return (int) floor($this->numArg($args, 0, $node));
			case 'ceil':
				return (int) ceil($this->numArg($args, 0, $node));
			case 'round':
				return (int) round($this->numArg($args, 0, $node));
			case 'trunc':
				return (int) $this->numArg($args, 0, $node);
			case 'abs':
				return abs($this->numArg($args, 0, $node));

			case 'sign':
				$n = $this->numArg($args, 0, $node);
				if (is_nan((float) $n))
					return NAN;
				return $n > 0 ? 1 : ($n < 0 ? -1 : 0);

			case 'random':
				// 0 <= n < 1, like JavaScript. Not security grade: a script
				// must never make a token, password or id with this.
				return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();

			case 'min':
			case 'max':
				return $this->extreme($name, $args, $node);

			case 'pow':
				return $this->finite(
					pow($this->numArg($args, 0, $node),
						$this->numArg($args, 1, $node)));
			case 'atan2':
				return atan2($this->numArg($args, 0, $node),
					$this->numArg($args, 1, $node));

			case 'hypot':
				$sum = 0.0;
				foreach ($args as $a) {
					$n = (float) $this->rt->toNumber($a, $node);
					$sum += $n * $n;
				}
				return sqrt($sum);

			// The one argument functions, all of which JS answers with NaN
			// rather than an error when the argument is out of their domain
			case 'sqrt':
			case 'cbrt':
			case 'exp':
			case 'log':
			case 'log2':
			case 'log10':
			case 'sin':
			case 'cos':
			case 'tan':
			case 'asin':
			case 'acos':
			case 'atan':
				return $this->unary($name, (float) $this->numArg($args, 0, $node));
		}
	}

	private function unary($name, $x) {
		switch ($name) {
			case 'sqrt':
				return $x < 0 ? NAN : sqrt($x);
			case 'cbrt':
				// PHP has no cbrt(); pow() cannot take a negative base to a
				// fractional power, so the sign is carried around it
				return $x < 0 ? -pow(-$x, 1 / 3) : pow($x, 1 / 3);
			case 'exp':
				return $this->finite(exp($x));
			case 'log':
				return $x < 0 ? NAN : ($x == 0 ? -INF : log($x));
			case 'log2':
				return $x < 0 ? NAN : ($x == 0 ? -INF : log($x, 2));
			case 'log10':
				return $x < 0 ? NAN : ($x == 0 ? -INF : log10($x));
			case 'asin':
			case 'acos':
				if ($x < -1 || $x > 1)
					return NAN;
				return $name === 'asin' ? asin($x) : acos($x);
			case 'sin':
				return sin($x);
			case 'cos':
				return cos($x);
			case 'tan':
				return tan($x);
			case 'atan':
				return atan($x);
		}
	}

	/** PHP answers an overflow with INF in some builds and a warning in others. */
	private function finite($n) {
		return is_float($n) && !is_finite($n) && !is_nan($n)
			? ($n > 0 ? INF : -INF) : $n;
	}

	private function extreme($name, $args, $node) {
		if (empty($args))
			$this->rt->fail($name . ' requires an argument', $node);

		$nums = array();
		foreach ($args as $a) {
			$n = $this->rt->toNumber($a, $node);
			// As in JS: one NaN in the list makes the whole answer NaN
			if (is_float($n) && is_nan($n))
				return NAN;
			$nums[] = $n;
		}
		return $name === 'min' ? min($nums) : max($nums);
	}
}

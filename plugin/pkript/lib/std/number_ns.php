<?php
// $Id: number_ns.php,v 0.4 2026/09/02 00:00:00 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Number namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * `Number.isInteger()` and the limits a script needs to know about.
 *
 * The tests here are the strict ones: unlike the bare `isNaN()` and
 * `isFinite()`, which convert first, `Number.isNaN('x')` is false, because a
 * string is not the number NaN - it is not a number at all. That difference
 * is the reason both spellings exist in JavaScript, so both exist here.
 */
class Pkript_Std_NumberNs extends Pkript_Std_Module {
	public static function members() {
		return array('isInteger', 'isSafeInteger', 'isFinite', 'isNaN',
			'parseInt', 'parseFloat');
	}

	public static function constants() {
		return array(
			// 2**53 - 1: past this, whole numbers stop being exact
			'MAX_SAFE_INTEGER' => 9007199254740991,
			'MIN_SAFE_INTEGER' => -9007199254740991,
			'MAX_VALUE' => PHP_FLOAT_MAX,
			'MIN_VALUE' => PHP_FLOAT_MIN,
			'EPSILON' => PHP_FLOAT_EPSILON,
			'POSITIVE_INFINITY' => INF,
			'NEGATIVE_INFINITY' => -INF,
			'NaN' => NAN,
		);
	}

	public function call($name, $args, $node) {
		// The two that read text rather than testing a number are the bare
		// ones under another name, so there is one reading of '12px' and not two
		if ($name === 'parseInt' || $name === 'parseFloat')
			return $this->rt->callBuiltin('lang.' . $name, $args, $node);

		$v = $this->arg($args, 0, NULL);
		if (!is_int($v) && !is_float($v))
			return FALSE;   // nothing that is not a number passes any of these

		switch ($name) {
			case 'isNaN':
				return is_float($v) && is_nan($v);
			case 'isFinite':
				return !is_nan((float) $v) && is_finite((float) $v);
			case 'isInteger':
				return is_int($v) || (is_finite($v) && $v == floor($v));
			case 'isSafeInteger':
				return (is_int($v) || (is_finite($v) && $v == floor($v))) &&
					abs($v) <= 9007199254740991;
		}
	}
}

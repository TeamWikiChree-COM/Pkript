<?php
// $Id: number_methods.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Number methods
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Std_NumberMethods extends Pkript_Std_Methods {
	public static function methods() {
		return array('toFixed', 'toString');
	}

	public function call($n, $name, $args, $node) {
		switch ($name) {
			case 'toFixed':
				$digits = (int) $this->numArg($args, 0, $node, 0);
				if ($digits < 0 || $digits > 20)
					$this->rt->fail('toFixed の桁数が範囲外です', $node);
				return number_format((float) $n, $digits, '.', '');

			case 'toString':
				return Pkript_Interpreter::toStringValue($n);
		}
	}
}

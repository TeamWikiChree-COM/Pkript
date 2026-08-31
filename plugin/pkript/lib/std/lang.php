<?php
// $Id: lang.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - conversions and argument helpers
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * The bare global functions: String() / Number() / Boolean() and PHP's
 * func_get_*(). Registered under 'lang' so dispatch works like any other
 * module, but the name is internal - a script only ever sees the bare
 * spellings listed in Pkript_Stdlib::$globals.
 */
class Pkript_Std_Lang extends Pkript_Std_Module {
	public static function members() {
		return array(
			'String', 'Number', 'Boolean',
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
}

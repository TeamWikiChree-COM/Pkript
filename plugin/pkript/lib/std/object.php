<?php
// $Id: object.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Object namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Std_Object extends Pkript_Std_Module {
	public static function members() {
		return array('keys', 'values', 'has');
	}

	public function call($name, $args, $node) {
		$obj = $this->arg($args, 0, NULL);
		if (!($obj instanceof Pkript_Obj)) {
			$this->rt->fail(Pkript_Interpreter::typeName($obj) .
				' は Object ではありません', $node);
		}

		switch ($name) {
			case 'keys':
				return new Pkript_Arr(array_keys($obj->props));
			case 'values':
				return new Pkript_Arr(array_values($obj->props));
			case 'has':
				return array_key_exists($this->strArg($args, 1), $obj->props);
		}
	}
}

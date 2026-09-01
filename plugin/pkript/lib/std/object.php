<?php
// $Id: object.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Object namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Std_Object extends Pkript_Std_Module {
	public static function members() {
		return array('keys', 'values', 'entries', 'has', 'assign', 'fromEntries');
	}

	public function call($name, $args, $node) {
		if ($name === 'fromEntries')
			return $this->fromEntries($args, $node);

		$obj = $this->arg($args, 0, NULL);
		if (!($obj instanceof Pkript_Obj)) {
			$this->rt->fail(Pkript_Interpreter::typeName($obj) .
				' is not an Object', $node);
		}

		switch ($name) {
			case 'keys':
				return new Pkript_Arr(array_keys($obj->props));
			case 'values':
				return new Pkript_Arr(array_values($obj->props));

			case 'entries':
				$out = array();
				foreach ($obj->props as $key => $value)
					$out[] = new Pkript_Arr(array((string) $key, $value));
				return new Pkript_Arr($this->rt->checkArray($out, $node));

			case 'has':
				return array_key_exists($this->strArg($args, 1), $obj->props);

			case 'assign':
				return $this->assign($obj, $args, $node);
		}
	}

	/**
	 * Copies into the first object and answers with it, as in JavaScript -
	 * the target is changed in place and the caller sees it.
	 */
	private function assign($target, $args, $node) {
		foreach (array_slice($args, 1) as $source) {
			if ($source === NULL)
				continue;
			if (!($source instanceof Pkript_Obj)) {
				$this->rt->fail(Pkript_Interpreter::typeName($source) .
					' is not an Object', $node);
			}
			foreach ($source->props as $key => $value)
				$target->props[$key] = $value;
		}
		$this->rt->checkArray($target->props, $node);
		return $target;
	}

	/** The other direction from entries(): [[k, v], ...] back into an object. */
	private function fromEntries($args, $node) {
		$pairs = $this->arg($args, 0, NULL);
		if (!($pairs instanceof Pkript_Arr)) {
			$this->rt->fail(Pkript_Interpreter::typeName($pairs) .
				' is not an Array', $node);
		}

		$obj = new Pkript_Obj();
		foreach ($pairs->items as $pair) {
			if (!($pair instanceof Pkript_Arr) || count($pair->items) < 1)
				$this->rt->fail('fromEntries expects [key, value] arrays', $node);
			$key = Pkript_Interpreter::stripHtmlMarks(
				Pkript_Interpreter::toStringValue($pair->items[0]));
			$obj->props[$key] = array_key_exists(1, $pair->items)
				? $pair->items[1] : NULL;
		}
		$this->rt->checkArray($obj->props, $node);
		return $obj;
	}
}

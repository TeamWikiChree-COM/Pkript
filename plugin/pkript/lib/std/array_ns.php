<?php
// $Id: array_ns.php,v 0.4 2026/09/02 00:00:00 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Array namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * `Array.isArray()` and the two ways of making one.
 *
 * The name `Array` is also the label an array value's methods are looked up
 * under, but the two are separate modules: `Array.from` is here, `[].map` is
 * in array_methods.php. Which one a lookup reads is decided by whether it
 * started from the name or from a value - see Pkript_Stdlib.
 */
class Pkript_Std_ArrayNs extends Pkript_Std_Module {
	public static function members() {
		return array('isArray', 'from', 'of');
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'isArray':
				return $this->arg($args, 0, NULL) instanceof Pkript_Arr;

			// `Array.of(1, 2)` is [1, 2] - the spelling that exists because
			// `Array(3)` would mean something else in JavaScript
			case 'of':
				return new Pkript_Arr($this->rt->checkArray($args, $node));

			case 'from':
				return $this->from($args, $node);
		}
	}

	/**
	 * An array out of anything a for..of loop could walk, with an optional
	 * function applied to each element on the way - so `Array.from(s, f)` is
	 * the shorter `[...s].map(f)`.
	 */
	private function from($args, $node) {
		$items = $this->elementsOf($this->arg($args, 0, NULL), $node);

		$fn = $this->arg($args, 1, NULL);
		if ($fn === NULL)
			return new Pkript_Arr($this->rt->checkArray($items, $node));

		$out = array();
		foreach ($items as $i => $item)
			$out[] = $this->rt->callValue($fn, array($item, $i), $node);
		return new Pkript_Arr($this->rt->checkArray($out, $node));
	}

	/** What `Array.from()` accepts: an array, a string, or an object's values. */
	private function elementsOf($subject, $node) {
		if ($subject instanceof Pkript_Arr)
			return $subject->items;
		if (is_string($subject))
			return mb_str_split($subject, 1, PKRIPT_ENCODING);
		if ($subject instanceof Pkript_Obj)
			return array_values($subject->props);

		$this->rt->fail('Array.from needs an array, a string or an object, not ' .
			Pkript_Interpreter::typeName($subject), $node);
	}
}

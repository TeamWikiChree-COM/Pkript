<?php
// $Id: array_methods.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Array methods
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Mutating methods (push / sort / reverse) change the receiver in place and
 * the caller sees it, as in JavaScript; the rest return a new Pkript_Arr.
 * Anything that can grow an array goes through checkArray().
 */
class Pkript_Std_ArrayMethods extends Pkript_Std_Methods {
	public static function methods() {
		return array(
			'push', 'pop', 'shift', 'unshift', 'join', 'indexOf', 'includes',
			'slice', 'reverse', 'concat', 'map', 'filter', 'find', 'findIndex',
			'forEach', 'some', 'every', 'reduce', 'sort',
		);
	}

	public function call($arr, $name, $args, $node) {
		switch ($name) {
			case 'push':
				foreach ($args as $a)
					$arr->items[] = $a;
				$this->rt->checkArray($arr->items, $node);
				return count($arr->items);

			case 'unshift':
				foreach (array_reverse($args) as $a)
					array_unshift($arr->items, $a);
				$this->rt->checkArray($arr->items, $node);
				return count($arr->items);

			case 'pop':
				return empty($arr->items) ? NULL : array_pop($arr->items);
			case 'shift':
				return empty($arr->items) ? NULL : array_shift($arr->items);

			case 'reverse':
				$arr->items = array_reverse($arr->items);
				return $arr;
			case 'sort':
				$arr->items = $this->sortItems(
					$arr->items, $this->arg($args, 0, NULL), $node);
				return $arr;

			case 'join':
				return $this->join($arr, $args, $node);
			case 'concat':
				return $this->concat($arr, $args, $node);

			case 'slice':
				list($start, $end) = $this->sliceRange(
					count($arr->items), $args, $node, TRUE);
				return new Pkript_Arr(
					array_slice($arr->items, $start, $end - $start));

			case 'indexOf':
				return $this->indexOf($arr, $args, $node);
			case 'includes':
				$needle = $this->arg($args, 0, NULL);
				foreach ($arr->items as $item) {
					if (Pkript_Interpreter::strictEquals($item, $needle))
						return TRUE;
				}
				return FALSE;

			case 'map':
			case 'filter':
			case 'find':
			case 'findIndex':
			case 'forEach':
			case 'some':
			case 'every':
				return $this->iterate($arr, $name, $args, $node);

			case 'reduce':
				return $this->reduce($arr, $args, $node);
		}
	}

	private function join($arr, $args, $node) {
		$sep = array_key_exists(0, $args)
			? Pkript_Interpreter::toStringValue($args[0]) : ',';
		// Seeded with the array itself: an item pointing back at it writes nothing
		$seen = array(spl_object_id($arr) => TRUE);

		$parts = array();
		$length = 0;
		foreach ($arr->items as $item) {
			$part = Pkript_Interpreter::toStringValue($item, $seen);
			// As we go: the finished string could be MAX_ARRAY x MAX_STRING
			$length += strlen($part) + strlen($sep);
			if ($length > PKRIPT_MAX_STRING)
				$this->rt->failStringTooLong($node);
			$parts[] = $part;
		}
		return $this->rt->checkString(implode($sep, $parts), $node);
	}

	private function concat($arr, $args, $node) {
		$items = $arr->items;
		foreach ($args as $a) {
			if ($a instanceof Pkript_Arr) {
				foreach ($a->items as $item)
					$items[] = $item;
			} else {
				$items[] = $a;
			}
		}
		return new Pkript_Arr($this->rt->checkArray($items, $node));
	}

	private function indexOf($arr, $args, $node) {
		$len = count($arr->items);
		$from = (int) $this->numArg($args, 1, $node, 0);
		if ($from < 0)
			$from = max(0, $len + $from);

		$needle = $this->arg($args, 0, NULL);
		for ($i = $from; $i < $len; $i++) {
			if (Pkript_Interpreter::strictEquals($arr->items[$i], $needle))
				return $i;
		}
		return -1;
	}

	/**
	 * Everything that walks the array with a callback, except reduce, whose
	 * callback has a different shape. The callback gets (item, index, array)
	 * as in JavaScript, over a snapshot: one that mutates the array cannot
	 * extend the loop.
	 *
	 * some / every stop as soon as the answer is known, as in JavaScript, so
	 * a callback with a side effect is not run over the whole array.
	 */
	private function iterate($arr, $name, $args, $node) {
		$callback = $this->arg($args, 0, NULL);
		$out = array();

		foreach ($arr->items as $i => $item) {
			$result = $this->rt->callValue(
				$callback, array($item, $i, $arr), $node);

			switch ($name) {
				case 'map':
					$out[] = $result;
					break;
				case 'filter':
					if (Pkript_Interpreter::toBool($result))
						$out[] = $item;
					break;
				case 'find':
					if (Pkript_Interpreter::toBool($result))
						return $item;
					break;
				case 'findIndex':
					if (Pkript_Interpreter::toBool($result))
						return $i;
					break;
				case 'some':
					if (Pkript_Interpreter::toBool($result))
						return TRUE;
					break;
				case 'every':
					if (!Pkript_Interpreter::toBool($result))
						return FALSE;
					break;
				case 'forEach':
					break;   // the result is not used
			}
		}

		if ($name === 'find')
			return NULL;
		if ($name === 'findIndex')
			return -1;
		if ($name === 'some')
			return FALSE;
		if ($name === 'every')
			return TRUE;    // vacuously true for an empty array, as in JS
		if ($name === 'forEach')
			return NULL;
		// filter can only shrink the array, so only map needs the check
		return new Pkript_Arr(
			$name === 'map' ? $this->rt->checkArray($out, $node) : $out);
	}

	/**
	 * Fold the array into one value. The callback gets
	 * (accumulator, item, index, array).
	 *
	 * Without an initial value the first element is the accumulator and the
	 * walk starts at the second, which is why an empty array has no answer to
	 * give and fails - the same case JavaScript throws a TypeError for.
	 */
	private function reduce($arr, $args, $node) {
		$callback = $this->arg($args, 0, NULL);
		$items = $arr->items;
		$len = count($items);

		if (array_key_exists(1, $args)) {
			$acc = $args[1];
			$i = 0;
		} else {
			if ($len === 0)
				$this->rt->fail('空の配列は初期値なしで reduce できません', $node);
			$acc = $items[0];
			$i = 1;
		}

		for (; $i < $len; $i++) {
			$acc = $this->rt->callValue(
				$callback, array($acc, $items[$i], $i, $arr), $node);
		}
		return $acc;
	}

	/**
	 * In place, like JavaScript: no comparator means compare as strings.
	 * usort() has been stable since PHP 8.0, which is what ES2019 requires.
	 */
	private function sortItems($items, $compare, $node) {
		if ($compare === NULL) {
			usort($items, function ($a, $b) {
				return strcmp(
					Pkript_Interpreter::toStringValue($a),
					Pkript_Interpreter::toStringValue($b));
			});
			return $items;
		}

		$rt = $this->rt;
		usort($items, function ($a, $b) use ($rt, $compare, $node) {
			$n = $rt->toNumber($rt->callValue($compare, array($a, $b), $node), $node);
			// A comparator returning 0.5 must not collapse to 0
			return $n < 0 ? -1 : ($n > 0 ? 1 : 0);
		});
		return $items;
	}
}

<?php
// $Id: array_methods.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

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
			'forEach', 'some', 'every', 'reduce', 'reduceRight', 'sort',
			'at', 'lastIndexOf', 'fill', 'flat', 'flatMap',
			'findLast', 'findLastIndex', 'splice', 'toString',
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
			case 'reduceRight':
				return $this->reduce($arr, $name === 'reduceRight', $args, $node);

			case 'at':
				$i = (int) $this->numArg($args, 0, $node, 0);
				if ($i < 0)
					$i += count($arr->items);
				return array_key_exists($i, $arr->items) ? $arr->items[$i] : NULL;

			case 'lastIndexOf':
				$needle = $this->arg($args, 0, NULL);
				for ($i = count($arr->items) - 1; $i >= 0; $i--) {
					if (Pkript_Interpreter::strictEquals($arr->items[$i], $needle))
						return $i;
				}
				return -1;

			case 'findLast':
			case 'findLastIndex':
				return $this->findLast($arr, $name === 'findLast', $args, $node);

			case 'fill':
				return $this->fill($arr, $args, $node);
			case 'flat':
				return $this->flat($arr, $args, $node);
			case 'flatMap':
				return $this->flat(
					$this->iterate($arr, 'map', $args, $node),
					array(), $node);
			case 'splice':
				return $this->splice($arr, $args, $node);

			case 'toString':
				return $this->join($arr, array(), $node);
		}
	}

	/** Walks from the end and stops at the first hit, as in JavaScript. */
	private function findLast($arr, $wantItem, $args, $node) {
		$callback = $this->arg($args, 0, NULL);
		$items = $arr->items;
		for ($i = count($items) - 1; $i >= 0; $i--) {
			$hit = $this->rt->callValue(
				$callback, array($items[$i], $i, $arr), $node);
			if (Pkript_Interpreter::toBool($hit))
				return $wantItem ? $items[$i] : $i;
		}
		return $wantItem ? NULL : -1;
	}

	/** In place, like JavaScript, over the existing indexes only. */
	private function fill($arr, $args, $node) {
		$value = $this->arg($args, 0, NULL);
		$len = count($arr->items);
		list($start, $end) = $this->sliceRange(
			$len, array_slice($args, 1), $node, TRUE);
		for ($i = $start; $i < $end; $i++)
			$arr->items[$i] = $value;
		return $arr;
	}

	/**
	 * @param array $args arg 0 is the depth, defaulting to 1 as in JS.
	 *                    Infinity means all the way down, which here is
	 *                    PKRIPT_MAX_ARRAY levels - deeper than a script under
	 *                    the array limit can nest without sharing a value.
	 */
	private function flat($arr, $args, $node) {
		$depth = 1;
		if (array_key_exists(0, $args)) {
			$n = $this->rt->toNumber($args[0], $node);
			$depth = is_float($n) && !is_finite($n) && $n > 0
				? PKRIPT_MAX_ARRAY : (int) $n;
		}
		$out = array();
		$this->flatInto($arr, $depth, $out, $node, array());
		return new Pkript_Arr($this->rt->checkArray($out, $node));
	}

	/**
	 * @param array $onPath ids of the arrays being flattened right now, so
	 *                      `a.push(a); a.flat(Infinity)` stops instead of
	 *                      running off the PHP stack
	 */
	private function flatInto($arr, $depth, &$out, $node, $onPath) {
		$onPath[spl_object_id($arr)] = TRUE;
		foreach ($arr->items as $item) {
			if ($depth > 0 && $item instanceof Pkript_Arr &&
			    !isset($onPath[spl_object_id($item)])) {
				$this->flatInto($item, $depth - 1, $out, $node, $onPath);
			} else {
				$out[] = $item;
				$this->rt->checkArray($out, $node);
			}
		}
	}

	/**
	 * In place: remove $count items from $start, put the rest of the
	 * arguments there instead, and answer with what was removed.
	 */
	private function splice($arr, $args, $node) {
		$len = count($arr->items);
		$start = (int) $this->numArg($args, 0, $node, 0);
		if ($start < 0)
			$start = max(0, $len + $start);
		$start = min($start, $len);

		// No second argument means to the end, which is not the same as 0
		$count = array_key_exists(1, $args)
			? max(0, (int) $this->rt->toNumber($args[1], $node)) : $len - $start;

		$removed = array_slice($arr->items, $start, $count);
		array_splice($arr->items, $start, $count, array_slice($args, 2));
		$this->rt->checkArray($arr->items, $node);
		return new Pkript_Arr($removed);
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
	 *
	 * @param bool $fromEnd reduceRight, which walks the other way but hands
	 *                      the callback the same (acc, item, index, array)
	 */
	private function reduce($arr, $fromEnd, $args, $node) {
		$callback = $this->arg($args, 0, NULL);
		$items = $arr->items;
		$len = count($items);
		$step = $fromEnd ? -1 : 1;

		if (array_key_exists(1, $args)) {
			$acc = $args[1];
			$i = $fromEnd ? $len - 1 : 0;
		} else {
			if ($len === 0)
				$this->rt->fail('Reduce of empty array with no initial value', $node);
			$i = $fromEnd ? $len - 1 : 0;
			$acc = $items[$i];
			$i += $step;
		}

		for (; $i >= 0 && $i < $len; $i += $step) {
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

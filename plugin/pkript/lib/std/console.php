<?php
// $Id: console.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - console namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * A debug log, not a way to write to the page.
 *
 * Nothing here outputs anything by itself: a call appends one line to the
 * interpreter's buffer, and the entry point renders the buffer after the
 * script's return value, through the same helper as an error message. Two
 * reasons it is not an echo:
 *
 *  - the sanitizer has exactly one way in, the entry point's return value
 *    (9.3.2). An immediate write would be a second one.
 *  - a plugin returns its HTML, so anything echoed lands ahead of it -
 *    console.log("a") before a return would print after nothing it was
 *    written next to.
 *
 * The buffer fills whether or not PKRIPT_DEBUG is on, so turning it off
 * changes what is shown and not how long a script takes to run.
 */
class Pkript_Std_Console extends Pkript_Std_Module {
	/** How deep an object or array is spelled out before it becomes [...]. */
	const MAX_DEPTH = 3;

	public static function members() {
		return array('log', 'warn', 'error');
	}

	public function call($name, $args, $node) {
		$parts = array();
		foreach ($args as $arg)
			$parts[] = $this->format($arg, 0, array());

		$this->rt->log($name, implode(' ', $parts));
		return NULL;
	}

	/**
	 * What a value looks like in the log. Deliberately not toStringValue():
	 * every object there is '[object Object]', which is the one thing a
	 * debug line must not say.
	 *
	 * @param array $seen containers being written, so a cycle ends
	 */
	private function format($v, $depth, $seen) {
		if (is_string($v))
			return $v;
		if ($v === NULL)
			return 'null';
		if ($v instanceof Pkript_Func || $v instanceof Pkript_Builtin)
			return '[function]';

		if ($v instanceof Pkript_Arr || $v instanceof Pkript_Obj) {
			$id = spl_object_id($v);
			if (isset($seen[$id]))
				return '[circular]';
			if ($depth >= self::MAX_DEPTH)
				return $v instanceof Pkript_Arr ? '[...]' : '{...}';
			$seen[$id] = TRUE;

			$parts = array();
			if ($v instanceof Pkript_Arr) {
				foreach ($v->items as $item)
					$parts[] = $this->quote($item, $depth + 1, $seen);
				return '[' . implode(', ', $parts) . ']';
			}
			foreach ($v->props as $key => $value)
				$parts[] = $key . ': ' . $this->quote($value, $depth + 1, $seen);
			return '{' . implode(', ', $parts) . '}';
		}

		return Pkript_Interpreter::toStringValue($v);
	}

	/** Inside a container a string is quoted, so [""] does not read as []. */
	private function quote($v, $depth, $seen) {
		$out = $this->format($v, $depth, $seen);
		return is_string($v) ? '"' . $out . '"' : $out;
	}
}

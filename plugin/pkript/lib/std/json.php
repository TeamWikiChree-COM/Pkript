<?php
// $Id: json.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - JSON namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Std_Json extends Pkript_Std_Module {
	// Stands for "this value has no JSON form". A string no script can
	// produce, because it is not a value the language can build.
	const SKIP = "\x00pkript-json-skip";

	public static function members() {
		return array('stringify', 'parse');
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'stringify':
				return $this->stringify(
					$this->arg($args, 0, NULL), $this->arg($args, 1, 0), $node);
			case 'parse':
				return $this->parse($this->strArg($args, 0), $node);
		}
	}

	/////////////////////////////////////////////
	// Out

	/**
	 * Objects and arrays go out as themselves; a function has no JSON form,
	 * so it is dropped from an object and becomes null in an array, the way
	 * JavaScript's own JSON.stringify() treats one.
	 *
	 * @param mixed $indent spaces per level. 0 (the default) for one line
	 */
	public function stringify($value, $indent, $node) {
		$plain = $this->encodeValue($value, 0, array(), $node);

		$width = is_bool($indent) ? ($indent ? 4 : 0)
			: (int) $this->rt->toNumber($indent, $node);
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		if ($width > 0)
			$flags |= JSON_PRETTY_PRINT;

		$out = json_encode($plain, $flags);
		if ($out === FALSE)
			$this->rt->fail('Cannot convert to JSON', $node);
		if ($width > 0 && $width !== 4)
			$out = self::reindent($out, $width);
		return $this->rt->checkString($out, $node);
	}

	/** A Pkript value as something json_encode() understands. */
	private function encodeValue($value, $depth, $seen, $node) {
		if ($depth > PKRIPT_MAX_DEPTH) {
			$this->rt->fail('JSON nesting too deep (limit ' .
				PKRIPT_MAX_DEPTH . ')', $node);
		}

		if (is_string($value))
			return Pkript_Interpreter::stripHtmlMarks($value);
		if ($value === NULL || is_bool($value) || is_int($value))
			return $value;
		if (is_float($value))
			return (is_nan($value) || is_infinite($value)) ? NULL : $value;

		if ($value instanceof Pkript_Arr || $value instanceof Pkript_Obj) {
			$id = spl_object_id($value);
			if (isset($seen[$id]))
				$this->rt->fail('JSON has a cycle', $node);
			$seen[$id] = TRUE;

			return $value instanceof Pkript_Arr
				? $this->encodeArr($value, $depth, $seen, $node)
				: $this->encodeObj($value, $depth, $seen, $node);
		}

		return self::SKIP;   // a function of some kind
	}

	private function encodeArr($arr, $depth, $seen, $node) {
		$out = array();
		foreach ($arr->items as $item) {
			$item = $this->encodeValue($item, $depth + 1, $seen, $node);
			$out[] = $item === self::SKIP ? NULL : $item;
		}
		return $out;
	}

	private function encodeObj($obj, $depth, $seen, $node) {
		// An object with no properties has to encode as {}, not [], so the
		// empty case cannot go through a PHP array
		$out = new stdClass();
		foreach ($obj->props as $key => $prop) {
			$prop = $this->encodeValue($prop, $depth + 1, $seen, $node);
			if ($prop !== self::SKIP)
				$out->{(string) $key} = $prop;
		}
		return $out;
	}

	/**
	 * JSON_PRETTY_PRINT indents with four spaces and has no setting for it.
	 * Only the indentation of a line can be spaces, since every newline inside
	 * a string is escaped, so rewriting the leading run is safe.
	 */
	private static function reindent($json, $width) {
		$pad = str_repeat(' ', min($width, 10));
		return preg_replace_callback('/^(?: {4})+/m', function ($m) use ($pad) {
			return str_repeat($pad, strlen($m[0]) / 4);
		}, $json);
	}

	/////////////////////////////////////////////
	// In

	public function parse($text, $node) {
		if (trim($text) === '')
			$this->rt->fail('JSON is empty', $node);

		$data = json_decode($text, FALSE, min(PKRIPT_MAX_DEPTH, 512));
		if ($data === NULL && strtolower(trim($text)) !== 'null')
			$this->rt->fail('Cannot parse as JSON', $node);

		return $this->decodeValue($data, $node);
	}

	private function decodeValue($value, $node) {
		if (is_array($value)) {
			$items = array();
			foreach ($value as $item)
				$items[] = $this->decodeValue($item, $node);
			return new Pkript_Arr($this->rt->checkArray($items, $node));
		}
		if ($value instanceof stdClass) {
			$obj = new Pkript_Obj();
			foreach (get_object_vars($value) as $key => $prop)
				$obj->props[(string) $key] = $this->decodeValue($prop, $node);
			$this->rt->checkArray($obj->props, $node);
			return $obj;
		}
		if (is_string($value)) {
			return $this->rt->checkString(
				Pkript_Interpreter::stripHtmlMarks($value), $node);
		}
		if (is_float($value) && $value == (int) $value && abs($value) < 1e15)
			return (int) $value;   // 1.0 came from "1"; keep it an integer
		return $value;
	}
}

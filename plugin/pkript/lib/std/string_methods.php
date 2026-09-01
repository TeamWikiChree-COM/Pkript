<?php
// $Id: string_methods.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - String methods
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Indexes and lengths are in characters, not bytes, so a script sees the same
 * numbers JavaScript would. The limit checks are in bytes, because that is
 * what the memory actually costs.
 */
class Pkript_Std_StringMethods extends Pkript_Std_Methods {
	public static function methods() {
		return array(
			'toUpperCase', 'toLowerCase', 'trim', 'trimStart', 'trimEnd',
			'indexOf', 'lastIndexOf', 'includes', 'startsWith', 'endsWith',
			'replace', 'replaceAll', 'split', 'substring', 'slice',
			'charAt', 'at', 'padStart', 'padEnd', 'repeat',
			'match', 'matchAll', 'search',
			'concat', 'charCodeAt', 'codePointAt', 'localeCompare',
			'toString', 'valueOf',
			'spanWhile', 'spanUntil',
		);
	}

	public function call($s, $name, $args, $node) {
		$enc = PKRIPT_ENCODING;
		switch ($name) {
			case 'toUpperCase':
				return mb_strtoupper($s, $enc);
			case 'toLowerCase':
				return mb_strtolower($s, $enc);
			case 'trim':
				return trim($s);
			case 'trimStart':
				return ltrim($s);
			case 'trimEnd':
				return rtrim($s);

			case 'indexOf':
				return $this->indexOf($s, $args, $node);
			case 'lastIndexOf':
				$needle = $this->strArg($args, 0);
				if ($needle === '')
					return mb_strlen($s, $enc);
				$at = mb_strrpos($s, $needle, 0, $enc);
				return $at === FALSE ? -1 : $at;

			case 'includes':
				$needle = $this->strArg($args, 0);
				return $needle === '' ||
					mb_strpos($s, $needle, 0, $enc) !== FALSE;
			case 'startsWith':
				return strpos($s, $this->strArg($args, 0)) === 0;
			case 'endsWith':
				$suffix = $this->strArg($args, 0);
				return $suffix === '' || substr($s, -strlen($suffix)) === $suffix;

			case 'replace':
			case 'replaceAll':
				return $this->replace($s, $name === 'replaceAll', $args, $node);

			case 'split':
				$sep = $this->arg($args, 0, '');
				if ($sep instanceof Pkript_Regex)
					return $this->regex()->split($sep, $s, $node);
				$sep = Pkript_Interpreter::stripHtmlMarks(
					Pkript_Interpreter::toStringValue($sep));
				$parts = $sep === '' ? mb_str_split($s, 1, $enc) : explode($sep, $s);
				return new Pkript_Arr($this->rt->checkArray($parts, $node));

			// Regex only: there is no sensible plain-string reading of these
			case 'match':
				return $this->regex()->firstMatch(
					$this->arg($args, 0, NULL), $s, $node);
			case 'matchAll':
				return $this->regex()->allMatches(
					$this->arg($args, 0, NULL), $s, $node);
			case 'search':
				return $this->regex()->search(
					$this->arg($args, 0, NULL), $s, $node);

			case 'substring':
			case 'slice':
				list($start, $end) = $this->sliceRange(
					mb_strlen($s, $enc), $args, $node, $name === 'slice');
				return mb_substr($s, $start, $end - $start, $enc);

			case 'charAt':
			case 'at':
				return $this->charAt($s, $name === 'at', $args, $node);

			case 'padStart':
			case 'padEnd':
				return $this->pad($s, $name === 'padStart', $args, $node);

			case 'repeat':
				return $this->repeat($s, $args, $node);

			case 'concat':
				foreach ($args as $a)
					$s .= Pkript_Interpreter::toStringValue($a);
				return $this->rt->checkString($s, $node);

			case 'charCodeAt':
			case 'codePointAt':
				// One code point either way: the runtime keeps text as UTF-8
				// and has no UTF-16 halves to hand back separately
				$i = (int) $this->numArg($args, 0, $node, 0);
				if ($i < 0 || $i >= mb_strlen($s, $enc))
					return NAN;
				$points = array_values(unpack('N*',
					mb_convert_encoding(mb_substr($s, $i, 1, $enc), 'UCS-4BE', $enc)));
				return $points[0];

			case 'localeCompare':
				// Byte order, as everything else here compares: PHP has no
				// collation to offer that does not depend on the server's locale
				$other = $this->strArg($args, 0);
				return $s < $other ? -1 : ($s > $other ? 1 : 0);

			case 'toString':
			case 'valueOf':
				return $s;

			case 'spanWhile':
			case 'spanUntil':
				return $this->span($s, $name === 'spanWhile', $args, $node);
		}
	}

	private function indexOf($s, $args, $node) {
		$enc = PKRIPT_ENCODING;
		$len = mb_strlen($s, $enc);
		$from = (int) $this->numArg($args, 1, $node, 0);
		$from = max(0, min($from, $len));

		$needle = $this->strArg($args, 0);
		if ($needle === '')
			return $from;
		$at = mb_strpos($s, $needle, $from, $enc);
		return $at === FALSE ? -1 : $at;
	}

	/** @param bool $all replaceAll; otherwise only the first occurrence */
	private function regex() {
		return new Pkript_Regex_Runner($this->rt);
	}

	/**
	 * The first argument decides which kind of replace this is: a regex
	 * replaces what it matches, a string replaces itself. replaceAll on a
	 * pattern without /g still replaces every match, which is the reading its
	 * name asks for.
	 */
	private function replace($s, $all, $args, $node) {
		$from = $this->arg($args, 0, '');
		if ($from instanceof Pkript_Regex) {
			return $this->regex()->replace(
				$from, $s, $this->strArg($args, 1), $all, $node);
		}

		$from = Pkript_Interpreter::stripHtmlMarks(
			Pkript_Interpreter::toStringValue($from));
		if ($from === '')
			return $s;
		$to = $this->strArg($args, 1);

		if ($all)
			return $this->rt->checkString(str_replace($from, $to, $s), $node);

		$pos = strpos($s, $from);
		if ($pos === FALSE)
			return $s;
		return $this->rt->checkString(
			substr_replace($s, $to, $pos, strlen($from)), $node);
	}

	/** @param bool $fromEnd `at`, where -1 is the last character as in JS */
	private function charAt($s, $fromEnd, $args, $node) {
		$enc = PKRIPT_ENCODING;
		$len = mb_strlen($s, $enc);
		$i = (int) $this->numArg($args, 0, $node, 0);
		if ($fromEnd && $i < 0)
			$i += $len;
		if ($i < 0 || $i >= $len)
			return '';
		return mb_substr($s, $i, 1, $enc);
	}

	private function repeat($s, $args, $node) {
		$n = (int) $this->numArg($args, 0, $node, 0);
		if ($n < 0)
			$this->rt->fail('repeat count is negative', $node);
		// Checked before str_repeat() so the big string is never built
		if ($n > 0 && strlen($s) * $n > PKRIPT_MAX_STRING)
			$this->rt->failStringTooLong($node);
		return str_repeat($s, $n);
	}

	/**
	 * Pad to a length in characters, repeating the pad string and cutting the
	 * last repeat short, as JavaScript does. A string already that long comes
	 * back unchanged; padding is never truncation.
	 */
	private function pad($s, $atStart, $args, $node) {
		$enc = PKRIPT_ENCODING;
		$target = (int) $this->numArg($args, 0, $node, 0);
		$pad = $this->strArg($args, 1, ' ');
		$len = mb_strlen($s, $enc);

		if ($pad === '' || $target <= $len)
			return $s;
		// Checked before the pad is built, so the big string is never made
		if ($target > PKRIPT_MAX_STRING)
			$this->rt->failStringTooLong($node);

		$need = $target - $len;
		$fill = mb_substr(
			str_repeat($pad, (int) ceil($need / mb_strlen($pad, $enc))),
			0, $need, $enc);
		return $this->rt->checkString($atStart ? $fill . $s : $s . $fill, $node);
	}

	/**
	 * How far a run of characters reaches: `spanWhile` walks while the char is
	 * in $set, `spanUntil` while it is not. Returns the index it stopped at.
	 * Replaces a scanner's inner loop, which costs 50-60 steps per character.
	 */
	private function span($s, $inSet, $args, $node) {
		$enc = PKRIPT_ENCODING;
		$from = max(0, (int) $this->numArg($args, 0, $node, 0));
		$set = $this->strArg($args, 1);
		$len = mb_strlen($s, $enc);

		if ($from >= $len)
			return $len;
		if ($set === '')
			return $inSet ? $from : $len;

		$table = self::charSet($set);
		// Character indexes are byte indexes when nothing is multibyte, which
		// is the case a scanner is usually in
		$chars = strlen($s) === $len ? $s : mb_str_split($s, 1, $enc);

		$i = $from;
		while ($i < $len && isset($table[$chars[$i]]) === $inSet)
			$i++;
		return $i;
	}

	/**
	 * The characters of a set string, with `a-z` ranges expanded. To include a
	 * literal '-', put it first or last. Only ASCII takes part in a range.
	 */
	private static function charSet($set) {
		static $cache = array();
		if (isset($cache[$set]))
			return $cache[$set];

		$table = array();
		$n = strlen($set);
		for ($i = 0; $i < $n; $i++) {
			if ($i + 2 < $n && $set[$i + 1] === '-' && ord($set[$i]) <= ord($set[$i + 2])) {
				for ($c = ord($set[$i]); $c <= ord($set[$i + 2]); $c++)
					$table[chr($c)] = TRUE;
				$i += 2;
				continue;
			}
			$table[$set[$i]] = TRUE;
		}

		// A script could ask for a new set on every iteration
		if (count($cache) >= 32)
			$cache = array();
		return $cache[$set] = $table;
	}
}

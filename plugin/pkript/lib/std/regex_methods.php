<?php
// $Id: regex_methods.php,v 0.3 2026/08/31 11:06:32 WikiChree.COM Team Exp $

/**
 * Pkript runtime - RegExp methods
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * What a regex value itself can do. The interesting operations - replace,
 * split, match - read better on the string and live in Pkript_Std_StringMethods;
 * these are the two that read better on the pattern.
 */
class Pkript_Std_RegexMethods extends Pkript_Std_Methods {
	public static function methods() {
		return array('test', 'exec', 'source', 'flags', 'global');
	}

	public function call($re, $name, $args, $node) {
		switch ($name) {
			case 'test':
				return $this->runner()->test($re, $this->strArg($args, 0), $node);

			// The parts of the literal, so a script can show what it matched
			// with. Written as calls because this runtime has no properties
			// on anything but objects.
			case 'source':
				return $re->source;
			case 'flags':
				return $re->flags;
			case 'global':
				return $re->isGlobal();

			case 'exec':
				return $this->runner()->firstMatch(
					$re, $this->strArg($args, 0), $node);
		}
	}

	private function runner() {
		return new Pkript_Regex_Runner($this->rt);
	}
}

/**
 * Every PCRE call the standard library makes goes through here, so the
 * backtrack guard and the budget are applied in one place rather than at each
 * call site.
 */
class Pkript_Regex_Runner {
	private $rt;

	public function __construct($rt) {
		$this->rt = $rt;
	}

	/**
	 * A pattern that gives up is a resource limit, not a failed match: it is
	 * reported like the step and time limits and, like them, cannot be caught.
	 * Returning FALSE instead would tell the script "no match", which is a
	 * lie that silently changes what a page says.
	 */
	private function run($fn, $node) {
		list($result, $error) = Pkript_Regex::guarded($fn);
		if ($error !== '')
			$this->rt->failLimit($error, $node);
		return $result;
	}

	private function want($value, $node) {
		if (!($value instanceof Pkript_Regex)) {
			$this->rt->fail(Pkript_Interpreter::typeName($value) .
				' は正規表現ではありません', $node);
		}
		return $value;
	}

	public function test($re, $subject, $node) {
		$re = $this->want($re, $node);
		$pattern = $re->pattern();
		return (bool) $this->run(function () use ($pattern, $subject) {
			return preg_match($pattern, $subject);
		}, $node);
	}

	/**
	 * The first match as an array: the whole match, then each group. A group
	 * that did not take part is an empty string, because this language has no
	 * undefined to tell it apart with.
	 *
	 * @return Pkript_Arr|NULL NULL when nothing matched, as in JavaScript
	 */
	public function firstMatch($re, $subject, $node) {
		$re = $this->want($re, $node);
		$pattern = $re->pattern();

		// What JavaScript's String.match does with /g: the whole of every
		// match, and no groups. matchAll is the one that keeps the groups.
		if ($re->isGlobal()) {
			$found = array();
			$this->run(function () use ($pattern, $subject, &$found) {
				return preg_match_all($pattern, $subject, $found);
			}, $node);
			if (empty($found[0]))
				return NULL;
			return new Pkript_Arr($this->rt->checkArray($found[0], $node));
		}

		$m = array();
		$found = $this->run(function () use ($pattern, $subject, &$m) {
			return preg_match($pattern, $subject, $m);
		}, $node);

		if (!$found)
			return NULL;
		return new Pkript_Arr($this->rt->checkArray(array_values($m), $node));
	}

	/** Every match, each one an array shaped like firstMatch()'s. */
	public function allMatches($re, $subject, $node) {
		$re = $this->want($re, $node);
		$pattern = $re->pattern();
		$sets = array();
		$this->run(function () use ($pattern, $subject, &$sets) {
			return preg_match_all($pattern, $subject, $sets, PREG_SET_ORDER);
		}, $node);

		$out = array();
		foreach ($sets as $set)
			$out[] = new Pkript_Arr(array_values($set));
		return new Pkript_Arr($this->rt->checkArray($out, $node));
	}

	/** Where the first match starts, in characters, or -1. */
	public function search($re, $subject, $node) {
		$re = $this->want($re, $node);
		$pattern = $re->pattern();
		$m = array();
		$found = $this->run(function () use ($pattern, $subject, &$m) {
			return preg_match($pattern, $subject, $m, PREG_OFFSET_CAPTURE);
		}, $node);

		if (!$found)
			return -1;
		// preg gives a byte offset; every other index in this runtime counts
		// characters, so convert before it is handed over
		return mb_strlen(substr($subject, 0, $m[0][1]), SOURCE_ENCODING);
	}

	/**
	 * @param bool $all replace every match, whatever the pattern's /g says
	 */
	public function replace($re, $subject, $replacement, $all, $node) {
		$re = $this->want($re, $node);
		$pattern = $re->pattern();
		$with = self::replacementFor($replacement);
		$limit = ($all || $re->isGlobal()) ? -1 : 1;

		$out = $this->run(function () use ($pattern, $with, $subject, $limit) {
			return preg_replace($pattern, $with, $subject, $limit);
		}, $node);

		if ($out === NULL)
			$this->rt->fail('正規表現の置換に失敗しました', $node);
		return $this->rt->checkString($out, $node);
	}

	public function split($re, $subject, $node) {
		$re = $this->want($re, $node);
		$pattern = $re->pattern();
		$parts = $this->run(function () use ($pattern, $subject) {
			return preg_split($pattern, $subject);
		}, $node);

		if ($parts === FALSE)
			$this->rt->fail('正規表現の分割に失敗しました', $node);
		return new Pkript_Arr($this->rt->checkArray($parts, $node));
	}

	/**
	 * A JavaScript replacement string as a PCRE one.
	 *
	 * `$&` is the whole match there and `$0` here; `$$` is a literal dollar in
	 * both. A backslash means nothing in a JavaScript replacement but
	 * everything in a PCRE one, so it is escaped rather than passed on - a
	 * script writing `\1` gets the two characters it wrote.
	 */
	private static function replacementFor($text) {
		$text = str_replace('\\', '\\\\', $text);

		$out = '';
		$n = strlen($text);
		for ($i = 0; $i < $n; $i++) {
			if ($text[$i] !== '$' || $i + 1 >= $n) {
				$out .= $text[$i];
				continue;
			}

			$next = $text[$i + 1];
			if ($next === '&') {
				$out .= '${0}';
				$i++;
				continue;
			}
			if ($next === '$') {
				$out .= '\\$';   // a dollar PCRE must not read as a group
				$i++;
				continue;
			}
			// ${1} says where the group number stops, so a replacement can
			// put a digit straight after one
			if ($next === '{' && preg_match('/^\$\{([0-9]{1,2})\}/',
					substr($text, $i), $m)) {
				$out .= '${' . (int) $m[1] . '}';
				$i += strlen($m[0]) - 1;
				continue;
			}
			if (ctype_digit($next)) {
				// Braced, so $12 stays group 12 and $1 followed by a digit
				// in the text does not become one
				$digits = '';
				$j = $i + 1;
				while ($j < $n && ctype_digit($text[$j]) && strlen($digits) < 2)
					$digits .= $text[$j++];
				$out .= '${' . (int) $digits . '}';
				$i = $j - 1;
				continue;
			}
			$out .= '\\$';
		}
		return $out;
	}
}

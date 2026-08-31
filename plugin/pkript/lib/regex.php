<?php
// $Id: regex.php,v 0.3 2026/08/31 11:06:32 WikiChree.COM Team Exp $

/**
 * Pkript runtime - regular expressions
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// Regular expressions
//
// A regex is the one place a script can hand PCRE something to work on, so it
// is the one place a script can spend far more time than the number of steps
// it took to get there. Two things hold that down, and both matter:
//
//   - the pattern is checked before it is ever compiled, and the constructs
//     that make PCRE do something other than match - recursion, subroutine
//     calls, callouts - are refused outright;
//   - every match runs under a backtrack limit far below PHP's own, and
//     hitting it is reported as a resource limit rather than as "no match",
//     so a catastrophic pattern stops the script instead of quietly lying.
//
// The delimiter is always '/', and it never needs escaping: a regex literal
// ends at its first unescaped '/', so the source cannot contain one.

class Pkript_Regex {
	/** As written in the script, between the slashes. */
	public $source;

	/** As written after the closing slash. */
	public $flags;

	public function __construct($source, $flags) {
		$this->source = $source;
		$this->flags = $flags;
	}

	/** JavaScript's /g: does this pattern act on every match, or just one? */
	public function isGlobal() {
		return strpos($this->flags, 'g') !== FALSE;
	}

	/**
	 * The PCRE pattern.
	 *
	 * 'u' is always on: the wiki is UTF-8, and without it a dot would match
	 * half a character. 'g' has no PCRE modifier - it decides which function
	 * the caller uses, not how the pattern compiles.
	 */
	public function pattern() {
		$mods = 'u';
		foreach (array('i', 'm', 's') as $flag) {
			if (strpos($this->flags, $flag) !== FALSE)
				$mods .= $flag;
		}
		return '/' . $this->source . '/' . $mods;
	}

	public function __toString() {
		return '/' . $this->source . '/' . $this->flags;
	}

	/////////////////////////////////////////////
	// Checking

	// Flags a script may write. JavaScript's 'y' (sticky) is left out because
	// nothing here exposes lastIndex for it to move.
	const FLAGS = 'gims';

	/**
	 * Why this pattern may not be used, or '' if it may.
	 *
	 * Called when the script is parsed, so a bad pattern is an error before
	 * anything runs, and a cached AST is only ever built from a pattern that
	 * passed.
	 */
	public static function check($source, $flags) {
		if (strlen($source) > PKRIPT_MAX_REGEX) {
			return '正規表現が長すぎます (上限 ' . PKRIPT_MAX_REGEX . 'バイト)';
		}

		$seen = array();
		$n = strlen($flags);
		for ($i = 0; $i < $n; $i++) {
			$flag = $flags[$i];
			if (strpos(self::FLAGS, $flag) === FALSE)
				return '正規表現のフラグ ' . $flag . ' は使えません';
			if (isset($seen[$flag]))
				return '正規表現のフラグ ' . $flag . ' が重複しています';
			$seen[$flag] = TRUE;
		}

		$refused = self::refusedConstruct($source);
		if ($refused !== '')
			return '正規表現で ' . $refused . ' は使えません';

		$re = new self($source, $flags);
		// Compiling is the only complete check; a warning here would be the
		// PHP notice for a bad pattern, which the script must not see
		if (@preg_match($re->pattern(), '') === FALSE)
			return '正規表現が不正です';

		return '';
	}

	/**
	 * The first construct in $source that is not allowed, or ''.
	 *
	 * Group syntax is a whitelist: a `(?` has to be one of the forms below.
	 * That refuses recursion `(?R)`, subroutine calls `(?1)` `(?&name)` and
	 * callouts `(?C)` without having to name each of them, and it refuses
	 * whatever PCRE grows next as well.
	 */
	private static function refusedConstruct($source) {
		$len = strlen($source);
		$inClass = FALSE;

		for ($i = 0; $i < $len; $i++) {
			$ch = $source[$i];

			if ($ch === '\\') {
				// \K moves where the match started, which every offset this
				// runtime reports would then be wrong about
				if ($i + 1 < $len && ($source[$i + 1] === 'K' || $source[$i + 1] === 'C'))
					return '\\' . $source[$i + 1];
				$i++;   // whatever it escapes is not syntax
				continue;
			}
			if ($inClass) {
				if ($ch === ']') $inClass = FALSE;
				continue;
			}
			if ($ch === '[') {
				$inClass = TRUE;
				continue;
			}
			if ($ch !== '(' || $i + 1 >= $len || $source[$i + 1] !== '?')
				continue;

			$rest = substr($source, $i);
			if (!preg_match('/^\(\?(?:' .
				'[:=!]' .                             // (?: (?= (?!
				'|<[=!]' .                            // (?<= (?<!
				'|P?<[A-Za-z_][A-Za-z0-9_]*>' .       // named groups
				"|P?'[A-Za-z_][A-Za-z0-9_]*'" .
				'|[imsx]*(?:-[imsx]+)?[:)]' .         // inline flags
				')/', $rest)
			) {
				// Enough of it to name in the message, no more
				return substr($rest, 0, 4);
			}
		}
		return '';
	}

	/////////////////////////////////////////////
	// Running

	/**
	 * Run one PCRE call with the backtrack limit lowered, then put the limit
	 * back. The limit is per call, so it cannot be spent across a loop: what
	 * it bounds is a single catastrophic match, which is the case a step
	 * counter never sees because it all happens inside one PHP call.
	 *
	 * @param callable $fn does the preg_* call
	 * @return array array($result, $errorMessage); one of the two is empty
	 */
	public static function guarded($fn) {
		$previous = ini_get('pcre.backtrack_limit');
		ini_set('pcre.backtrack_limit', (string) PKRIPT_REGEX_BACKTRACK);
		try {
			$result = $fn();
		} finally {
			ini_set('pcre.backtrack_limit', $previous);
		}

		$error = preg_last_error();
		if ($error === PREG_NO_ERROR)
			return array($result, '');

		return array(NULL, self::errorMessage($error));
	}

	private static function errorMessage($error) {
		switch ($error) {
			case PREG_BACKTRACK_LIMIT_ERROR:
				return '正規表現の処理量が上限を超えました (上限 ' .
					PKRIPT_REGEX_BACKTRACK . ')';
			case PREG_RECURSION_LIMIT_ERROR:
				return '正規表現の再帰が深すぎます';
			case PREG_BAD_UTF8_ERROR:
			case PREG_BAD_UTF8_OFFSET_ERROR:
				return '正規表現の対象がUTF-8として不正です';
			case PREG_JIT_STACKLIMIT_ERROR:
				return '正規表現の処理量が上限を超えました';
		}
		return '正規表現の実行に失敗しました';
	}
}

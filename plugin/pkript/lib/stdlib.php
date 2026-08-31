<?php
// $Id: stdlib.php,v 0.2 2026/08/31 11:06:32 WikiChree.COM Team Exp $

/**
 * Pkript runtime - standard library
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Everything a script can reach that is not language syntax.
 *
 * A trait rather than a class so it shares Pkript_Interpreter's private
 * helpers (fail / checkString / checkArray) without widening their visibility.
 */
trait Pkript_Stdlib
{
	// How far wiki.convert() may re-enter itself. A static property, not a
	// const, because traits could not hold constants before PHP 8.2.
	private static $maxConvertDepth = 3;

	// Page names for wiki.pages(), read once per run
	private $pageList = NULL;

	// Set by Pkript_Interpreter's constructor
	private $budget;

	/**
	 * PukiWiki's own function names, so code moved over from a PHP plugin works
	 * as written. Each is an alias, resolved by callBuiltin() before dispatch.
	 */
	private static $pukiwikiAliases = array(
		'htmlsc' => 'html.escape',
		'is_page' => 'wiki.exists',
		'make_pagelink' => 'wiki.link',
		'convert_html' => 'wiki.convert',
		'strip_bracket' => 'wiki.stripBracket',
		'get_source' => 'wiki.source',
		'get_existpages' => 'wiki.pages',
		'encode' => 'wiki.encode',
		'decode' => 'wiki.decode',
	);

	private static function globalFunctions()
	{
		return array_merge(
			// PukiWiki spellings, each an alias of its namespaced twin
			array_keys(self::$pukiwikiAliases),
			// PHP argument helpers
			array('func_get_args', 'func_num_args', 'func_get_arg'),
			// conversions
			array('String', 'Number', 'Boolean')
		);
	}

	/** The whole standard library: nothing outside this table is reachable. */
	private static function apiNamespaces()
	{
		return array(
			'html' => array('escape', 'br', 'strip'),
			'Math' => array('floor', 'ceil', 'round', 'abs', 'min', 'max', 'random'),
			'Object' => array('keys', 'values', 'has'),
			'wiki' => array(
				'exists',
				'link',
				'convert',
				'stripBracket',
				'encode',
				'decode',
				'source',
				'pages',
				'write',
				'append',
				'token',
				'canWrite'
			),
		);
	}

	/////////////////////////////////////////////
	// Standard library

	/** Read by both isMethod() (does `"a".foo` exist?) and callMethod(). */
	private static function methodTables()
	{
		return array(
			'String' => array(
				'call' => 'callStringMethod',
				'methods' => array(
					'toUpperCase',
					'toLowerCase',
					'trim',
					'indexOf',
					'includes',
					'startsWith',
					'endsWith',
					'replace',
					'replaceAll',
					'split',
					'substring',
					'slice',
					'charAt',
					'repeat',
					'spanWhile',
					'spanUntil',
				),
			),
			'Array' => array(
				'call' => 'callArrayMethod',
				'methods' => array(
					'push',
					'pop',
					'shift',
					'unshift',
					'join',
					'indexOf',
					'includes',
					'slice',
					'reverse',
					'concat',
					'map',
					'filter',
					'find',
					'findIndex',
					'sort',
				),
			),
			'Number' => array(
				'call' => 'callNumberMethod',
				'methods' => array('toFixed', 'toString'),
			),
		);
	}

	/** The name a script sees for a value's type, or NULL if it has no methods. */
	private static function methodTypeOf($value)
	{
		if (is_string($value))
			return 'String';
		if ($value instanceof Pkript_Arr)
			return 'Array';
		if (is_int($value) || is_float($value))
			return 'Number';
		return NULL;
	}

	/** @return array|NULL the table for this receiver, with its label folded in */
	private static function methodTableFor($value)
	{
		$label = self::methodTypeOf($value);
		if ($label === NULL)
			return NULL;
		$tables = self::methodTables();
		$table = $tables[$label];
		$table['label'] = $label;
		return $table;
	}

	private static function isMethod($value, $name)
	{
		$table = self::methodTableFor($value);
		return $table !== NULL && in_array($name, $table['methods'], TRUE);
	}

	private function arg($args, $i, $default = '')
	{
		return array_key_exists($i, $args) ? $args[$i] : $default;
	}

	private function strArg($args, $i, $default = '')
	{
		// stripHtmlMarks(): JSX output is a string like any other to a script,
		// but the marks it carries are an internal detail of assembling HTML
		// and have no business inside an API argument.
		return array_key_exists($i, $args)
			? self::stripHtmlMarks(self::toStringValue($args[$i]))
			: $default;
	}

	private function numArg($args, $i, $node, $default = 0)
	{
		return array_key_exists($i, $args) ? $this->toNumber($args[$i], $node) : $default;
	}

	/**
	 * Shared by String.slice / substring and Array.slice, clamped into 0..$len.
	 *
	 * @param bool $fromEnd a negative index counts back from the end
	 *                      (slice does, substring does not)
	 */
	private function sliceRange($len, $args, $node, $fromEnd)
	{
		$start = (int) $this->numArg($args, 0, $node, 0);
		if ($fromEnd && $start < 0)
			$start = max(0, $len + $start);

		$end = array_key_exists(1, $args) ? (int) $this->toNumber($args[1], $node) : $len;
		if ($fromEnd && $end < 0)
			$end = $len + $end;

		$start = max(0, min($start, $len));
		$end = max($start, min($end, $len));
		return array($start, $end);
	}

	/** Arguments of the call we are inside, for the func_get_*() helpers. */
	private function currentCallArgs()
	{
		$args = end($this->argsStack);
		return $args === FALSE ? array() : $args;
	}

	private function callBuiltin($name, $args, $node)
	{
		// Resolve a PukiWiki spelling to the name the switch below knows
		$canonical = isset(self::$pukiwikiAliases[$name])
			? self::$pukiwikiAliases[$name] : $name;

		switch ($canonical) {
			// --- html ---
			case 'html.escape':
				// ENT_QUOTES: PukiWiki's htmlsc() defaults to ENT_COMPAT,
				// which leaves ' alone
				return htmlsc($this->strArg($args, 0), ENT_QUOTES);
			case 'html.br':
				return nl2br($this->strArg($args, 0), TRUE);
			case 'html.strip':
				return strip_tags($this->strArg($args, 0));

			// --- Math ---
			case 'Math.floor':
				return (int) floor($this->numArg($args, 0, $node));
			case 'Math.ceil':
				return (int) ceil($this->numArg($args, 0, $node));
			case 'Math.round':
				return (int) round($this->numArg($args, 0, $node));
			case 'Math.abs':
				return abs($this->numArg($args, 0, $node));
			case 'Math.random':
				// 0 <= n < 1, like JavaScript. Not security grade: a script
				// must never make a token, password or id with this.
				return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();

			case 'Math.min':
			case 'Math.max':
				if (empty($args))
					$this->fail(substr($canonical, 5) . ' には引数が必要です', $node);
				$nums = array();
				foreach ($args as $a)
					$nums[] = $this->toNumber($a, $node);
				return $canonical === 'Math.min' ? min($nums) : max($nums);

			// --- Object ---
			case 'Object.keys':
			case 'Object.values':
			case 'Object.has':
				$obj = $this->arg($args, 0, NULL);
				if (!($obj instanceof Pkript_Obj)) {
					$this->fail(self::typeName($obj) . ' は Object ではありません', $node);
				}
				if ($canonical === 'Object.keys')
					return new Pkript_Arr(array_keys($obj->props));
				if ($canonical === 'Object.values')
					return new Pkript_Arr(array_values($obj->props));
				return array_key_exists($this->strArg($args, 1), $obj->props);

			// --- wiki ---
			case 'wiki.exists':
				$page = $this->strArg($args, 0);
				if ($page === '')
					return FALSE;
				$this->spendRead($node);
				return is_page($page);

			case 'wiki.link':
				return $this->wikiLink($this->strArg($args, 0), $this->strArg($args, 1, ''), $node);

			case 'wiki.convert':
				return $this->wikiConvert($this->strArg($args, 0), $node);

			case 'wiki.stripBracket':
				// '[[Page]]' -> 'Page'; anything else is returned unchanged
				$s = $this->strArg($args, 0);
				return preg_match('/^\[\[(.*)\]\]$/s', $s, $m) ? $m[1] : $s;

			case 'wiki.source':
				return $this->wikiSource($this->strArg($args, 0), $node);

			case 'wiki.pages':
				return new Pkript_Arr($this->wikiPages($this->strArg($args, 0, ''), $node));

			case 'wiki.token':
				return plugin_pkript_token();

			// So a script can render a read-only view instead of a form it
			// knows the write would be refused for
			case 'wiki.canWrite':
				return $this->writeRefusal($this->strArg($args, 0)) === '';

			case 'wiki.write':
			case 'wiki.append':
				return $this->wikiWrite(
					$this->strArg($args, 0),
					$this->strArg($args, 1),
					$canonical === 'wiki.append',
					$node
				);

			case 'wiki.encode':
				// Page name -> the upper-case hex PukiWiki uses for filenames
				$s = $this->strArg($args, 0);
				return $s === '' ? '' : strtoupper(bin2hex($s));

			case 'wiki.decode':
				$s = $this->strArg($args, 0);
				// Like PukiWiki's decode(): non-hex comes back untouched, and
				// pairs are required so pack() never silently pads.
				if (!preg_match('/^([0-9a-f]{2})+$/i', $s))
					return $s;
				$bytes = pack('H*', $s);
				// Invalid UTF-8 would send the whole output down the
				// sanitizer's escape-everything fallback, so drop it instead
				return mb_check_encoding($bytes, SOURCE_ENCODING) ? $bytes : '';

			// --- PHP argument helpers ---
			case 'func_get_args':
				return new Pkript_Arr($this->currentCallArgs());

			case 'func_num_args':
				return count($this->currentCallArgs());

			case 'func_get_arg':
				$callArgs = $this->currentCallArgs();
				$i = (int) $this->toNumber($this->arg($args, 0, NULL), $node);
				return isset($callArgs[$i]) ? $callArgs[$i] : NULL;

			// --- Conversions ---
			case 'String':
				return self::toStringValue($this->arg($args, 0, ''));
			case 'Number':
				return $this->toNumber($this->arg($args, 0, 0), $node);
			case 'Boolean':
				return self::toBool($this->arg($args, 0, FALSE));
		}
		$this->fail('未定義の組み込み関数 ' . $name, $node);
	}

	/**
	 * Link to a wiki page. make_pagelink() already escapes the page name and
	 * only ever emits a relative URL, so the sanitizer keeps it intact.
	 */
	private function wikiLink($page, $label, $node)
	{
		if ($page === '')
			return '';
		// make_pagelink() stats the page to decide how to render the link
		$this->spendRead($node);
		if (!function_exists('make_pagelink')) {
			return htmlsc($label === '' ? $page : $label);
		}
		// PukiWiki's own markup, same as wiki.convert(): park it as a trusted
		// fragment so its class names survive. Running it through the whitelist
		// would rewrite class="link_page_passage" to "pkript-link_..." and
		// the wiki's stylesheet would stop matching it.
		return $this->addTrustedFragment(
			make_pagelink($page, $label === '' ? $page : $label),
			$node
		);
	}

	/**
	 * Hand HTML that PukiWiki produced to the sanitizer untouched.
	 * Bounded so a loop cannot grow the table without limit.
	 */
	private function addTrustedFragment($html, $node)
	{
		if (count($this->fragments) >= PKRIPT_MAX_ARRAY) {
			$this->fail('Wiki出力の断片が多すぎます (上限 ' . PKRIPT_MAX_ARRAY . ')', $node);
		}
		return Pkript_Sanitizer::addFragment($this->fragments, $html);
	}

	/**
	 * Read another page's raw Wiki text.
	 *
	 * check_readable($page, TRUE, FALSE): the second argument enables the auth
	 * check and the third stops it calling exit() on failure. Passing FALSE for
	 * the second would switch the check off entirely, which is the opposite of
	 * what is wanted here. With it in place a script can only read what the
	 * current viewer could open themselves.
	 *
	 * A page that does not exist and a page the viewer may not read both come
	 * back as an empty string, so a script cannot use the difference to probe
	 * for the existence of protected pages.
	 */
	/**
	 * Page names starting with $prefix, sorted. $non_list hides what PukiWiki's
	 * own #list hides (':config/' included, where page scripts live), and
	 * check_readable() keeps an unreadable page from even being named.
	 */
	private function spendRead($node)
	{
		if (!$this->budget->spendRead()) {
			$this->failLimit('ページ参照の回数が上限を超えました (上限 ' .
				PKRIPT_MAX_READS . ')', $node);
		}
	}

	private function wikiPages($prefix, $node)
	{
		if (!function_exists('get_existpages'))
			return array();
		$this->spendRead($node);

		// One directory scan per run, however often a script asks.
		// get_existpages() caches across the request on its own.
		if ($this->pageList === NULL) {
			$this->pageList = get_existpages();
			sort($this->pageList, SORT_STRING);
		}

		$matched = array();
		foreach ($this->pageList as $page) {
			if ($prefix !== '' && strpos($page, $prefix) !== 0)
				continue;
			if (function_exists('check_non_list') && check_non_list($page))
				continue;
			$matched[] = $page;
		}

		// Checked before the permission pass, which is the expensive part
		if (count($matched) > PKRIPT_MAX_PAGES) {
			$this->failLimit('ページ数が上限を超えました (上限 ' . PKRIPT_MAX_PAGES .
				')。prefix で絞り込んでください', $node);
		}

		if (!function_exists('check_readable'))
			return $matched;

		$out = array();
		foreach ($matched as $page) {
			if (check_readable($page, TRUE, FALSE))
				$out[] = $page;
		}
		return $out;
	}

	/**
	 * Why a write to $page would be refused, '' if it would go through.
	 * Every check here is one PukiWiki 1.5.4 does not make for us:
	 * do_plugin_action() does no authentication, page_write() sees only
	 * PKWK_READONLY, and the core has no CSRF token at all. README.md 8.5.
	 */
	private function writeRefusal($page)
	{
		if ($this->trust < PKRIPT_WRITE_MIN_TRUST) {
			return 'このスクリプトはページを書き込めません';
		}
		// Only reachable as ?cmd=pkript, never while a page is being rendered:
		// a write must be something the visitor asked for
		if ($this->entryType !== 'action') {
			return 'ページの書き込みは action からのみ行えます';
		}
		if (
			!isset($_SERVER['REQUEST_METHOD']) ||
			strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST'
		) {
			return 'ページの書き込みは POST からのみ行えます';
		}
		if (defined('PKWK_READONLY') && PKWK_READONLY) {
			return 'Wikiが読み取り専用です';
		}
		if (!plugin_pkript_check_token()) {
			return 'トークンが正しくありません';
		}

		if ($page === '' || strpos($page, ':') === 0) {
			// ':' pages hold the wiki's own configuration - and the page
			// scripts themselves, so writing one would rewrite the sandbox
			return '書き込めないページ名です';
		}
		if (!function_exists('page_write')) {
			return 'この環境ではページを書き込めません';
		}
		if (function_exists('is_freeze') && is_freeze($page)) {
			return 'ページが凍結されています';
		}
		if (function_exists('check_editable') && !check_editable($page, TRUE, FALSE)) {
			return 'ページの編集権限がありません';
		}
		return '';
	}

	/** @return bool TRUE once the page is written */
	private function wikiWrite($page, $text, $append, $node)
	{
		$refusal = $this->writeRefusal($page);
		if ($refusal !== '')
			$this->fail($refusal, $node);

		if (!$this->budget->spendWrite()) {
			$this->failLimit('ページ書き込みの回数が上限を超えました (上限 ' .
				PKRIPT_MAX_WRITES . ')', $node);
		}

		if ($append) {
			$current = is_page($page) ? get_source($page, TRUE, TRUE) : '';
			// Keep the two sides from running into one line
			if ($current !== '' && substr($current, -1) !== "\n")
				$current .= "\n";
			$text = $current . $text;
		}
		if (strlen($text) > PKRIPT_MAX_PAGE_BYTES) {
			$this->failLimit('ページが大きすぎます (上限 ' . PKRIPT_MAX_PAGE_BYTES .
				'バイト)', $node);
		}

		page_write($page, $text);
		return TRUE;
	}

	private function wikiSource($page, $node)
	{
		$this->spendRead($node);
		if ($page === '' || !is_page($page))
			return '';

		if (function_exists('check_readable') && !check_readable($page, TRUE, FALSE)) {
			return '';
		}
		if (!function_exists('get_source'))
			return '';

		return $this->checkString(
			self::stripHtmlMarks(get_source($page, TRUE, TRUE)), $node);
	}

	/**
	 * Render Wiki markup with PukiWiki's own converter.
	 *
	 * The result is NOT passed through the sanitizer. PukiWiki's output is
	 * already trusted markup, and running it through a whitelist meant for
	 * script output would rewrite its class names, strip its table markup and
	 * unwrap tags it does not know - the converted text would come out broken.
	 *
	 * So the HTML is parked in a side table and a plain-text token takes its
	 * place. The sanitizer substitutes the real HTML back in afterwards, and
	 * only where the token ended up as *text* - never inside an attribute. A
	 * script therefore cannot smuggle the fragment into `title="..."` and use
	 * it to break out of an attribute.
	 *
	 * What this does NOT do is make the input safe: whatever the script passes
	 * in is interpreted as Wiki markup, so a script can emit any HTML that a
	 * page author could write by hand. That is the same authority the person
	 * who installed the script already has, which is why this is allowed at
	 * all - but it is the reason wiki.convert() must never be fed text that
	 * came from an untrusted request parameter.
	 */
	private function wikiConvert($text, $node)
	{
		if (!function_exists('convert_html'))
			return htmlsc($text);
		if (trim($text) === '')
			return '';

		if (!$this->budget->spendConvert()) {
			$this->failLimit('wiki.convert の呼び出し回数が上限を超えました (上限 ' .
				PKRIPT_MAX_CONVERT . ')', $node);
		}

		// convert_html() runs the whole plugin pipeline, so a script that
		// converts text containing '#pkript(...)' would re-enter this
		// interpreter. Depth is static because the nesting happens across
		// separate Interpreter instances.
		static $depth = 0;
		if ($depth >= self::$maxConvertDepth) {
			$this->failLimit('wiki.convert の入れ子が深すぎます (上限 ' .
				self::$maxConvertDepth . ')', $node);
		}

		$depth++;
		try {
			$html = convert_html($text);
		} catch (Exception $e) {
			$depth--;
			$this->fail('wiki.convert に失敗しました', $node);
			return '';
		}
		$depth--;

		return $this->addTrustedFragment($html, $node);
	}

	private function callMethod($recv, $name, $args, $node)
	{
		$table = self::methodTableFor($recv);
		if ($table === NULL || !in_array($name, $table['methods'], TRUE)) {
			$type = $table === NULL ? self::typeName($recv) : $table['label'];
			$this->fail($type . ' にメソッド ' . $name . ' はありません', $node);
		}
		$handler = $table['call'];
		return $this->$handler($recv, $name, $args, $node);
	}

	private function callStringMethod($s, $name, $args, $node)
	{
		$enc = SOURCE_ENCODING;
		switch ($name) {
			case 'toUpperCase':
				return mb_strtoupper($s, $enc);
			case 'toLowerCase':
				return mb_strtolower($s, $enc);
			case 'trim':
				return trim($s);

			case 'indexOf':
				$len = mb_strlen($s, $enc);
				$from = (int) $this->numArg($args, 1, $node, 0);
				if ($from < 0)
					$from = 0;
				if ($from > $len)
					$from = $len;
				$needle = $this->strArg($args, 0);
				if ($needle === '')
					return $from;
				$at = mb_strpos($s, $needle, $from, $enc);
				return $at === FALSE ? -1 : $at;

			case 'includes':
				return $this->strArg($args, 0) === '' ||
					mb_strpos($s, $this->strArg($args, 0), 0, $enc) !== FALSE;
			case 'startsWith':
				return strpos($s, $this->strArg($args, 0)) === 0;
			case 'endsWith':
				$suffix = $this->strArg($args, 0);
				return $suffix === '' || substr($s, -strlen($suffix)) === $suffix;

			case 'replace':
				$from = $this->strArg($args, 0);
				if ($from === '')
					return $s;
				$pos = strpos($s, $from);
				if ($pos === FALSE)
					return $s;
				return $this->checkString(
					substr_replace($s, $this->strArg($args, 1), $pos, strlen($from)),
					$node
				);

			case 'replaceAll':
				$from = $this->strArg($args, 0);
				if ($from === '')
					return $s;
				return $this->checkString(str_replace($from, $this->strArg($args, 1), $s), $node);

			case 'split':
				$sep = $this->strArg($args, 0);
				$parts = $sep === '' ? mb_str_split($s, 1, $enc) : explode($sep, $s);
				return new Pkript_Arr($this->checkArray($parts, $node));

			case 'substring':
			case 'slice':
				list($start, $end) = $this->sliceRange(
					mb_strlen($s, $enc),
					$args,
					$node,
					$name === 'slice'
				);
				return mb_substr($s, $start, $end - $start, $enc);

			case 'charAt':
				$i = (int) $this->numArg($args, 0, $node, 0);
				if ($i < 0 || $i >= mb_strlen($s, $enc))
					return '';
				return mb_substr($s, $i, 1, $enc);

			case 'spanWhile':
			case 'spanUntil':
				return $this->span($s, $name === 'spanWhile', $args, $node);

			case 'repeat':
				$n = (int) $this->numArg($args, 0, $node, 0);
				if ($n < 0)
					$this->fail('repeat の回数が負の数です', $node);
				// Checked before str_repeat() so the big string is never built
				if ($n > 0 && strlen($s) * $n > PKRIPT_MAX_STRING) {
					$this->failStringTooLong($node);
				}
				return str_repeat($s, $n);
		}
		$this->fail('String にメソッド ' . $name . ' はありません', $node);
	}

	/**
	 * How far a run of characters reaches: `spanWhile` walks while the char is
	 * in $set, `spanUntil` while it is not. Returns the index it stopped at.
	 * Replaces a scanner's inner loop, which costs 50-60 steps per character.
	 */
	private function span($s, $inSet, $args, $node)
	{
		$enc = SOURCE_ENCODING;
		$from = (int) $this->numArg($args, 0, $node, 0);
		$set = $this->strArg($args, 1);
		$len = mb_strlen($s, $enc);

		if ($from < 0)
			$from = 0;
		if ($from >= $len)
			return $len;
		if ($set === '')
			return $inSet ? $from : $len;

		$table = self::charSet($set);

		// Character indexes are byte indexes when nothing is multibyte, which
		// is the case a scanner is usually in
		if (strlen($s) === $len) {
			$i = $from;
			while ($i < $len && isset($table[$s[$i]]) === $inSet)
				$i++;
			return $i;
		}

		$chars = mb_str_split($s, 1, $enc);
		$i = $from;
		while ($i < $len && isset($table[$chars[$i]]) === $inSet)
			$i++;
		return $i;
	}

	/**
	 * The characters of a set string, with `a-z` ranges expanded. To include a
	 * literal '-', put it first or last. Only ASCII takes part in a range.
	 */
	private static function charSet($set)
	{
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

	private function callArrayMethod($arr, $name, $args, $node)
	{
		switch ($name) {
			case 'push':
				foreach ($args as $a)
					$arr->items[] = $a;
				$this->checkArray($arr->items, $node);
				return count($arr->items);

			case 'pop':
				return empty($arr->items) ? NULL : array_pop($arr->items);

			case 'shift':
				return empty($arr->items) ? NULL : array_shift($arr->items);

			case 'unshift':
				foreach (array_reverse($args) as $a)
					array_unshift($arr->items, $a);
				$this->checkArray($arr->items, $node);
				return count($arr->items);

			case 'join':
				$sep = array_key_exists(0, $args) ? self::toStringValue($args[0]) : ',';
				// Seeded with the array itself: an item pointing back at it writes nothing
				$seen = array(spl_object_id($arr) => TRUE);
				$parts = array();
				$length = 0;
				foreach ($arr->items as $item) {
					$part = self::toStringValue($item, $seen);
					// As we go: the finished string could be MAX_ARRAY x MAX_STRING
					$length += strlen($part) + strlen($sep);
					if ($length > PKRIPT_MAX_STRING)
						$this->failStringTooLong($node);
					$parts[] = $part;
				}
				return $this->checkString(implode($sep, $parts), $node);

			case 'indexOf':
				$len = count($arr->items);
				$from = (int) $this->numArg($args, 1, $node, 0);
				if ($from < 0)
					$from = max(0, $len + $from);
				$needle = $this->arg($args, 0, NULL);
				for ($i = $from; $i < $len; $i++) {
					if (self::strictEquals($arr->items[$i], $needle))
						return $i;
				}
				return -1;

			case 'includes':
				foreach ($arr->items as $item) {
					if (self::strictEquals($item, $this->arg($args, 0, NULL)))
						return TRUE;
				}
				return FALSE;

			case 'slice':
				list($start, $end) = $this->sliceRange(
					count($arr->items),
					$args,
					$node,
					TRUE
				);
				return new Pkript_Arr(array_slice($arr->items, $start, $end - $start));

			case 'reverse':
				$arr->items = array_reverse($arr->items);
				return $arr;

			// (item, index, array), as in JavaScript, over a snapshot: a
			// callback that mutates the array cannot extend the loop
			case 'map':
				$out = array();
				foreach ($arr->items as $i => $item) {
					$out[] = $this->callCallback($args, array($item, $i, $arr), $node);
				}
				return new Pkript_Arr($this->checkArray($out, $node));

			case 'filter':
				$out = array();
				foreach ($arr->items as $i => $item) {
					if (self::toBool($this->callCallback($args, array($item, $i, $arr), $node))) {
						$out[] = $item;
					}
				}
				return new Pkript_Arr($out);

			case 'find':
			case 'findIndex':
				foreach ($arr->items as $i => $item) {
					if (self::toBool($this->callCallback($args, array($item, $i, $arr), $node))) {
						return $name === 'find' ? $item : $i;
					}
				}
				return $name === 'find' ? NULL : -1;

			case 'sort':
				$arr->items = $this->sortItems($arr->items, $this->arg($args, 0, NULL), $node);
				return $arr;

			case 'concat':
				$items = $arr->items;
				foreach ($args as $a) {
					if ($a instanceof Pkript_Arr) {
						foreach ($a->items as $item)
							$items[] = $item;
					} else {
						$items[] = $a;
					}
				}
				return new Pkript_Arr($this->checkArray($items, $node));
		}
		$this->fail('Array にメソッド ' . $name . ' はありません', $node);
	}

	private function callCallback($args, $callArgs, $node)
	{
		return $this->callValue($this->arg($args, 0, NULL), $callArgs, $node);
	}

	/**
	 * In place, like JavaScript: no comparator means compare as strings.
	 * usort() has been stable since PHP 8.0, which is what ES2019 requires.
	 */
	private function sortItems($items, $compare, $node)
	{
		if ($compare === NULL) {
			usort($items, function ($a, $b) {
				return strcmp(self::toStringValue($a), self::toStringValue($b));
			});
			return $items;
		}

		usort($items, function ($a, $b) use ($compare, $node) {
			$n = $this->toNumber($this->callValue($compare, array($a, $b), $node), $node);
			// A comparator returning 0.5 must not collapse to 0
			return $n < 0 ? -1 : ($n > 0 ? 1 : 0);
		});
		return $items;
	}

	private function callNumberMethod($n, $name, $args, $node)
	{
		switch ($name) {
			case 'toFixed':
				$digits = (int) $this->numArg($args, 0, $node, 0);
				if ($digits < 0 || $digits > 20)
					$this->fail('toFixed の桁数が範囲外です', $node);
				return number_format((float) $n, $digits, '.', '');
			case 'toString':
				return self::toStringValue($n);
		}
		$this->fail('Number にメソッド ' . $name . ' はありません', $node);
	}
}

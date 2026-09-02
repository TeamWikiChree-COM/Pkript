<?php
// $Id: wiki.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - wiki namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Everything that touches PukiWiki itself.
 *
 * Two rules run through the whole file. Reads cost budget (spendRead) so a
 * script cannot walk the page store for free, and a page the viewer may not
 * read is indistinguishable from one that does not exist - never an error,
 * always the empty answer - so a script cannot probe for protected pages.
 *
 * Writes are a policy of their own; they live in Pkript_Std_WikiWriter.
 */
class Pkript_Std_Wiki extends Pkript_Std_Module {
	// How far convert() may re-enter itself
	const MAX_CONVERT_DEPTH = 3;

	/** @var Pkript_Std_WikiWriter */
	private $writer = NULL;

	/** Page names for pages(), read once per run. */
	private $pageList = NULL;

	public static function members() {
		return array(
			'exists', 'link', 'convert', 'stripBracket', 'encode', 'decode',
			'source', 'pages', 'write', 'append', 'token', 'canWrite',
			'time', 'isFrozen', 'uri', 'redirect',
		);
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'exists':
				return $this->exists($this->strArg($args, 0), $node);
			case 'source':
				return $this->source($this->strArg($args, 0), $node);
			case 'time':
				return $this->time($this->strArg($args, 0), $node);
			case 'isFrozen':
				return $this->isFrozen($this->strArg($args, 0), $node);
			case 'pages':
				return new Pkript_Arr(
					$this->pages($this->strArg($args, 0, ''), $node));

			case 'link':
				return $this->link(
					$this->strArg($args, 0), $this->strArg($args, 1, ''), $node);
			case 'convert':
				return $this->convert($this->strArg($args, 0), $node);
			case 'uri':
				return $this->uri(
					$this->strArg($args, 0, ''),
					Pkript_Interpreter::toBool($this->arg($args, 1, FALSE)),
					$node
				);

			case 'stripBracket':
				// '[[Page]]' -> 'Page'; anything else is returned unchanged
				$s = $this->strArg($args, 0);
				return preg_match('/^\[\[(.*)\]\]$/s', $s, $m) ? $m[1] : $s;
			case 'encode':
				return self::encode($this->strArg($args, 0));
			case 'decode':
				return self::decode($this->strArg($args, 0));

			case 'token':
				return plugin_pkript_token();
			// Whether a form would be worth drawing at all. Only the page
			// side of the policy, since the form supplies the rest.
			case 'canWrite':
				return $this->writer()->pageRefusal($this->strArg($args, 0)) === '';
			case 'redirect':
				return $this->redirect($this->strArg($args, 0), $node);

			case 'write':
			case 'append':
				return $this->writer()->write(
					$this->strArg($args, 0),
					$this->strArg($args, 1),
					$name === 'append',
					$node
				);
		}
	}

	private function writer() {
		if ($this->writer === NULL)
			$this->writer = new Pkript_Std_WikiWriter($this->rt);
		return $this->writer;
	}


	/** TRUE only for a page the viewer could open themselves. */
	private static function readable($page) {
		return !function_exists('check_readable') ||
			check_readable($page, TRUE, FALSE);
	}

	/////////////////////////////////////////////
	// Reading

	private function exists($page, $node) {
		if ($page === '')
			return FALSE;
		$this->spendRead($node);
		return is_page($page);
	}

	/**
	 * Another page's raw Wiki text.
	 *
	 * check_readable($page, TRUE, FALSE): the second argument enables the auth
	 * check and the third stops it calling exit() on failure. Passing FALSE for
	 * the second would switch the check off entirely, which is the opposite of
	 * what is wanted here.
	 */
	private function source($page, $node) {
		$this->spendRead($node);
		if ($page === '' || !is_page($page))
			return '';
		if (!self::readable($page) || !function_exists('get_source'))
			return '';

		return $this->rt->checkString(
			Pkript_Interpreter::stripHtmlMarks(get_source($page, TRUE, TRUE)),
			$node
		);
	}

	/** Last modified time of a page, or 0 for one that is not there. */
	private function time($page, $node) {
		$this->spendRead($node);
		if ($page === '' || !is_page($page))
			return 0;
		if (!self::readable($page) || !function_exists('get_filetime'))
			return 0;
		return (int) get_filetime($page);
	}

	private function isFrozen($page, $node) {
		$this->spendRead($node);
		if ($page === '' || !is_page($page))
			return FALSE;
		return function_exists('is_freeze') ? (bool) is_freeze($page) : FALSE;
	}

	/**
	 * Page names starting with $prefix, sorted. check_non_list() hides what
	 * PukiWiki's own #list hides (':config/' included, where page scripts
	 * live), and readable() keeps an unreadable page from even being named.
	 */
	private function pages($prefix, $node) {
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
			$this->rt->failLimit('Too many pages (limit ' .
				PKRIPT_MAX_PAGES . '). Narrow it with a prefix.', $node);
		}

		$out = array();
		foreach ($matched as $page) {
			if (self::readable($page))
				$out[] = $page;
		}
		return $out;
	}

	/////////////////////////////////////////////
	// Names and URIs

	/** Page name -> the upper-case hex PukiWiki uses for filenames. */
	private static function encode($s) {
		return $s === '' ? '' : strtoupper(bin2hex($s));
	}

	private static function decode($s) {
		// Like PukiWiki's decode(): non-hex comes back untouched, and pairs
		// are required so pack() never silently pads.
		if (!preg_match('/^([0-9a-f]{2})+$/i', $s))
			return $s;
		$bytes = pack('H*', $s);
		// Invalid UTF-8 would send the whole output down the sanitizer's
		// escape-everything fallback, so drop it instead
		return mb_check_encoding($bytes, SOURCE_ENCODING) ? $bytes : '';
	}

	/**
	 * The wiki's own URI, or the URI of one page.
	 *
	 * Relative by default, like every other link a script can produce. Pass
	 * TRUE for the second argument to get the absolute form that a mail body
	 * or an RSS item needs; the sanitizer keeps either (see filterUrl()).
	 */
	/**
	 * Finish the request by sending the visitor to $page.
	 *
	 * This is the second half of post-redirect-get: without it, a visitor who
	 * has just posted a form is left on the action's own page, and a refresh
	 * posts the form again. PukiWiki's own form plugins end the same way.
	 *
	 * Only from an action, and only from a POST, because those are the only
	 * requests that have anything to redirect away from. Only a page of this
	 * wiki, named by get_page_uri(), so nothing a script writes reaches the
	 * Location header directly.
	 */
	private function redirect($page, $node) {
		if ($this->rt->entryType() !== 'action')
			$this->rt->fail('Redirect is only allowed from an action', $node);

		$method = isset($_SERVER['REQUEST_METHOD'])
			? strtoupper($_SERVER['REQUEST_METHOD']) : '';
		if ($method !== 'POST')
			$this->rt->fail('Redirect is only allowed from a POST', $node);

		if ($page === '' || strpos($page, ':') === 0 || !is_page($page))
			$this->rt->fail('No such page to redirect to: ' . $page, $node);

		throw new Pkript_Redirect($page);
	}

	private function uri($page, $absolute, $node) {
		$type = ($absolute && defined('PKWK_URI_ABSOLUTE'))
			? PKWK_URI_ABSOLUTE : NULL;

		if ($page === '') {
			if (!function_exists('get_base_uri'))
				return './';
			return $type === NULL ? get_base_uri() : get_base_uri($type);
		}

		$this->spendRead($node);
		if (function_exists('get_page_uri')) {
			return $type === NULL
				? get_page_uri($page) : get_page_uri($page, $type);
		}
		$base = function_exists('get_base_uri') ? get_base_uri() : './';
		return $base . '?' . rawurlencode($page);
	}

	/////////////////////////////////////////////
	// PukiWiki's own HTML

	/**
	 * Link to a wiki page. make_pagelink() already escapes the page name and
	 * only ever emits a relative URL, so the sanitizer keeps it intact.
	 */
	private function link($page, $label, $node) {
		if ($page === '')
			return '';
		// make_pagelink() stats the page to decide how to render the link
		$this->spendRead($node);
		if (!function_exists('make_pagelink'))
			return htmlsc($label === '' ? $page : $label);

		// PukiWiki's own markup, same as convert(): park it as a trusted
		// fragment so its class names survive. Running it through the
		// whitelist would rewrite class="link_page_passage" to
		// "pkript-link_..." and the wiki's stylesheet would stop matching it.
		return $this->rt->addFragment(
			make_pagelink($page, $label === '' ? $page : $label), $node);
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
	private function convert($text, $node) {
		if (!function_exists('convert_html'))
			return htmlsc($text);
		if (trim($text) === '')
			return '';

		if (!$this->rt->budget()->spendConvert()) {
			$this->rt->failLimit('Too many wiki.convert() calls (limit ' .
				PKRIPT_MAX_CONVERT . ')', $node);
		}

		// convert_html() runs the whole plugin pipeline, so a script that
		// converts text containing '#pkript(...)' would re-enter this
		// interpreter. Depth is static because the nesting happens across
		// separate Interpreter instances.
		static $depth = 0;
		if ($depth >= self::MAX_CONVERT_DEPTH) {
			$this->rt->failLimit('wiki.convert() nesting too deep (limit ' .
				self::MAX_CONVERT_DEPTH . ')', $node);
		}

		$depth++;
		try {
			$html = convert_html($text);
		} catch (Exception $e) {
			$this->rt->fail('wiki.convert() failed', $node);
			return '';
		} finally {
			$depth--;
		}

		return $this->rt->addFragment($html, $node);
	}
}

<?php
// $Id: data.php,v 0.3 2026/08/31 12:00:00 WikiChree.COM Team Exp $

/**
 * Pkript runtime - data namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * A place for a script to keep something between requests.
 *
 * Each key is one wiki page under PKRIPT_DATA_PREFIX holding the value as
 * JSON. Pages, rather than a file of the plugin's own, because a wiki already
 * knows how to store, back up, diff and restore a page - and because an
 * administrator can then read and fix what a script wrote with the editor
 * they already have.
 *
 * Writing goes through exactly the same policy as wiki.write(): an action, a
 * POST, a token and enough trust. A store that could be written while a page
 * was merely being rendered would be a way to make a visitor change the wiki
 * without knowing it, whatever the data is called.
 *
 * Reading does not check page permissions, and cannot: a guestbook has to
 * read its own store for a visitor who may not read :config pages at all.
 * So the store is shared - any script can read any key. Keep a secret out of
 * it; it is configuration and accumulated state, not private storage.
 */
class Pkript_Std_Data extends Pkript_Std_Module {
	private $json = NULL;

	public static function members() {
		return array('get', 'set', 'has', 'remove', 'keys', 'canWrite');
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'get':
				return $this->get($this->strArg($args, 0),
					$this->arg($args, 1, NULL), $node);

			case 'set':
				return $this->set($this->strArg($args, 0),
					$this->arg($args, 1, NULL), $node);

			case 'has':
				return $this->has($this->strArg($args, 0), $node);

			case 'remove':
				return $this->remove($this->strArg($args, 0), $node);

			case 'keys':
				return $this->keys($this->strArg($args, 0, ''), $node);

			// Whether a form would be worth drawing at all. Only the store
			// side of the policy, since the form supplies the rest.
			case 'canWrite':
				return $this->storeRefusal($this->strArg($args, 0)) === '';
		}
	}

	/////////////////////////////////////////////
	// Keys

	/**
	 * A key names a page, so what may be in one is decided here rather than
	 * by whatever the page name turns out to be. Segments of letters, digits,
	 * '_' and '-', joined by '/', and no segment may be '.' or '..': a key
	 * can only ever name a page under the prefix.
	 */
	private static function isKey($key) {
		if ($key === '' || strlen($key) > 128)
			return FALSE;
		return preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $key) === 1;
	}

	private function page($key, $node) {
		if (!self::isKey($key))
			$this->rt->fail('データ名が不正です: ' . $key, $node);
		return PKRIPT_DATA_PREFIX . $key;
	}

	/////////////////////////////////////////////
	// Reading

	/**
	 * @param mixed $default what to return when the key was never written
	 */
	private function get($key, $default, $node) {
		$page = $this->page($key, $node);
		$this->spendRead($node);

		if (!PKRIPT_ALLOW_DATA || !is_page($page) || !function_exists('get_source'))
			return $default;

		$text = self::unwrap(get_source($page, TRUE, TRUE));
		if ($text === '')
			return $default;

		// set() wrote it, so it parses - unless a person edited the page by
		// hand and got it wrong. That is worth saying plainly rather than
		// reporting as a JSON syntax error against a page they cannot see.
		try {
			return $this->json()->parse($text, $node);
		} catch (Pkript_LimitError $limit) {
			throw $limit;   // a limit is never a "broken data" answer
		} catch (Pkript_Error $e) {
			$this->rt->fail('データ ' . $key . ' が壊れています', $node);
		}
	}

	/**
	 * The JSON back out of the page PukiWiki actually stored.
	 *
	 * page_write() does two things to whatever it is handed, and both of them
	 * are why this is not just trim():
	 *
	 *   - add_author_info() puts an #author(...) line on the front of every
	 *     page, so the first line is never the value;
	 *   - make_str_rules() rewrites &now;, &date;, &page; and friends -
	 *     except on lines that start with a space, which is why wrap() puts
	 *     one there.
	 */
	private static function unwrap($text) {
		$text = function_exists('remove_author_info')
			? remove_author_info($text)
			: preg_replace('/^\s*#author\([^\n]*(\n|$)/', '', $text);

		$out = array();
		foreach (explode("\n", $text) as $line) {
			// The one space wrap() added, and no more: everything after it is
			// the value as it was written
			if (isset($line[0]) && $line[0] === ' ')
				$line = substr($line, 1);
			$out[] = $line;
		}
		return trim(implode("\n", $out));
	}

	/**
	 * The page text for a value. The leading space is what keeps
	 * make_str_rules() from rewriting the JSON, and it makes the page render
	 * as a preformatted block rather than as broken wiki markup, so an
	 * administrator opening it sees the value.
	 */
	private static function wrap($json) {
		$out = array();
		foreach (explode("\n", $json) as $line)
			$out[] = ' ' . $line;
		return implode("\n", $out) . "\n";
	}

	private function has($key, $node) {
		$page = $this->page($key, $node);
		$this->spendRead($node);
		return PKRIPT_ALLOW_DATA && is_page($page);
	}

	/** Every key, or the ones under $prefix. Sorted, so listings are stable. */
	private function keys($prefix, $node) {
		if ($prefix !== '' && !self::isKey($prefix))
			$this->rt->fail('データ名が不正です: ' . $prefix, $node);
		if (!PKRIPT_ALLOW_DATA || !function_exists('get_existpages'))
			return new Pkript_Arr();
		$this->spendRead($node);

		$under = PKRIPT_DATA_PREFIX . $prefix;
		$out = array();
		foreach (get_existpages() as $page) {
			if (strpos($page, $under) !== 0)
				continue;
			$key = substr($page, strlen(PKRIPT_DATA_PREFIX));
			if (self::isKey($key))
				$out[] = $key;
		}
		sort($out);

		if (count($out) > PKRIPT_MAX_PAGES) {
			$this->rt->failLimit('ページ数が上限を超えました (上限 ' .
				PKRIPT_MAX_PAGES . ')', $node);
		}
		return new Pkript_Arr($this->rt->checkArray($out, $node));
	}

	/////////////////////////////////////////////
	// Writing

	/**
	 * Split the same way Pkript_Std_WikiWriter is, and for the same reason:
	 * canWrite() is asked while a page renders, so it must not be told no by
	 * the checks the form it is about to draw will satisfy.
	 *
	 * @return string why a write to $key would be refused now, '' if it
	 *                would go through
	 */
	private function refusal($key) {
		$refusal = $this->storeRefusal($key);
		if ($refusal !== '')
			return $refusal;

		// The page checks Pkript_Std_WikiWriter adds - ':' pages, freezing,
		// $edit_auth_pages - are about pages people write. Every page here is
		// under ':config' by design, so only the request half applies.
		$writer = new Pkript_Std_WikiWriter($this->rt);
		return $writer->requestRefusal();
	}

	/** What no form can change: this script, this key, this wiki. */
	private function storeRefusal($key) {
		if (!PKRIPT_ALLOW_DATA)
			return 'データ保存は無効になっています';
		if (!self::isKey($key))
			return 'データ名が不正です';
		if ($this->rt->trust() < PKRIPT_DATA_MIN_TRUST)
			return 'このスクリプトはデータを書き込めません';
		if (defined('PKWK_READONLY') && PKWK_READONLY)
			return 'Wikiが読み取り専用です';
		if (!function_exists('page_write'))
			return 'この環境ではページを書き込めません';
		return '';
	}

	private function set($key, $value, $node) {
		$refusal = $this->refusal($key);
		if ($refusal !== '')
			$this->rt->fail($refusal, $node);

		$text = $this->json()->stringify($value, 0, $node);
		if (strlen($text) > PKRIPT_MAX_PAGE_BYTES) {
			$this->rt->failLimit('データが大きすぎます (上限 ' .
				PKRIPT_MAX_PAGE_BYTES . 'バイト)', $node);
		}

		$this->writePage($this->page($key, $node), self::wrap($text), $node);
		return TRUE;
	}

	/** Removing a key deletes its page, the way PukiWiki deletes any page. */
	private function remove($key, $node) {
		$refusal = $this->refusal($key);
		if ($refusal !== '')
			$this->rt->fail($refusal, $node);

		$page = $this->page($key, $node);
		if (!is_page($page))
			return FALSE;

		$this->writePage($page, '', $node);
		return TRUE;
	}

	private function writePage($page, $text, $node) {
		if (!$this->rt->budget()->spendWrite()) {
			$this->rt->failLimit('ページ書き込みの回数が上限を超えました (上限 ' .
				PKRIPT_MAX_WRITES . ')', $node);
		}
		if (!function_exists('page_write'))
			$this->rt->fail('この環境ではページを書き込めません', $node);

		page_write($page, $text);
	}

	/** JSON is the storage format, so the JSON module is where it is done. */
	private function json() {
		if ($this->json === NULL)
			$this->json = new Pkript_Std_Json($this->rt);
		return $this->json;
	}
}

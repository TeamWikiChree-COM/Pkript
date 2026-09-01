<?php
// $Id: store.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - wiki page store
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * data.* kept as wiki pages under PKRIPT_DATA_PREFIX.
 *
 * Pages, rather than a file of the plugin's own, because a wiki already knows
 * how to store, back up, diff and restore a page - and because an
 * administrator can then read and fix what a script wrote with the editor
 * they already have.
 *
 * Everything here is about pages. What a key may look like, whether the store
 * is on, how much trust a write needs and what the text means are
 * Pkript_Std_Data's, and it has settled all of them before calling.
 */
class Pkript_Store_WikiPage implements Pkript_Store {
	/** @var Pkript_Std_WikiWriter */
	private $writer;

	/**
	 * @param Pkript_Std_WikiWriter $writer the same request checks pages get,
	 *        since a data page is a page
	 */
	public function __construct($writer) {
		$this->writer = $writer;
	}

	private function page($key) {
		return PKRIPT_DATA_PREFIX . $key;
	}

	public function get($key) {
		$page = $this->page($key);
		if (!is_page($page) || !function_exists('get_source'))
			return NULL;

		$text = self::unwrap(get_source($page, TRUE, TRUE));
		return $text === '' ? NULL : $text;
	}

	public function set($key, $text) {
		page_write($this->page($key), self::wrap($text));
	}

	public function remove($key) {
		$page = $this->page($key);
		if (!is_page($page))
			return FALSE;
		page_write($page, '');
		return TRUE;
	}

	public function has($key) {
		return is_page($this->page($key));
	}

	public function keys($prefix) {
		if (!function_exists('get_existpages'))
			return array();

		$under = PKRIPT_DATA_PREFIX . $prefix;
		$out = array();
		foreach (get_existpages() as $page) {
			if (strpos($page, $under) === 0)
				$out[] = substr($page, strlen(PKRIPT_DATA_PREFIX));
		}
		return $out;
	}

	public function refusal($trust) {
		if ($trust < PKRIPT_DATA_MIN_TRUST)
			return 'This script may not write data';
		if (defined('PKWK_READONLY') && PKWK_READONLY)
			return 'The wiki is read only';
		if (!function_exists('page_write'))
			return 'This environment cannot write pages';
		return '';
	}

	/**
	 * The page checks Pkript_Std_WikiWriter adds - ':' pages, freezing,
	 * $edit_auth_pages - are about pages people write. Every page here is
	 * under ':config' by design, so only the request half applies.
	 */
	public function requestRefusal() {
		return $this->writer->requestRefusal();
	}

	/////////////////////////////////////////////
	// What page_write() does to text, undone

	/**
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
	 * The leading space is what keeps make_str_rules() from rewriting the
	 * JSON, and it makes the page render as a preformatted block rather than
	 * as broken wiki markup, so an administrator opening it sees the value.
	 */
	private static function wrap($json) {
		$out = array();
		foreach (explode("\n", $json) as $line)
			$out[] = ' ' . $line;
		return implode("\n", $out) . "\n";
	}
}

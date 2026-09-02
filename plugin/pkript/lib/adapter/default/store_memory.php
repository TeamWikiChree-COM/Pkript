<?php
// $Id: store_memory.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - in-memory store
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * The store Pkript falls back to when the environment did not supply one.
 *
 * It keeps values for the length of one request and then forgets them, which
 * is wrong for a store and right for a default: data.* answers the way the
 * language says it does, so a script and its tests run anywhere, and nothing
 * is quietly written to a place nobody chose.
 *
 * An environment that means to keep data supplies its own; see
 * Pkript_Store_WikiPage.
 */
class Pkript_Store_Memory implements Pkript_Store {
	private $values = array();

	public function get($key) {
		return isset($this->values[$key]) ? $this->values[$key] : NULL;
	}

	public function set($key, $text) {
		$this->values[$key] = $text;
	}

	public function remove($key) {
		if (!array_key_exists($key, $this->values))
			return FALSE;
		unset($this->values[$key]);
		return TRUE;
	}

	public function has($key) {
		return array_key_exists($key, $this->values);
	}

	public function keys($prefix) {
		$out = array();
		foreach (array_keys($this->values) as $key) {
			if ($prefix === '' || strpos($key, $prefix) === 0)
				$out[] = $key;
		}
		return $out;
	}

	/** Nothing is kept past the request, so there is nothing to protect. */
	public function refusal($trust) {
		return '';
	}

	public function requestRefusal() {
		return '';
	}
}

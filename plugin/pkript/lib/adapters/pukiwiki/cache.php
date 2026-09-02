<?php
// $Id: cache.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript - parsed script cache
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Parsed scripts kept as files in CACHE_DIR.
 *
 * Storage and nothing else. Whether what comes back still describes the
 * scripts as they are now is Pkript_Loader's to decide, and it decides it
 * every time, so there is nothing to expire here.
 */
class Pkript_AstCache_File implements Pkript_AstCache {
	public function load($name) {
		$path = plugin_pkript_cache_path($name);
		if (!is_file($path) || !is_readable($path))
			return FALSE;

		$raw = file_get_contents($path);
		if ($raw === FALSE)
			return FALSE;

		// allowed_classes stops an object being built, but it leaves an
		// __PHP_Incomplete_Class behind rather than refusing. The loader
		// checks the shape of whatever this returns for that reason.
		$entry = @unserialize($raw, array('allowed_classes' => FALSE));
		return is_array($entry) ? $entry : FALSE;
	}

	/** Silent about failure: it is only a cache. */
	public function save($name, $entry) {
		$path = plugin_pkript_cache_path($name);
		$dir = dirname($path);
		if (!is_dir($dir) || !is_writable($dir))
			return;

		// Written aside and moved into place, so a request never reads half
		// a file
		$tmp = $path . '.' . getmypid() . '.tmp';
		if (file_put_contents($tmp, serialize($entry), LOCK_EX) === FALSE)
			return;
		if (!@rename($tmp, $path))
			@unlink($tmp);
	}
}

/** Where the parsed form of $name is kept. */
function plugin_pkript_cache_path($name) {
	return CACHE_DIR . 'pkript_ast_' . $name . '.dat';
}

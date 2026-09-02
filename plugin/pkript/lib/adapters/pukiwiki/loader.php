<?php
// $Id: loader.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript - loading scripts out of this wiki
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * The loader this wiki uses: its own script source, and CACHE_DIR unless the
 * cache has been turned off.
 *
 * PKRIPT_AST_CACHE is answered by handing over a cache that keeps nothing,
 * rather than by a flag the loader would have to keep asking about - off is a
 * kind of cache, not a special case.
 *
 * @return Pkript_Loader
 */
function plugin_pkript_loader() {
	static $loader = NULL;
	if ($loader === NULL) {
		$loader = new Pkript_Loader(
			new Pkript_ScriptSource_Wiki(),
			PKRIPT_AST_CACHE ? new Pkript_AstCache_File() : new Pkript_AstCache_Null()
		);
	}
	return $loader;
}

/**
 * Load a script and everything it imports as one function table.
 *
 * @param int    $trust  out: the lowest trust level of everything loaded
 * @param string $reason out: why nothing was loaded
 * @return array|FALSE function declarations, keyed by name
 */
function plugin_pkript_compile($name, &$trust, &$reason, &$constants = NULL) {
	return plugin_pkript_loader()->compile($name, $trust, $reason, $constants);
}

/** Identifies what a cached AST was built by; see Pkript_Loader::cacheKey(). */
function plugin_pkript_cache_key() {
	return plugin_pkript_loader()->cacheKey();
}

/**
 * Locate the script source. Returns FALSE and fills $reason on failure.
 *
 * @param int $trust out
 * @return string|FALSE
 */
function plugin_pkript_load($name, &$reason, &$trust = NULL) {
	$found = plugin_pkript_script_source()->find($name, $reason);
	if ($found === FALSE)
		return FALSE;
	$trust = $found['trust'];
	return $found['source'];
}

/** @return Pkript_ScriptSource_Wiki */
function plugin_pkript_script_source() {
	static $source = NULL;
	if ($source === NULL)
		$source = new Pkript_ScriptSource_Wiki();
	return $source;
}

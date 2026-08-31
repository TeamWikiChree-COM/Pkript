<?php
// $Id: cache.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript - parsed script cache
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// AST cache
//
// Parsing is most of the cost of a small script. The AST is plain arrays, so
// it serialises; unserialising one costs about 5% of parsing it again.
//
// Only the AST is cached, never a script's output - Math.random() and e.vars
// keep working as they should.

/** Where the parsed form of $name is kept. */
function plugin_pkript_cache_path($name) {
	return CACHE_DIR . 'pkript_ast_' . $name . '.dat';
}

/**
 * Identifies what the cached AST was built by. Anything that changes how a
 * script compiles has to be in here, or a stale entry would survive a config
 * change.
 */
function plugin_pkript_cache_key() {
	static $key = NULL;
	if ($key !== NULL)
		return $key;

	return $key = md5(serialize(array(
		PKRIPT_AST_VERSION,
		PKRIPT_IMPORT_LOWER_TRUST,
		PKRIPT_MAX_IMPORTS,
		PKRIPT_MAX_IMPORT_DEPTH,
		PKRIPT_ALLOW_PAGE_SCRIPT,
		PKRIPT_PAGE_SCRIPT_FROZEN_ONLY,
		PKRIPT_PAGE_PREFIX,
		PKRIPT_DATA_PREFIX,
		PKRIPT_SCRIPT_DIR,
		PKRIPT_SCRIPT_EXT,
		PKRIPT_JSX,
		PKRIPT_REGEX,
	)));
}

/**
 * The parsed script, if what is on disk still matches what was cached.
 *
 * Every script that went into it is checked, imports included: both its
 * content and where it was loaded from. Freezing a page changes no source but
 * does change what a script may do, so the trust level is compared as well.
 *
 * @param string $rootSource source of $name, already loaded by the caller
 * @param int    $trust      in: the root's trust. out: the run's trust
 * @return array|FALSE the function table
 */
function plugin_pkript_cache_read($name, $rootSource, &$trust, &$constants) {
	if (!PKRIPT_AST_CACHE)
		return FALSE;

	$path = plugin_pkript_cache_path($name);
	if (!is_file($path) || !is_readable($path))
		return FALSE;

	$raw = file_get_contents($path);
	if ($raw === FALSE)
		return FALSE;

	// allowed_classes stops an object being built, but it leaves an
	// __PHP_Incomplete_Class behind rather than refusing - so the shape has to
	// be checked as well, or a rewritten file reaches the interpreter as
	// something that is not an AST at all
	$entry = @unserialize($raw, array('allowed_classes' => FALSE));
	if (!plugin_pkript_cache_shape($entry))
		return FALSE;
	if ($entry['key'] !== plugin_pkript_cache_key())
		return FALSE;

	$lowest = $trust;
	foreach ($entry['units'] as $unit => $was) {
		if ($unit === $name) {
			$source = $rootSource;
			$now = $trust;
		} else {
			$reason = '';
			$source = plugin_pkript_load($unit, $reason, $now);
			if ($source === FALSE)
				return FALSE;
		}
		if (!is_array($was) || count($was) !== 2)
			return FALSE;
		if ($was[0] !== md5($source) || $was[1] !== $now)
			return FALSE;

		$lowest = min($lowest, $now);
	}

	$trust = $lowest;
	$constants = isset($entry['constants']) ? $entry['constants'] : array();
	return $entry['functions'];
}

/** Is this an AST entry, and not whatever else was in the file? */
function plugin_pkript_cache_shape($entry) {
	if (!is_array($entry))
		return FALSE;
	if (!isset($entry['key'], $entry['units'], $entry['functions']))
		return FALSE;
	if (!is_string($entry['key']) || !is_array($entry['units']) || !is_array($entry['functions']))
		return FALSE;
	// A real entry always names at least the script it was built from
	if (empty($entry['units']))
		return FALSE;
	if (isset($entry['constants']) && !is_array($entry['constants']))
		return FALSE;

	foreach ($entry['functions'] as $fn) {
		if (!is_array($fn) || !isset($fn['params'], $fn['body']))
			return FALSE;
	}
	return TRUE;
}

/** Store a freshly parsed script. Silent about failure: it is only a cache. */
function plugin_pkript_cache_write($name, $units, $functions, $constants) {
	if (!PKRIPT_AST_CACHE)
		return;

	$path = plugin_pkript_cache_path($name);
	$dir = dirname($path);
	if (!is_dir($dir) || !is_writable($dir))
		return;

	$blob = serialize(array(
		'key' => plugin_pkript_cache_key(),
		'units' => $units,
		'functions' => $functions,
		'constants' => $constants,
	));

	// Written aside and moved into place, so a request never reads half a file
	$tmp = $path . '.' . getmypid() . '.tmp';
	if (file_put_contents($tmp, $blob, LOCK_EX) === FALSE)
		return;
	if (!@rename($tmp, $path))
		@unlink($tmp);
}

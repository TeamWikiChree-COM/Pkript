<?php
// $Id: pkript.inc.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Pkript - JavaScript風の構文を持つ、PukiWiki用のサンドボックス型スクリプト言語
 *
 *   #pkript(script name, arg1, arg2)
 *   &pkript(script name, arg1);
 */


/////////////////////////////////////////////////
// Runtime
//
// Everything lives in plugin/pkript/lib/. This file is the entry points,
// the configuration, and nothing else.

foreach (array(
	'error',
	'budget',
	'values',
	'regex',
	'lexer',
	'parser',
	'scope',
	'stdlib',
	'interpreter',
	'sanitizer',
	'cache',
	'loader',
	'security',
	'run'
) as $pkript_module) {
	require_once dirname(__FILE__) . '/pkript/lib/' . $pkript_module . '.php';
}
unset($pkript_module);

/////////////////////////////////////////////////
// Configuration
//
// Override any of these from pukiwiki.ini.php. See README.md 9.2 and 9.4 for
// what the values mean and how they were chosen.

// --- where scripts come from ---

// Script files, relative to DATA_HOME. Under plugin/ because the web server
// already denies that directory: script source is not meant to be fetchable.
if (!defined('PKRIPT_SCRIPT_DIR'))
	define('PKRIPT_SCRIPT_DIR', 'plugin/pkript/script/');

// Extensions tried in order. '.js' is there for editor syntax highlighting.
if (!defined('PKRIPT_SCRIPT_EXT'))
	define('PKRIPT_SCRIPT_EXT', 'pks,js');

// Scripts stored as wiki pages. Anyone who can edit those pages can run code:
// protect them with $edit_auth_pages, or set FROZEN_ONLY, on an open wiki.
if (!defined('PKRIPT_ALLOW_PAGE_SCRIPT'))
	define('PKRIPT_ALLOW_PAGE_SCRIPT', 1);
if (!defined('PKRIPT_PAGE_SCRIPT_FROZEN_ONLY'))
	define('PKRIPT_PAGE_SCRIPT_FROZEN_ONLY', 0);
if (!defined('PKRIPT_PAGE_PREFIX'))
	define('PKRIPT_PAGE_PREFIX', ':config/pkript/script/');

// Bind a script to its own plugin name, so `#hello(...)` runs script 'hello'
// without going through #pkript. Off: only #pkript / &pkript; call scripts,
// and an unknown plugin name stays unknown.
if (!defined('PKRIPT_BIND'))
	define('PKRIPT_BIND', 1);

// --- syntax ---

// JSX-like markup as an expression: `return <p>{name}</p>;`, fragments
// (`<>...</>`) included. Interpolated values are escaped, nested elements are
// not, and the result still goes through the sanitizer. Off: '<' is only the
// less-than operator.
if (!defined('PKRIPT_JSX'))
	define('PKRIPT_JSX', 1);

// Regular expression literals, /pattern/flags. Off: '/' is only division.
if (!defined('PKRIPT_REGEX'))
	define('PKRIPT_REGEX', 1);

// How hard PCRE may work on one match before giving up. This is the whole
// answer to catastrophic backtracking: a pattern that would run for minutes
// stops here instead, and the script is told so. PHP's own default is a
// million, which is far more than a wiki page ever needs.
if (!defined('PKRIPT_REGEX_BACKTRACK'))
	define('PKRIPT_REGEX_BACKTRACK', 100000);

// A pattern longer than this is refused unparsed
if (!defined('PKRIPT_MAX_REGEX'))
	define('PKRIPT_MAX_REGEX', 512);

// --- stored data ---

// data.get() / data.set(): one wiki page per key, holding the value as JSON.
// Pages rather than files of the plugin's own, so the wiki's backups, diffs
// and editor all work on what a script stored.
if (!defined('PKRIPT_ALLOW_DATA'))
	define('PKRIPT_ALLOW_DATA', 1);
if (!defined('PKRIPT_DATA_PREFIX'))
	define('PKRIPT_DATA_PREFIX', ':config/pkript/data/');

// --- trust ---

// Where a script came from decides what it may do. Not overridable: the
// runtime compares these to each other.
define('PKRIPT_TRUST_FILE', 2);   // an admin put the file there
define('PKRIPT_TRUST_FROZEN', 1);   // a frozen page
define('PKRIPT_TRUST_PAGE', 0);   // a page anyone with edit rights can change

// Let a script import one less trusted than itself. Off: otherwise the author
// of an editable page could inject code into a frozen one.
if (!defined('PKRIPT_IMPORT_LOWER_TRUST'))
	define('PKRIPT_IMPORT_LOWER_TRUST', 0);

// What it takes to call wiki.write(). Writing is the one thing a script can do
// that nobody sees happen, so the default is file scripts only.
if (!defined('PKRIPT_WRITE_MIN_TRUST'))
	define('PKRIPT_WRITE_MIN_TRUST', PKRIPT_TRUST_FILE);

// What it takes to call data.set(). The same default as wiki.write() and for
// the same reason: a store a page script could write is a store anyone who
// can edit that page can write.
if (!defined('PKRIPT_DATA_MIN_TRUST'))
	define('PKRIPT_DATA_MIN_TRUST', PKRIPT_WRITE_MIN_TRUST);

// No secret means no token, and every write fails. That is the intended
// direction to fail in.
if (!defined('PKRIPT_SECRET_FILE'))
	define('PKRIPT_SECRET_FILE', CACHE_DIR . 'pkript_secret.dat');

// --- AST cache ---

// Parsed scripts are kept in CACHE_DIR. Invalidated by the content of every
// script involved, so an import changing is noticed too.
if (!defined('PKRIPT_AST_CACHE'))
	define('PKRIPT_AST_CACHE', 1);

// Bump when the shape of the AST changes, so old cache files are ignored
define('PKRIPT_AST_VERSION', 2);

// --- limits, per request ---

// Keep STEPS and TIME calibrated: at ~900,000 steps/sec, a million steps is
// about 1.1s, so a host 3x slower hits the time limit first. Set STEPS too low
// and it silently becomes the real limit.
if (!defined('PKRIPT_MAX_STEPS'))
	define('PKRIPT_MAX_STEPS', 1000000);
if (!defined('PKRIPT_MAX_TIME'))
	define('PKRIPT_MAX_TIME', 3);

// Memory is measured, not tallied: the per-value limits multiply out to
// gigabytes, and PHP's own memory_limit only reports that as a fatal.
if (!defined('PKRIPT_MAX_MEMORY'))
	define('PKRIPT_MAX_MEMORY', Pkript_Budget::defaultLimit());

// wiki.convert() runs the whole PukiWiki plugin pipeline, which the step
// counter never sees. Reads are wiki.source/exists/pages/link.
if (!defined('PKRIPT_MAX_CONVERT'))
	define('PKRIPT_MAX_CONVERT', 32);
if (!defined('PKRIPT_MAX_READS'))
	define('PKRIPT_MAX_READS', 5000);
if (!defined('PKRIPT_MAX_WRITES'))
	define('PKRIPT_MAX_WRITES', 4);

// --- limits, per run or per value ---

if (!defined('PKRIPT_MAX_DEPTH'))
	define('PKRIPT_MAX_DEPTH', 64);

// An import costs a parse, which is the whole cost of importing
if (!defined('PKRIPT_MAX_IMPORTS'))
	define('PKRIPT_MAX_IMPORTS', 16);
if (!defined('PKRIPT_MAX_IMPORT_DEPTH'))
	define('PKRIPT_MAX_IMPORT_DEPTH', 4);

// PKRIPT_MAX_STEPS is the real bound on looping; this only names the problem
// when a loop obviously runs away.
if (!defined('PKRIPT_MAX_LOOP'))
	define('PKRIPT_MAX_LOOP', 100000);

if (!defined('PKRIPT_MAX_STRING'))
	define('PKRIPT_MAX_STRING', 1048576);
if (!defined('PKRIPT_MAX_ARRAY'))
	define('PKRIPT_MAX_ARRAY', 10000);
if (!defined('PKRIPT_MAX_PAGE_BYTES'))
	define('PKRIPT_MAX_PAGE_BYTES', 524288);

// Lower than MAX_ARRAY because each hit costs a permission check. Over it the
// call fails rather than truncating: a listing is never silently short.
if (!defined('PKRIPT_MAX_PAGES'))
	define('PKRIPT_MAX_PAGES', 1000);

// A run keeps its console.log lines whatever PKRIPT_DEBUG says, so these
// bound the buffer rather than the output. Over either one the log stops and
// says so; the run itself is not failed for logging too much.
if (!defined('PKRIPT_MAX_LOG'))
	define('PKRIPT_MAX_LOG', 100);
if (!defined('PKRIPT_MAX_LOG_BYTES'))
	define('PKRIPT_MAX_LOG_BYTES', 8192);

// Line and column numbers in error messages, and console.log output
if (!defined('PKRIPT_DEBUG'))
	define('PKRIPT_DEBUG', 1);

/////////////////////////////////////////////////
// Plugin entry points

function plugin_pkript_init() {
	// Nothing to set up: scripts are loaded lazily on each call.
}

function plugin_pkript_convert() {
	return plugin_pkript_dispatch(func_get_args(), 'convert');
}

function plugin_pkript_inline() {
	return plugin_pkript_dispatch(func_get_args(), 'inline');
}

function plugin_pkript_action() {
	global $vars;
	// 'script', not 'name': a form built by a script wants 'name' for its own
	// field (a comment form asks for the commenter's name, for one).
	$name = isset($vars['script']) ? $vars['script'] : '';
	$args = array($name);
	return array(
		'msg' => 'Pkript',
		'body' => plugin_pkript_run($args, 'action', ''),
	);
}

/////////////////////////////////////////////////
// Page writing

/**
 * Bind dynamic wrapper functions for a Pkript script named $name.
 * Called by lib/plugin.php when #name or &name; is invoked directly, and only
 * after the search for a real PHP plugin of that name has come up empty, so an
 * existing plugin can never be taken over. PKRIPT_BIND turns this off.
 *
 * @param string $name plugin name
 * @return bool TRUE if script exists and functions were bound
 */
function plugin_pkript_bind($name) {
	if (!PKRIPT_BIND) return FALSE;
	if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) return FALSE;
	$reason = '';
	$source = plugin_pkript_load($name, $reason);
	if ($source === FALSE) return FALSE;

	// The wrappers only prepend the script name and hand over to the shared
	// entry points, so no call handling lives in this generated code.
	if (!function_exists('plugin_' . $name . '_convert')) {
		$quoted = var_export($name, TRUE);
		eval ('
			function plugin_' . $name . '_init() { return TRUE; }
			function plugin_' . $name . '_convert() {
				return plugin_pkript_dispatch(
					array_merge(array(' . $quoted . '), func_get_args()), "convert");
			}
			function plugin_' . $name . '_inline() {
				return plugin_pkript_dispatch(
					array_merge(array(' . $quoted . '), func_get_args()), "inline");
			}
			function plugin_' . $name . '_action() {
				return plugin_pkript_run(array(' . $quoted . '), "action", "");
			}
		');
	}
	return TRUE;
}

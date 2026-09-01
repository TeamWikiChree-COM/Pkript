<?php
// $Id: defaults.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - default settings
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// What the runtime needs a value for, whatever it is running inside.
//
// Loaded last, after the environment has had its say - see the bottom of
// plugin/pkript.inc.php - so every one of these is a fallback and none of
// them overrides a setting. An environment that configures Pkript never
// reaches these values; one that only wants to run a script gets a working
// set without having to know what a step counter is.
//
// Only settings the portable half reads are here. What a particular host
// needs - where scripts are kept, what a data key is stored as - is that
// host's to define; see plugin/pkript.inc.php for PukiWiki's.

/////////////////////////////////////////////////
// Trust
//
// How far a script is trusted, as an integer: bigger is more. Which sources
// sit where on the scale is the host's answer, since only the host knows
// where a script came from - PukiWiki's ladder is in pkript.inc.php. The
// runtime only ever compares.

// A script nobody vouched for.
if (!defined('PKRIPT_TRUST_NONE'))
	define('PKRIPT_TRUST_NONE', 0);

// As trusted as a script gets, and what a caller that does not say where a
// script came from is taken to mean: it handed the runtime that script on
// purpose.
if (!defined('PKRIPT_TRUST_FULL'))
	define('PKRIPT_TRUST_FULL', 2);

/////////////////////////////////////////////////
// Text
//
// The encoding every string a script handles is in. Taken from the host when
// it has said - PukiWiki's SOURCE_ENCODING is EUC-JP on an old wiki - so that
// mb_* counts characters the way the pages they came from are written.

if (!defined('PKRIPT_ENCODING')) {
	define('PKRIPT_ENCODING',
		defined('SOURCE_ENCODING') ? SOURCE_ENCODING : 'UTF-8');
}

/////////////////////////////////////////////////
// Syntax

if (!defined('PKRIPT_JSX'))
	define('PKRIPT_JSX', 1);
if (!defined('PKRIPT_REGEX'))
	define('PKRIPT_REGEX', 1);
if (!defined('PKRIPT_REGEX_BACKTRACK'))
	define('PKRIPT_REGEX_BACKTRACK', 100000);
if (!defined('PKRIPT_MAX_REGEX'))
	define('PKRIPT_MAX_REGEX', 512);

/////////////////////////////////////////////////
// Stored data

if (!defined('PKRIPT_ALLOW_DATA'))
	define('PKRIPT_ALLOW_DATA', 1);

/////////////////////////////////////////////////
// Imports
//
// PKRIPT_AST_VERSION is not a setting: it names the shape of the parse tree,
// so that a cache written by an older runtime is ignored rather than
// misread. Bump it when that shape changes.
if (!defined('PKRIPT_AST_VERSION'))
	define('PKRIPT_AST_VERSION', 2);
if (!defined('PKRIPT_MAX_IMPORTS'))
	define('PKRIPT_MAX_IMPORTS', 16);
if (!defined('PKRIPT_MAX_IMPORT_DEPTH'))
	define('PKRIPT_MAX_IMPORT_DEPTH', 4);

// Let a script import one less trusted than itself. Off: otherwise the author
// of an editable script could inject code into a trusted one.
if (!defined('PKRIPT_IMPORT_LOWER_TRUST'))
	define('PKRIPT_IMPORT_LOWER_TRUST', 0);

/////////////////////////////////////////////////
// Limits, per request

if (!defined('PKRIPT_MAX_STEPS'))
	define('PKRIPT_MAX_STEPS', 1000000);
if (!defined('PKRIPT_MAX_TIME'))
	define('PKRIPT_MAX_TIME', 3);
if (!defined('PKRIPT_MAX_MEMORY'))
	define('PKRIPT_MAX_MEMORY', Pkript_Budget::defaultLimit());
if (!defined('PKRIPT_MAX_CONVERT'))
	define('PKRIPT_MAX_CONVERT', 32);
if (!defined('PKRIPT_MAX_READS'))
	define('PKRIPT_MAX_READS', 5000);
if (!defined('PKRIPT_MAX_WRITES'))
	define('PKRIPT_MAX_WRITES', 4);

/////////////////////////////////////////////////
// Limits, per run or per value

if (!defined('PKRIPT_MAX_DEPTH'))
	define('PKRIPT_MAX_DEPTH', 64);
if (!defined('PKRIPT_MAX_LOOP'))
	define('PKRIPT_MAX_LOOP', 100000);
if (!defined('PKRIPT_MAX_STRING'))
	define('PKRIPT_MAX_STRING', 1048576);
if (!defined('PKRIPT_MAX_ARRAY'))
	define('PKRIPT_MAX_ARRAY', 10000);
if (!defined('PKRIPT_MAX_PAGE_BYTES'))
	define('PKRIPT_MAX_PAGE_BYTES', 524288);
if (!defined('PKRIPT_MAX_PAGES'))
	define('PKRIPT_MAX_PAGES', 1000);

/////////////////////////////////////////////////
// Logging

if (!defined('PKRIPT_MAX_LOG'))
	define('PKRIPT_MAX_LOG', 100);
if (!defined('PKRIPT_MAX_LOG_BYTES'))
	define('PKRIPT_MAX_LOG_BYTES', 8192);
if (!defined('PKRIPT_DEBUG'))
	define('PKRIPT_DEBUG', 0);

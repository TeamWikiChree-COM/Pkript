<?php
// $Id: boot.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - manifest
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// Everything the runtime is made of, in load order. The same idea as
// std/stdlib.php one level down: an entry point requires this file and
// nothing else, so adding or splitting a file is a line here and changes
// nothing outside lib/.
//
// Nothing listed here knows which environment it is in. What a particular
// host adds is its own manifest - see adapters/pukiwiki/boot.php.

foreach (array(
	// The environment, and the adapters that stand in for one
	'compat',
	'env',
	'adapter/store',
	'adapter/script_source',
	'adapter/ast_cache',
	'adapter/default/store_memory',
	'adapter/default/script_source_file',
	'adapter/default/ast_cache_null',

	// The language
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
	'loader'
) as $pkript_module) {
	require_once dirname(__FILE__) . '/' . $pkript_module . '.php';
}
unset($pkript_module);

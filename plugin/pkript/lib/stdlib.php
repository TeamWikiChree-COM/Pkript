<?php
// $Id: stdlib.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - standard library
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// The standard library lives in lib/std/.
//
// This file is its manifest, so the rest of the runtime only ever has to know
// that 'stdlib' exists: adding a module means adding a line here and a line in
// Pkript_Stdlib::$namespaces, and nothing outside lib/std/ changes.
//
// Order matters only for the first two - the base classes the modules extend,
// and the registry that names them.

foreach (array(
	'module',
	'package',
	'package_core',
	'registry',

	// One class per API namespace
	'html',
	'json',
	'date',
	'math',
	'object',
	'array_ns',
	'number_ns',
	'lang',
	'console',
	'data',

	// One class per value type that has methods
	'regex_methods',
	'string_methods',
	'array_methods',
	'number_methods'
) as $pkript_std_module) {
	require_once dirname(__FILE__) . '/std/' . $pkript_std_module . '.php';
}
unset($pkript_std_module);

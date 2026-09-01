<?php
// $Id: boot.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - PukiWiki manifest
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// What running inside PukiWiki adds: the adapters that reach the wiki, and
// the plumbing of a request - finding a script, caching it, checking a token,
// running it and turning the answer into a page.
//
// Split from lib/boot.php so the line is a directory rather than a rule
// somebody has to remember: not loading this file is what running Pkript
// somewhere else means.

foreach (array(
	'std/wiki_writer',
	'std/wiki',
	'package',
	'store',
	'script_source',
	'cache',
	'loader',
	'security',
	'run'
) as $pkript_module) {
	require_once dirname(__FILE__) . '/' . $pkript_module . '.php';
}
unset($pkript_module);

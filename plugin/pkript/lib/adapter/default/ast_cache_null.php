<?php
// $Id: ast_cache_null.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - no AST cache
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Parse every time.
 *
 * The default, because a cache has to be put somewhere and Pkript does not
 * get to choose a directory on a machine it knows nothing about. Correct, and
 * slower than it needs to be - an environment that runs the same scripts
 * repeatedly should supply a real one.
 */
class Pkript_AstCache_Null implements Pkript_AstCache {
	public function load($name) {
		return FALSE;
	}

	public function save($name, $entry) {
	}
}

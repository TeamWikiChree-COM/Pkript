<?php
// $Id: ast_cache.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - AST cache adapter
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Somewhere to keep a parsed script between requests.
 *
 * Parsing is most of the cost of a small script, and the AST is plain arrays,
 * so it survives being stored. Only the parse is ever kept, never what a
 * script produced: Math.random() and e.vars go on working as they should.
 *
 * An entry is an opaque array. Whether the one that comes back is still good -
 * every script that went into it, its content and its trust - is
 * Pkript_Loader's to decide and is decided on every load, so an
 * implementation may hand back a stale entry and does not have to expire
 * anything. It does have to hand back an array or FALSE and never anything
 * else; where an entry is stored somewhere a person could edit, that is a
 * shape to check, not to trust.
 */
interface Pkript_AstCache {
	/** @return array|FALSE whatever save() was last given for $name */
	public function load($name);

	/** Failure is silent: it is a cache. @return void */
	public function save($name, $entry);
}

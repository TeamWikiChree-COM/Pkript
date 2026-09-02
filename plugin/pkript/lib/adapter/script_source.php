<?php
// $Id: script_source.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - script source adapter
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Where a script called by name is found, and how far it is trusted.
 *
 * Only the environment can answer either: a name means a file here, a wiki
 * page there, a row in a table somewhere else, and how much a script may do
 * follows from which of those it turned out to be. Pkript_Loader takes the
 * answer and does the rest - imports, limits, clashes - the same way
 * everywhere.
 *
 * Trust is an integer, bigger is more; see lib/defaults.php. A source that
 * has no way to tell one script from another should say PKRIPT_TRUST_FULL for
 * all of them and mean it, or PKRIPT_TRUST_NONE and mean that.
 */
interface Pkript_ScriptSource {
	/**
	 * @param string $reason out: why nothing was found, for the error the
	 *        script's author will read
	 * @return array|FALSE array('source' => string, 'trust' => int)
	 */
	public function find($name, &$reason);

	/**
	 * Whatever, if changed, should make a parse cached earlier stop counting -
	 * where this source looks, what it accepts, what it decides about trust.
	 *
	 * Only its equality is used, so the cheapest honest answer is right: a
	 * source with nothing to configure can return its own class name.
	 *
	 * @return string
	 */
	public function signature();
}

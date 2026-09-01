<?php
// $Id: package.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - standard library package
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * A set of names a script can reach, offered as one piece.
 *
 * There is no central list of what the standard library contains: a package
 * says what it brings, and Pkript_Stdlib is whatever the packages an
 * environment loaded add up to. So an environment widens a script's reach by
 * adding a package, and narrows it by not adding one - neither of which is
 * an edit to a table somebody else owns.
 *
 * Nothing here is per-run: a package describes classes, and the registry
 * builds at most one of each, only when a script first reaches for it.
 */
interface Pkript_Package {
	/**
	 * API namespaces, e.g. `Math` or `wiki`.
	 *
	 * @return array namespace name -> Pkript_Std_Module subclass name
	 */
	public function namespaces();

	/**
	 * The methods of a value type, e.g. everything `"abc".` can reach.
	 *
	 * @return array type label (see Pkript_Stdlib::typeOf) ->
	 *               Pkript_Std_Methods subclass name
	 */
	public function types();

	/**
	 * Bare names, each an alias for a namespaced member: 'htmlsc' reaching
	 * 'html.escape'. A package may alias a member of another package's
	 * namespace; the alias simply does not resolve if that package is absent.
	 *
	 * @return array bare name -> 'namespace.member'
	 */
	public function globals();

	/**
	 * Bare values, e.g. NaN. Constants of a namespace - Math.PI - are the
	 * module's own business and are declared by its constants().
	 *
	 * @return array name -> value
	 */
	public function constants();
}

/**
 * Common ground for a package, so one only writes down what it actually has.
 */
abstract class Pkript_Package_Base implements Pkript_Package {
	public function namespaces() { return array(); }
	public function types()      { return array(); }
	public function globals()    { return array(); }
	public function constants()  { return array(); }
}

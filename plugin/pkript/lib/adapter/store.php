<?php
// $Id: store.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - store adapter
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Where data.* keeps what a script wrote.
 *
 * The split with Pkript_Std_Data is by who owns the rule, not by what the
 * call does:
 *
 *   - Pkript's own policy - whether the store is on at all, what a key may
 *     look like, how much trust a write needs, JSON, the budget - stays in
 *     Pkript_Std_Data, so it reads the same wherever Pkript runs.
 *   - The environment's policy - what a key is stored as, whether this
 *     request is allowed to write anything - is here.
 *
 * An implementation is handed keys that Pkript_Std_Data has already checked,
 * and text it has already sized. It stores the text as given and hands the
 * same text back; anything it has to add to survive its own storage (a
 * header, an escape) it must also take off again in get().
 */
interface Pkript_Store {
	/** @return string|NULL the text set() was given, or NULL if never written */
	public function get($key);

	/** @return void */
	public function set($key, $text);

	/** @return bool FALSE if the key was not there */
	public function remove($key);

	/** @return bool */
	public function has($key);

	/** @param string $prefix '' for every key. @return array of key strings */
	public function keys($prefix);

	/**
	 * Why the environment could not accept this write at all - a wiki in read
	 * only mode, a build with no way to write pages, a script not trusted
	 * enough to be storing anything.
	 *
	 * Nothing a form can change, so this is the half data.canWrite() reports:
	 * it is asked while a page renders, to decide whether drawing that form is
	 * worth it.
	 *
	 * @param int $trust how far the running script is trusted; bigger is
	 *        more. What the number means, and how much of it a write takes,
	 *        are the host's - the runtime only carries it here.
	 * @return string the reason, or '' if the environment allows writes
	 */
	public function refusal($trust);

	/**
	 * Why this particular request may not write - not the POST a form made,
	 * no valid token.
	 *
	 * Split from refusal() because the request being wrong is exactly what the
	 * form is about to put right, so canWrite() must not report it.
	 *
	 * @return string the reason, or '' if this request may write
	 */
	public function requestRefusal();
}

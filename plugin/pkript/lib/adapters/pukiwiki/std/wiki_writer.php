<?php
// $Id: wiki_writer.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - wiki page writing
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * The one place a script can change the wiki, kept apart from the rest of the
 * wiki namespace because it is a security policy, not an API.
 *
 * refusal() is that policy in full: every check in it is one PukiWiki 1.5.4
 * does not make for us. do_plugin_action() does no authentication,
 * page_write() sees only PKWK_READONLY, and the core has no CSRF token at
 * all. It is a *list of reasons* rather than a boolean so
 * wiki.canWrite() and wiki.write() can never drift apart - the same function
 * answers both, one reporting the reason and the other refusing on it.
 */
class Pkript_Std_WikiWriter extends Pkript_Std_Base {
	/**
	 * @return string why a write to $page would be refused, '' if it would
	 *                go through
	 */
	public function refusal($page) {
		$refusal = $this->pageRefusal($page);
		if ($refusal !== '')
			return $refusal;
		return $this->requestRefusal();
	}

	/**
	 * The half of the policy a form cannot change: this script, this page,
	 * this wiki. Trust, freezing and $edit_auth_pages are the same answer
	 * whether the visitor has pressed the button yet or not.
	 *
	 * This is what wiki.canWrite() asks, and it has to be: canWrite() is
	 * called while a page renders, which is the only time a form is drawn,
	 * and the request checks are all false then by design. Asking the whole
	 * policy there would hide every form there is.
	 *
	 * @return string the reason, or '' if nothing about the page refuses
	 */
	public function pageRefusal($page) {
		// Is this script allowed to write at all?
		if ($this->rt->trust() < PKRIPT_WRITE_MIN_TRUST)
			return 'This script may not write pages';
		if (defined('PKWK_READONLY') && PKWK_READONLY)
			return 'The wiki is read only';

		// May this particular page be written? ':' pages hold the wiki's own
		// configuration - and the page scripts themselves, so writing one
		// would rewrite the sandbox.
		if ($page === '' || strpos($page, ':') === 0)
			return 'Not a writable page name';
		if (!function_exists('page_write'))
			return 'This environment cannot write pages';
		if (function_exists('is_freeze') && is_freeze($page))
			return 'The page is frozen';
		if (function_exists('check_editable') && !check_editable($page, TRUE, FALSE))
			return 'No permission to edit the page';

		return '';
	}

	/**
	 * The other half: did the visitor actually ask for this write, in this
	 * request? Every one of these is something the form the script draws
	 * supplies, which is why canWrite() leaves them out.
	 *
	 * @return string the reason, or '' if the request may write
	 */
	public function requestRefusal() {
		// 'action' is only reachable as ?cmd=pkript, never while a page is
		// being rendered.
		if ($this->rt->entryType() !== 'action')
			return 'Writing a page is only allowed from an action';

		$method = isset($_SERVER['REQUEST_METHOD'])
			? strtoupper($_SERVER['REQUEST_METHOD']) : '';
		if ($method !== 'POST')
			return 'Writing a page is only allowed from a POST';
		if (!plugin_pkript_check_token())
			return 'Invalid token';
		return '';
	}

	/** @return bool TRUE once the page is written */
	public function write($page, $text, $append, $node) {
		$refusal = $this->refusal($page);
		if ($refusal !== '')
			$this->rt->fail($refusal, $node);

		if (!$this->rt->budget()->spendWrite()) {
			$this->rt->failLimit('Too many page writes (limit ' .
				PKRIPT_MAX_WRITES . ')', $node);
		}

		if ($append)
			$text = self::appendTo($page, $text);

		if (strlen($text) > PKRIPT_MAX_PAGE_BYTES) {
			$this->rt->failLimit('Page too large (limit ' .
				PKRIPT_MAX_PAGE_BYTES . ' bytes)', $node);
		}

		page_write($page, $text);
		return TRUE;
	}

	private static function appendTo($page, $text) {
		$current = is_page($page) ? get_source($page, TRUE, TRUE) : '';
		// Keep the two sides from running into one line
		if ($current !== '' && substr($current, -1) !== "\n")
			$current .= "\n";
		return $current . $text;
	}
}

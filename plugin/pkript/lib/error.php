<?php
// $Id: error.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - error type
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// Error

class Pkript_Error extends Exception {
	// NOTE: named with a prefix because Exception already owns $line and $file
	private $psScript;
	private $psLine;
	private $psCol;

	public function __construct($message, $script = '', $line = 0, $col = 0) {
		parent::__construct($message);
		$this->psScript = $script;
		$this->psLine = $line;
		$this->psCol = $col;
	}

	/**
	 * Message shown to the user. Never exposes file paths or PHP internals.
	 */
	public function getScriptMessage() {
		$where = '';
		if ($this->psLine > 0) {
			$where = ' (' . $this->psScript . ':' . $this->psLine . '行目';
			if (PKRIPT_DEBUG && $this->psCol > 0) {
				$where .= ' ' . $this->psCol . '列';
			}
			$where .= ')';
		}
		return $this->getMessage() . $where;
	}
}

/**
 * A resource limit was reached. Kept apart from Pkript_Error because
 * `catch` must not be able to swallow one - a script that could would have
 * no limits at all.
 */
class Pkript_LimitError extends Pkript_Error {
}

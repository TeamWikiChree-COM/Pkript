<?php
// $Id: error.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

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
	 * Move the reported line numbers by $n.
	 *
	 * For source the runtime assembled rather than read: #pks wraps the text
	 * on the page in a function declaration, so every line inside it is one
	 * further down than the author wrote it. Shifting here rather than at
	 * every throw site keeps the wrapping the concern of whoever did it.
	 */
	public function shiftLines($n) {
		$this->psLine += $n;
		return $this;
	}

	/**
	 * Message shown to the user. Never exposes file paths or PHP internals.
	 *
	 * The place is written script:line:col, the way a JavaScript stack trace
	 * writes one. Without PKRIPT_DEBUG there is no column to write.
	 */
	public function getScriptMessage() {
		$where = '';
		if ($this->psLine > 0) {
			$where = ' (' . $this->psScript . ':' . $this->psLine;
			if (PKRIPT_DEBUG && $this->psCol > 0)
				$where .= ':' . $this->psCol;
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

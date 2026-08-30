<?php
// $Id: error.php,v 0.3 2026/08/30 00:00:00 Pitan Exp $

/**
* Pkript runtime - error type
*
* @link https://blog.pitan76.net/?Pkript
* @author Pitan
* @license https://opensource.org/license/mit MIT
*/

// Loaded by plugin/pkript.inc.php; not meant to be requested directly.
if (! defined('PKRIPT_RUNTIME')) exit;

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
		$this->psLine   = $line;
		$this->psCol    = $col;
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

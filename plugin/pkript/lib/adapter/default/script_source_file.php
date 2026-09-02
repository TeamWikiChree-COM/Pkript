<?php
// $Id: script_source_file.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - scripts in a directory
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Scripts as files in one directory, which is what an embedder usually has.
 *
 * Everything found is trusted equally and completely: whoever can write to
 * that directory could have edited the program itself, so there is nothing
 * left to withhold. A host that does have degrees - a wiki where some pages
 * are frozen and some are not - needs a source of its own.
 *
 * The name is checked by Pkript_Loader before it gets here, so it is letters,
 * digits, '_' and '-'; the directory is joined to it and nothing else, and no
 * name can leave it.
 */
class Pkript_ScriptSource_File implements Pkript_ScriptSource {
	private $dir;
	private $extensions;

	/** @param array $extensions tried in order, without the dot */
	public function __construct($dir, $extensions = array('pks', 'js')) {
		$this->dir = rtrim($dir, '/\\') . '/';
		$this->extensions = $extensions;
	}

	public function find($name, &$reason) {
		foreach ($this->extensions as $ext) {
			$file = $this->dir . $name . '.' . $ext;
			if (is_file($file) && is_readable($file)) {
				$source = file_get_contents($file);
				if ($source !== FALSE)
					return array('source' => $source, 'trust' => PKRIPT_TRUST_FULL);
			}
		}
		$reason = 'Script not found: ' . $name;
		return FALSE;
	}

	public function signature() {
		return 'file:' . $this->dir . ':' . implode(',', $this->extensions);
	}
}

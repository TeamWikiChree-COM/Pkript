<?php
// $Id: script_source.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript - scripts in a wiki
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * A script is a file an administrator put there, or a wiki page.
 *
 * The two places are why this wiki has degrees of trust at all. A file under
 * DATA_HOME can only have been put there by someone with the run of the
 * server, so it is trusted completely; a page under PKRIPT_PAGE_PREFIX can be
 * written by anyone who may edit it, and a frozen one sits between the two.
 *
 * Files are looked at first, so an administrator can always override a page.
 */
class Pkript_ScriptSource_Wiki implements Pkript_ScriptSource {
	public function find($name, &$reason) {
		// 1. script/<name>.<ext> - '.pks' and '.js' are equivalent
		foreach (plugin_pkript_extensions() as $ext) {
			$file = DATA_HOME . PKRIPT_SCRIPT_DIR . $name . '.' . $ext;
			if (is_file($file) && is_readable($file)) {
				return array(
					'source' => file_get_contents($file),
					'trust' => PKRIPT_TRUST_FILE,
				);
			}
		}

		// 2. :config/pkript/script/<name>
		if (PKRIPT_ALLOW_PAGE_SCRIPT) {
			$page = PKRIPT_PAGE_PREFIX . $name;
			if (is_page($page)) {
				$frozen = function_exists('is_freeze') && is_freeze($page);
				if (PKRIPT_PAGE_SCRIPT_FROZEN_ONLY && !$frozen) {
					$reason = 'Script page is not frozen: ' . $page;
					return FALSE;
				}
				return array(
					'source' => self::stripMetadata(get_source($page, TRUE, TRUE)),
					'trust' => $frozen ? PKRIPT_TRUST_FROZEN : PKRIPT_TRUST_PAGE,
				);
			}
		}

		$reason = 'Script not found: ' . $name;
		return FALSE;
	}

	/**
	 * Everything that decides what a name resolves to, and to what trust. A
	 * wiki that starts accepting page scripts must not go on using parses
	 * made while it did not.
	 */
	public function signature() {
		return 'wiki:' . implode(':', array(
			PKRIPT_SCRIPT_DIR,
			PKRIPT_SCRIPT_EXT,
			PKRIPT_ALLOW_PAGE_SCRIPT,
			PKRIPT_PAGE_SCRIPT_FROZEN_ONLY,
			PKRIPT_PAGE_PREFIX,
		));
	}

	/**
	 * Remove PukiWiki page metadata that get_source() leaves in place.
	 * Blank the lines instead of deleting them so error line numbers still
	 * match what the user sees in the page editor.
	 */
	private static function stripMetadata($source) {
		$lines = explode("\n", $source);
		foreach ($lines as $i => $line) {
			if (preg_match('/^#(freeze|author\()/', $line)) {
				$lines[$i] = '';
			} else {
				break; // metadata only appears at the top of the page
			}
		}
		return implode("\n", $lines);
	}
}

/** Extensions accepted for script files, normalized and de-duplicated. */
function plugin_pkript_extensions() {
	static $exts = NULL;
	if ($exts !== NULL)
		return $exts;

	$exts = array();
	foreach (explode(',', PKRIPT_SCRIPT_EXT) as $ext) {
		$ext = strtolower(ltrim(trim($ext), '.'));
		// Alphanumeric only: an extension is pasted straight into a path
		if ($ext === '' || !preg_match('/^[a-z0-9]+$/', $ext))
			continue;
		if (!in_array($ext, $exts, TRUE))
			$exts[] = $ext;
	}
	if (empty($exts))
		$exts = array('pks');
	return $exts;
}

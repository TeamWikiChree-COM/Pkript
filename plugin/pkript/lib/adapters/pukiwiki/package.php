<?php
// $Id: package.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - PukiWiki package
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * What a script can reach because it is running inside a wiki.
 *
 * The bare names are the PukiWiki spellings, so code moved over from a PHP
 * plugin works as written. They are aliases and nothing more - is_page() and
 * wiki.exists() are the same call - and they are here rather than in the
 * core package because a script that uses them is a script about a wiki.
 */
class Pkript_Package_PukiWiki extends Pkript_Package_Base {
	public function namespaces() {
		return array(
			'wiki' => 'Pkript_Std_Wiki',
		);
	}

	public function globals() {
		return array(
			'is_page' => 'wiki.exists',
			'make_pagelink' => 'wiki.link',
			'convert_html' => 'wiki.convert',
			'strip_bracket' => 'wiki.stripBracket',
			'get_source' => 'wiki.source',
			'get_existpages' => 'wiki.pages',
			'encode' => 'wiki.encode',
			'decode' => 'wiki.decode',
			'get_filetime' => 'wiki.time',
			'is_freeze' => 'wiki.isFrozen',
		);
	}
}

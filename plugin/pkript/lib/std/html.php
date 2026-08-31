<?php
// $Id: html.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - html namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Percent-encoding, for the query strings a script builds by hand.
 *
 * wiki.uri() covers a link to a page; this covers everything else - a vote
 * link, a filter, a page number. Without it a script that writes
 * '?choice=' + value produces a broken link the moment a value holds '&'.
 */
class Pkript_Std_Url extends Pkript_Std_Module {
	public static function members() {
		return array('encode', 'decode');
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'encode':
				// rawurlencode: RFC 3986, so a space is %20 rather than '+'.
				// '+' only means a space in a form body, and this is used to
				// build paths and query values alike.
				return $this->rt->checkString(
					rawurlencode($this->strArg($args, 0)), $node);

			case 'decode':
				$text = rawurldecode($this->strArg($args, 0));
				// Decoding can produce bytes that are not UTF-8, and those
				// would send the whole output down the sanitizer's
				// escape-everything path. Same rule as wiki.decode().
				if (!mb_check_encoding($text, SOURCE_ENCODING))
					return '';
				return $this->rt->checkString($text, $node);
		}
	}
}

class Pkript_Std_Html extends Pkript_Std_Module {
	public static function members() {
		return array('escape', 'br', 'strip');
	}

	public function call($name, $args, $node) {
		$s = $this->strArg($args, 0);
		switch ($name) {
			case 'escape':
				// ENT_QUOTES: PukiWiki's htmlsc() defaults to ENT_COMPAT,
				// which leaves ' alone
				return htmlsc($s, ENT_QUOTES);
			case 'br':
				return nl2br($s, TRUE);
			case 'strip':
				return strip_tags($s);
		}
	}
}

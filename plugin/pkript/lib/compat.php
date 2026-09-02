<?php
// $Id: compat.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - host compatibility
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// The few host functions the language core needs, each with a fallback for
// running outside PukiWiki.
//
// A function rather than an interface on purpose: the core reaches for these
// from static contexts (Pkript_Sanitizer is static throughout, so is
// Pkript_Interpreter::jsxText), where there is no $rt to route a call
// through. Only put something here that a plain PHP function can answer;
// anything with a policy behind it belongs to a module instead.

/**
 * Escape text for HTML.
 *
 * PukiWiki's htmlsc() is htmlspecialchars() under a shorter name, so there is
 * nothing for a host to do differently - the fallback is the same call.
 */
if (!function_exists('pkript_htmlsc')) {
	function pkript_htmlsc($string = '', $flags = ENT_COMPAT) {
		return function_exists('htmlsc')
			? htmlsc($string, $flags)
			: htmlspecialchars($string, $flags, defined('PKRIPT_ENCODING') ? PKRIPT_ENCODING : 'UTF-8');
	}
}

/**
 * The entry point a form with no action of its own should post back to.
 *
 * A host builds this from settings only it has - PukiWiki's get_base_uri()
 * reads $script_directory_index and its URI handler - so there is nothing to
 * compute here, only somewhere to ask.
 *
 * The fallback posts back to the current URL, which is right whenever the
 * page a script rendered into is also what handles the post. A host where it
 * is not should define pkript_self_uri() itself.
 */
if (!function_exists('pkript_self_uri')) {
	function pkript_self_uri() {
		if (function_exists('get_base_uri'))
			return get_base_uri();
		if (function_exists('get_script_uri'))
			return get_script_uri();
		return './';
	}
}

/**
 * Now, in the units a host counts time in.
 *
 * PukiWiki keeps times as "seconds since the epoch, minus the server's
 * offset" and adds the zone back when it prints one, so a timestamp taken
 * here and a timestamp on a page mean the same thing. Without a host that is
 * simply the epoch.
 */
if (!function_exists('pkript_now')) {
	function pkript_now() {
		if (defined('UTIME'))
			return UTIME;
		return time() - (defined('LOCALZONE') ? LOCALZONE : 0);
	}
}

/** What to add to one of those before printing it. */
if (!function_exists('pkript_zone_offset')) {
	function pkript_zone_offset() {
		return defined('ZONETIME') ? ZONETIME : 0;
	}
}

/**
 * A timestamp written the way this host writes one.
 *
 * A wiki has a house style for a date - PukiWiki's format_date() follows
 * $date_format and the reader's zone - and a script asking for the default
 * format is asking for that, not for one Pkript invented.
 *
 * The fallback is ISO-ish and unambiguous, which is the right thing to be
 * when there is no house style to follow.
 *
 * @param bool $paren wrap it in parentheses, as a wiki does beside a heading
 */
if (!function_exists('pkript_format_date')) {
	function pkript_format_date($time, $paren = FALSE) {
		if (function_exists('format_date'))
			return format_date($time, $paren);

		// The host's own formatter is given the time as it stands, because a
		// host that has a house style also has an opinion about the zone.
		// Only this fallback, which has neither, does the shifting.
		$text = date('Y-m-d H:i:s', $time + pkript_zone_offset());
		return $paren ? '(' . $text . ')' : $text;
	}
}

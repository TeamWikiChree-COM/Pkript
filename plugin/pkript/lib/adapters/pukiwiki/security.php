<?php
// $Id: security.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript - write token
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/** Per-site secret behind the token, created on first use. FALSE if it cannot be. */
function plugin_pkript_secret() {
	static $secret = NULL;
	if ($secret !== NULL)
		return $secret;

	$file = PKRIPT_SECRET_FILE;
	if (is_file($file) && is_readable($file)) {
		$raw = file_get_contents($file);
		if (strlen($raw) >= 32)
			return $secret = $raw;
	}

	$dir = dirname($file);
	if (!is_dir($dir) || !is_writable($dir))
		return $secret = FALSE;

	$raw = function_exists('random_bytes') ? random_bytes(32) : '';
	if ($raw === '')
		return $secret = FALSE;
	// LOCK_EX so two requests racing on first use do not write half a secret
	if (file_put_contents($file, $raw, LOCK_EX) === FALSE)
		return $secret = FALSE;
	@chmod($file, 0600);
	return $secret = $raw;
}

/**
 * Token a script embeds in its own form. PukiWiki 1.5.4 has no CSRF machinery,
 * so this is Pkript's. Tied to the authenticated user - which means it is only
 * site-wide on a wiki with no authentication. 
 *
 * @return string '' when no secret is available
 */
function plugin_pkript_token() {
	$secret = plugin_pkript_secret();
	if ($secret === FALSE)
		return '';
	return hash_hmac('sha256', 'pkript:write:' . plugin_pkript_identity(), $secret);
}

/** Who the request is authenticated as, '' when nobody. */
function plugin_pkript_identity() {
	if (isset($_SESSION['authenticated_user']))
		return (string) $_SESSION['authenticated_user'];
	if (isset($_SERVER['PHP_AUTH_USER']))
		return (string) $_SERVER['PHP_AUTH_USER'];
	return '';
}

/** Does this request carry the token for its own identity? */
function plugin_pkript_check_token() {
	global $vars;
	$want = plugin_pkript_token();
	if ($want === '')
		return FALSE;
	$got = isset($vars['pkript_token']) ? (string) $vars['pkript_token'] : '';
	return $got !== '' && hash_equals($want, $got);
}

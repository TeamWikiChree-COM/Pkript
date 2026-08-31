<?php
// $Id: run.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript - plugin entry point plumbing
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Split PukiWiki's argument list into script arguments and the body, then run.
 * Shared by #pkript and by the wrappers plugin_pkript_bind() generates, so a
 * script called under its own name behaves identically.
 *
 * @param array $args $args[0] is the script name
 */
function plugin_pkript_dispatch($args, $type) {
	$body = '';

	if ($type === 'convert') {
		// #pkript(name){{ ... }} appends the body as the last argument, with
		// its lines joined by "\r", not "\n" (convert_html.php, "Delimiter").
		if (count($args) > 1 && strpos(end($args), "\r") !== FALSE)
			$body = plugin_pkript_normalize_newlines(array_pop($args));
	} else {
		// PukiWiki always passes {text} last, even when empty
		if (count($args) > 1) {
			$text = array_pop($args);
			if ($text !== NULL)
				$body = plugin_pkript_normalize_newlines($text);
		}
	}

	return plugin_pkript_run($args, $type, $body);
}

/** PukiWiki delimits multiline plugin bodies with "\r"; scripts expect "\n". */
function plugin_pkript_normalize_newlines($text) {
	return str_replace(array("\r\n", "\r"), "\n", $text);
}

/**
 * Request variables, as e.vars.
 *
 * $vars is not always the script's own form: previewing an edit POSTs 'pass',
 * the password just typed, and a script in that page runs during the request.
 * Array values (from 'name[]') are skipped; e.vars holds strings.
 */
function plugin_pkript_request_vars() {
	global $vars;

	static $denied = array(
	'pass' => 1,
	'password' => 1,
	'passwd' => 1,
	'encode_hint' => 1,
	'original' => 1,
	'digest' => 1,
	);

	$out = array();
	if (!is_array($vars))
		return $out;
	foreach ($vars as $key => $value) {
		if (isset($denied[strtolower($key)]))
			continue;
		if (!is_scalar($value))
			continue;
		$out[$key] = (string) $value;
	}
	return $out;
}

/**
 * Who is viewing, as e.user.
 *
 * PukiWiki has worked this out before any plugin runs: lib/pukiwiki.php calls
 * ensure_valid_auth_user(), which fills the globals read here. So this is a
 * read, not an authentication, and every run can afford it.
 *
 * All three keys are always there, because the language has no optional
 * chaining and reading a property that does not exist is an error: a script
 * has to be able to write e.user.name without first asking whether anyone is
 * logged in. Nobody logged in is the empty string and the empty array.
 *
 * There is no 'admin' key. PukiWiki has no administrator identity to report:
 * pkwk_login() compares a password sent with the request against $adminpass
 * and keeps nothing, so no page ever knows whether its viewer is one. Use
 * wiki.canWrite(page) for what a viewer may do, or groups for who they are.
 */
function plugin_pkript_user() {
	global $auth_user, $auth_user_fullname, $auth_user_groups;

	$name = (isset($auth_user) && is_string($auth_user)) ? $auth_user : '';
	if ($name === '') {
		return array('name' => '', 'fullname' => '', 'groups' => array());
	}

	// Same fallback add_author_info() uses when the fullname is empty, prefix
	// stripped and all, so e.user.fullname and the #author line of a page
	// saved in the same request agree with each other
	$fullname = (isset($auth_user_fullname) && is_string($auth_user_fullname))
		? $auth_user_fullname : '';
	if ($fullname === '')
		$fullname = preg_replace('/^[^:]*:/', '', $name);

	// get_groups_from_username() puts the user's own name and 'valid-user' in
	// here as well, so the array is never empty for someone logged in
	$groups = array();
	if (isset($auth_user_groups) && is_array($auth_user_groups)) {
		foreach ($auth_user_groups as $group) {
			if (is_scalar($group))
				$groups[] = (string) $group;
		}
	}

	return array('name' => $name, 'fullname' => $fullname, 'groups' => $groups);
}

/**
 * Load, parse and run a script, then sanitize its output.
 *
 * @param array  $args  [0] is the script name, the rest are passed to the script
 * @param string $type  'convert' | 'inline' | 'action'
 * @param string $body  multiline body, exposed as e.body
 * @return string HTML
 */
function plugin_pkript_run($args, $type, $body = '') {
	global $vars;

	$name = isset($args[0]) ? trim($args[0]) : '';
	$args = array_slice($args, 1);

	if ($name === '')
		return plugin_pkript_error('Usage: #pkript(name, arg...)');
	if (!preg_match('/^[A-Za-z0-9_-]+$/', $name))
		return plugin_pkript_error('Invalid script name: ' . $name);


	// '#pkript(hello, World)' splits into 'hello' and ' World'; trim so scripts
	// do not have to deal with the separator whitespace.
	$args = array_map('trim', array_values($args));

	$context = new Pkript_Obj(array(
		'args' => $args,
		'opts' => new Pkript_Obj(plugin_pkript_parse_opts($args)),
		'body' => $body,
		'page' => isset($vars['page']) ? $vars['page'] : '',
		'name' => $name,
		'type' => $type,
		'vars' => new Pkript_Obj(plugin_pkript_request_vars()),
		'user' => new Pkript_Obj(plugin_pkript_user()),
		'method' => (isset($_SERVER['REQUEST_METHOD']) &&
			strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') ? 'POST' : 'GET',
	));

	$interp = NULL;
	try {
		$reason = '';
		$trust = PKRIPT_TRUST_FILE;
		$constants = array();
		$program = plugin_pkript_compile($name, $trust, $reason, $constants);
		if ($program === FALSE)
			return plugin_pkript_error($reason);

		$interp = new Pkript_Interpreter($program, $name, $trust, $constants);
		$result = $interp->callEntryPoint('plugin_' . $name . '_' . $type, $context);
	} catch (Pkript_Redirect $to) {
		// The script asked to end the request somewhere else. Nothing it
		// produced is sent: a redirect has no body to put it in.
		return plugin_pkript_send_redirect($to->page);
	} catch (Pkript_Error $err) {
		// The log comes first here: it is what the script managed to say
		// before it died, so it reads as leading up to the error
		return plugin_pkript_logs($interp) .
			plugin_pkript_error($err->getScriptMessage());
	}

	$html = Pkript_Interpreter::toStringValue($result);
	return Pkript_Sanitizer::sanitize($html, $interp->getFragments()) .
		plugin_pkript_logs($interp);
}

/**
 * console.log output, after the script's own HTML.
 *
 * Rendered the way an error is - one inline element per line, escaped by the
 * same helper - rather than as a block of its own, because a script called
 * inline sits inside a paragraph and must not break out of it.
 *
 * @param Pkript_Interpreter|NULL $interp NULL if the run never started
 */
function plugin_pkript_logs($interp) {
	if (!PKRIPT_DEBUG || $interp === NULL)
		return '';

	static $labels = array(
		'log' => array('pkript-log', 'Pkript Log'),
		'warn' => array('pkript-log pkript-log-warn', 'Pkript Warn'),
		'error' => array('pkript-error', 'Pkript Error'),
	);

	$out = '';
	foreach ($interp->getLogs() as $line) {
		list($class, $label) = $labels[$line['level']];
		$out .= plugin_pkript_notice($class, $label, $line['text']);
	}
	return $out;
}

/**
 * Pick up 'key=value' arguments for e.opts. They stay in e.args as well, so a
 * script can still read them positionally when that is more convenient.
 */
function plugin_pkript_parse_opts($args) {
	$opts = array();
	foreach ($args as $arg) {
		if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/s', $arg, $m))
			continue;
		$opts[$m[1]] = $m[2];
	}
	return $opts;
}

/**
 * Send the visitor to $page and stop, for wiki.redirect().
 *
 * Defined only if nothing else has: a harness that must not exit the process
 * declares its own before loading the plugin, and this is then left alone.
 * The return value is the body for the one case where redirecting is no
 * longer possible, because output has already started.
 */
if (!function_exists('plugin_pkript_send_redirect')) {
	function plugin_pkript_send_redirect($page) {
		$uri = function_exists('get_page_uri')
			? get_page_uri($page)
			: (function_exists('get_base_uri') ? get_base_uri() : './') .
				'?' . rawurlencode($page);

		if (headers_sent()) {
			// Too late for a header; say where they were going instead
			return '<p class="pkript-redirect"><a href="' . htmlsc($uri) .
				'">' . htmlsc($page) . '</a></p>';
		}
		header('Location: ' . $uri);
		exit;
	}
}

function plugin_pkript_error($message) {
	return plugin_pkript_notice('pkript-error', 'Pkript Error', $message);
}

/** The one shape everything the runtime says for itself is written in. */
function plugin_pkript_notice($class, $label, $message) {
	return '<span class="' . $class . '">' . $label . ': ' .
		htmlsc($message) . '</span>';
}

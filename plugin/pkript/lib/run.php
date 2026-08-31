<?php
// $Id: run.php,v 0.2 2026/08/31 11:06:32 WikiChree.COM Team Exp $

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
function plugin_pkript_dispatch($args, $type)
{
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
function plugin_pkript_normalize_newlines($text)
{
	return str_replace(array("\r\n", "\r"), "\n", $text);
}

/**
 * Request variables, as e.vars.
 *
 * $vars is not always the script's own form: previewing an edit POSTs 'pass',
 * the password just typed, and a script in that page runs during the request.
 * Array values (from 'name[]') are skipped; e.vars holds strings.
 */
function plugin_pkript_request_vars()
{
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
 * Load, parse and run a script, then sanitize its output.
 *
 * @param array  $args  [0] is the script name, the rest are passed to the script
 * @param string $type  'convert' | 'inline' | 'action'
 * @param string $body  multiline body, exposed as e.body
 * @return string HTML
 */
function plugin_pkript_run($args, $type, $body = '')
{
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
		'method' => (isset($_SERVER['REQUEST_METHOD']) &&
			strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') ? 'POST' : 'GET',
	));

	try {
		$reason = '';
		$trust = PKRIPT_TRUST_FILE;
		$constants = array();
		$program = plugin_pkript_compile($name, $trust, $reason, $constants);
		if ($program === FALSE)
			return plugin_pkript_error($reason);

		$interp = new Pkript_Interpreter($program, $name, $trust, $constants);
		$result = $interp->callEntryPoint('plugin_' . $name . '_' . $type, $context);
	} catch (Pkript_Error $err) {
		return plugin_pkript_error($err->getScriptMessage());
	}

	$html = Pkript_Interpreter::toStringValue($result);
	return Pkript_Sanitizer::sanitize($html, $interp->getFragments());
}

/**
 * Pick up 'key=value' arguments for e.opts. They stay in e.args as well, so a
 * script can still read them positionally when that is more convenient.
 */
function plugin_pkript_parse_opts($args)
{
	$opts = array();
	foreach ($args as $arg) {
		if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/s', $arg, $m))
			continue;
		$opts[$m[1]] = $m[2];
	}
	return $opts;
}

function plugin_pkript_error($message)
{
	return '<span class="pkript-error">Pkript Error: ' .
		htmlsc($message) . '</span>';
}

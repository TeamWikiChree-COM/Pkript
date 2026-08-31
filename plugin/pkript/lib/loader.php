<?php
// $Id: loader.php,v 0.2 2026/08/31 11:06:32 WikiChree.COM Team Exp $

/**
 * Pkript - script loading and import
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/** Extensions accepted for script files, normalized and de-duplicated. */
function plugin_pkript_extensions()
{
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

/**
 * Locate the script source. Returns FALSE and fills $reason on failure.
 */
function plugin_pkript_load($name, &$reason, &$trust = NULL)
{
	// 1. script/<name>.<ext> - '.pks' and '.js' are equivalent
	foreach (plugin_pkript_extensions() as $ext) {
		$file = DATA_HOME . PKRIPT_SCRIPT_DIR . $name . '.' . $ext;
		if (is_file($file) && is_readable($file)) {
			$trust = PKRIPT_TRUST_FILE;
			return file_get_contents($file);
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
			$trust = $frozen ? PKRIPT_TRUST_FROZEN : PKRIPT_TRUST_PAGE;
			return plugin_pkript_strip_metadata(get_source($page, TRUE, TRUE));
		}
	}

	$reason = 'Script not found: ' . $name;
	return FALSE;
}

/**
 * Load a script and everything it imports as one function table.
 *
 * @param int    $trust  out: the lowest trust level of everything loaded
 * @param string $reason out: why nothing was loaded
 * @return array|FALSE function declarations, keyed by name
 */
function plugin_pkript_compile($name, &$trust, &$reason, &$constants = NULL)
{
	$source = plugin_pkript_load($name, $reason, $trust);
	if ($source === FALSE)
		return FALSE;

	$cached = plugin_pkript_cache_read($name, $source, $trust, $constants);
	if ($cached !== FALSE)
		return $cached;

	$state = array(
		'loaded' => array($name => TRUE),
		'units' => array($name => array(md5($source), $trust)),
		'count' => 0,
		'trust' => $trust,
		'constants' => array(),
		'constNames' => array()
	);
	$functions = array();
	plugin_pkript_compile_unit($name, $source, $trust, $functions, $state, 0);
	$trust = $state['trust'];
	$constants = $state['constants'];
	plugin_pkript_cache_write($name, $state['units'], $functions, $constants);
	return $functions;
}

/** Parse one script, pull in its imports, then add its own functions. */
function plugin_pkript_compile_unit($name, $source, $trust, &$functions, &$state, $depth)
{
	$lexer = new Pkript_Lexer($source, $name);
	$parser = new Pkript_Parser($lexer->tokenize(), $name);
	$own = $parser->parse();

	foreach ($parser->getImports() as $import) {
		plugin_pkript_import($import, $name, $trust, $functions, $state, $depth);
	}

	foreach ($own as $fnName => $fn) {
		plugin_pkript_claim($fnName, '関数', $name, $fn, $functions, $state);
		$fn['script'] = $name;
		$functions[$fnName] = $fn;
	}

	// After the functions, so a constant may call any of them. Between
	// constants the order is the order they were written in.
	foreach ($parser->getConstants() as $const) {
		plugin_pkript_claim($const['name'], 'const', $name, $const, $functions, $state);
		$const['script'] = $name;
		$state['constNames'][$const['name']] = $name;
		$state['constants'][] = $const;
	}
}

/** Functions and constants share one namespace; a clash is an error. */
function plugin_pkript_claim($what, $kind, $script, $node, $functions, $state)
{
	$owner = NULL;
	if (isset($functions[$what]))
		$owner = $functions[$what]['script'];
	if (isset($state['constNames'][$what]))
		$owner = $state['constNames'][$what];
	if ($owner === NULL)
		return;

	throw new Pkript_Error(
		$kind . ' ' . $what . ' は ' . $owner . ' で定義済みです',
		$script,
		$node['line'],
		$node['col']
	);
}

/** Follow one `import`, unless it has been followed already. */
function plugin_pkript_import($import, $from, $fromTrust, &$functions, &$state, $depth)
{
	$name = $import['name'];
	$fail = function ($message) use ($from, $import) {
		throw new Pkript_Error($message, $from, $import['line'], $import['col']);
	};

	if (!preg_match('/^[A-Za-z0-9_-]+$/', $name))
		$fail('import できない名前です: ' . $name);
	if (isset($state['loaded'][$name]))
		return;   // like require_once

	if ($depth + 1 > PKRIPT_MAX_IMPORT_DEPTH) {
		$fail('import の入れ子が深すぎます (上限 ' . PKRIPT_MAX_IMPORT_DEPTH . ')');
	}
	if (++$state['count'] > PKRIPT_MAX_IMPORTS) {
		$fail('import が多すぎます (上限 ' . PKRIPT_MAX_IMPORTS . ')');
	}

	$reason = '';
	$source = plugin_pkript_load($name, $reason, $trust);
	if ($source === FALSE)
		$fail($reason);

	if ($trust < $fromTrust && !PKRIPT_IMPORT_LOWER_TRUST) {
		$fail('信頼度の低いスクリプトは import できません: ' . $name);
	}

	$state['loaded'][$name] = TRUE;
	$state['units'][$name] = array(md5($source), $trust);
	// The whole run drops to the lowest level involved
	$state['trust'] = min($state['trust'], $trust);
	plugin_pkript_compile_unit(
		$name,
		$source,
		min($fromTrust, $trust),
		$functions,
		$state,
		$depth + 1
	);
}

/**
 * Remove PukiWiki page metadata that get_source() leaves in place.
 * Blank the lines instead of deleting them so error line numbers still match
 * what the user sees in the page editor.
 */
function plugin_pkript_strip_metadata($source)
{
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

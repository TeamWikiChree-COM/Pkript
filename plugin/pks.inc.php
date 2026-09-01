<?php
// $Id: pks.inc.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * @link https://wikichree.com/guide/?PkriptRunner
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Pkript Runner - Pkriptをその場で実行するプラグイン
 *
 *   #pks(arg1, arg2){{
 *   code
 *   }}
 *   &pks(arg1, arg2){code};
 */

require_once(__DIR__ . '/pkript.inc.php');

function plugin_pks_convert() {
	$args = func_get_args();

	if (count($args) > 0 && strpos(end($args), "\r") !== FALSE) {
		$source = plugin_pkript_normalize_newlines(array_pop($args));
	} else {
		$source = implode(',', $args);
		$args = array();
	}
	return plugin_pks_dispatch($source, $args, 'convert');
}

function plugin_pks_inline() {
	$args = func_get_args();
	$text = count($args) > 0 ? array_pop($args) : NULL;
	$text = $text === NULL ? '' : plugin_pkript_normalize_newlines($text);

	if (trim($text) === '')
		return plugin_pks_dispatch(implode(',', $args), array(), 'inline');

	return plugin_pks_dispatch($text, $args, 'inline');
}

function plugin_pks_dispatch($source, $args, $type) {
	global $vars;

	$page = isset($vars['page']) ? $vars['page'] : '';
	$frozen = $page !== '' && function_exists('is_freeze') && is_freeze($page);

	if (PKRIPT_PAGE_SCRIPT_FROZEN_ONLY && !$frozen)
		return plugin_pkript_error('Page is not frozen: ' . $page);

	return plugin_pks_run($source, $args, $type,
		$frozen ? PKRIPT_TRUST_FROZEN : PKRIPT_TRUST_PAGE);
}

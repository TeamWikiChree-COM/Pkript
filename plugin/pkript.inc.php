<?php
// $Id: pkript.inc.php,v 0.3 2026/08/30 00:00:00 Pitan Exp $

/**
* @link https://blog.pitan76.net/?Pkript
* @author Pitan
* @license https://opensource.org/license/mit MIT
*/

/**
 * Pkript - JavaScript風の構文を持つ、PukiWiki用のサンドボックス型スクリプト言語
 *
 *   #pkript(script name, arg1, arg2)
 *   &pkript(script name, arg1);
 */


/////////////////////////////////////////////////
// Runtime
//
// The interpreter lives in plugin/pkript/. This file keeps only what
// PukiWiki itself calls: the entry points and the script loader.

define('PKRIPT_RUNTIME', 1);

foreach (array(
	'error',
	'budget',
	'values',
	'lexer',
	'parser',
	'scope',
	'stdlib',
	'interpreter',
	'sanitizer'
) as $pkript_module) {
	require_once dirname(__FILE__) . '/pkript/' . $pkript_module . '.php';
}
unset($pkript_module);

/////////////////////////////////////////////////
// Configuration
//
// Every value can be overridden from pukiwiki.ini.php: define it there and the
// guards below leave it alone.

// Directory that holds script files (relative to DATA_HOME). Under plugin/
// because the web server already denies it: script source is not fetchable.
if (! defined('PKRIPT_SCRIPT_DIR'))
	define('PKRIPT_SCRIPT_DIR', 'plugin/pkript/script/');

// Extensions tried for script files, in order. '.js' is there for editor
// syntax highlighting.
if (! defined('PKRIPT_SCRIPT_EXT'))
	define('PKRIPT_SCRIPT_EXT', 'pks,js');

// Allow scripts stored as wiki pages under ':config/pkript/script/'.
// Anyone who can edit those pages can run code: protect them with
// $edit_auth_pages (or freeze them) on a wiki with open editing.
if (! defined('PKRIPT_ALLOW_PAGE_SCRIPT'))
	define('PKRIPT_ALLOW_PAGE_SCRIPT', 1);

// When page scripts are allowed, run frozen pages only
if (! defined('PKRIPT_PAGE_SCRIPT_FROZEN_ONLY'))
	define('PKRIPT_PAGE_SCRIPT_FROZEN_ONLY', 0);

// Page name prefix for page scripts, mirroring the on-disk layout.
if (! defined('PKRIPT_PAGE_PREFIX'))
	define('PKRIPT_PAGE_PREFIX', ':config/pkript/script/');

// Trust levels, highest first. Where a script came from decides what it may
// do; `import` never lets a script gain the level of what it imported.
define('PKRIPT_TRUST_FILE',   2);   // plugin/pkript/script/ - only an admin writes here
define('PKRIPT_TRUST_FROZEN', 1);   // a frozen :config/ page
define('PKRIPT_TRUST_PAGE',   0);   // a page anyone with edit rights can change

// Scripts one run may import, and how deep the chain may go. Each import is
// another parse, and that is what an import costs.
if (! defined('PKRIPT_MAX_IMPORTS'))
	define('PKRIPT_MAX_IMPORTS', 16);
if (! defined('PKRIPT_MAX_IMPORT_DEPTH'))
	define('PKRIPT_MAX_IMPORT_DEPTH', 4);

// Allow importing a script less trusted than the importer. Off by default:
// otherwise the author of an editable page could inject code into a frozen
// one. Turning it on does not raise the importer - the whole run drops to the
// lowest level involved.
if (! defined('PKRIPT_IMPORT_LOWER_TRUST'))
	define('PKRIPT_IMPORT_LOWER_TRUST', 0);

// Minimum trust level a script needs to call wiki.write() / wiki.append().
// The default is file scripts only: a page script is written by whoever can
// edit that page, and page writing is the one capability that lets a script
// change what the wiki says when nobody is looking.
if (! defined('PKRIPT_WRITE_MIN_TRUST'))
	define('PKRIPT_WRITE_MIN_TRUST', PKRIPT_TRUST_FILE);

// Page reads per request: wiki.source() / wiki.exists() / wiki.pages() /
// wiki.link() all touch the filesystem, and a loop can call them as often as
// it likes for a handful of steps each.
if (! defined('PKRIPT_MAX_READS'))
	define('PKRIPT_MAX_READS', 5000);

// Page writes per request, and the largest page a script may leave behind
if (! defined('PKRIPT_MAX_WRITES'))
	define('PKRIPT_MAX_WRITES', 4);
if (! defined('PKRIPT_MAX_PAGE_BYTES'))
	define('PKRIPT_MAX_PAGE_BYTES', 524288);

// Where the CSRF token secret is kept. Without a readable secret no token can
// be issued or checked, and every write fails - that is the intended failure
// direction.
if (! defined('PKRIPT_SECRET_FILE'))
	define('PKRIPT_SECRET_FILE',
		(defined('CACHE_DIR') ? CACHE_DIR : DATA_HOME . 'cache/') . 'pkript_secret.dat');

// Maximum function call nesting depth (guards against infinite recursion)
if (! defined('PKRIPT_MAX_DEPTH'))
	define('PKRIPT_MAX_DEPTH', 64);

// Maximum number of evaluation steps for one script run: the deterministic
// bound, with PKRIPT_MAX_TIME as the wall clock backstop. Keep the two
// calibrated - at roughly 900,000 steps/sec here, 1,000,000 is about 1.1s, so
// a host 3x slower hits the time limit first. Set this too low and it
// silently becomes the real limit instead.
if (! defined('PKRIPT_MAX_STEPS'))
	define('PKRIPT_MAX_STEPS', 1000000);

// Wall clock limit for one script run, in seconds
if (! defined('PKRIPT_MAX_TIME'))
	define('PKRIPT_MAX_TIME', 3);

// Maximum iterations for a single loop. PKRIPT_MAX_STEPS is the real bound;
// this only exists to name the problem when a loop obviously runs away.
if (! defined('PKRIPT_MAX_LOOP'))
	define('PKRIPT_MAX_LOOP', 100000);

// Maximum length of a string value, in bytes
if (! defined('PKRIPT_MAX_STRING'))
	define('PKRIPT_MAX_STRING', 1048576);

// Maximum pages wiki.pages() may return. Each hit costs a read permission
// check, so this is a lower ceiling than PKRIPT_MAX_ARRAY: over it the call
// fails rather than truncating, so a listing is never silently short.
if (! defined('PKRIPT_MAX_PAGES'))
	define('PKRIPT_MAX_PAGES', 1000);

// Maximum number of elements in an array
if (! defined('PKRIPT_MAX_ARRAY'))
	define('PKRIPT_MAX_ARRAY', 10000);

// Maximum wiki.convert() calls per run. Rendering Wiki markup runs the whole
// PukiWiki plugin pipeline, and that work is not charged to the step counter.
if (! defined('PKRIPT_MAX_CONVERT'))
	define('PKRIPT_MAX_CONVERT', 32);

// Show detailed messages (line/column) on error
if (! defined('PKRIPT_DEBUG'))
	define('PKRIPT_DEBUG', 1);

/////////////////////////////////////////////////
// Plugin entry points

function plugin_pkript_init() {
	// Nothing to set up: scripts are loaded lazily on each call.
}

function plugin_pkript_convert() {
	return plugin_pkript_dispatch(func_get_args(), 'convert');
}

function plugin_pkript_inline() {
	return plugin_pkript_dispatch(func_get_args(), 'inline');
}

/**
 * Split PukiWiki's argument list into script arguments and the body, then run.
 *
 * Shared by the #pkript / &pkript entry points above and by the wrappers
 * plugin_pkript_bind() defines for a script called under its own name, so the
 * two spellings of a call behave identically.
 *
 * @param array  $args $args[0] is the script name, as PukiWiki passes it
 * @param string $type 'convert' | 'inline'
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
			if ($text !== NULL) $body = plugin_pkript_normalize_newlines($text);
		}
	}

	return plugin_pkript_run($args, $type, $body);
}

/**
 * PukiWiki delimits multiline plugin bodies with "\r"; scripts expect "\n".
 */
function plugin_pkript_normalize_newlines($text) {
	return str_replace(array("\r\n", "\r"), "\n", $text);
}

function plugin_pkript_action() {
	global $vars;
	// 'script', not 'name': a form built by a script wants 'name' for its own
	// field (a comment form asks for the commenter's name, for one).
	$name = isset($vars['script']) ? $vars['script'] : '';
	$args = array($name);
	return array(
		'msg'  => 'Pkript',
		'body' => plugin_pkript_run($args, 'action', ''),
	);
}

/**
 * Request variables, as e.vars.
 *
 * $vars is not always the script's own form: previewing an edit POSTs 'pass',
 * the password just typed, and a script in the previewed page runs during that
 * request. So PukiWiki's own auth and edit keys are dropped here.
 * Array values (from 'name[]' fields) are skipped; e.vars holds strings.
 */
function plugin_pkript_request_vars() {
	global $vars;

	static $denied = array(
		'pass' => 1, 'password' => 1, 'passwd' => 1,
		'encode_hint' => 1, 'original' => 1, 'digest' => 1,
	);

	$out = array();
	if (! is_array($vars)) return $out;
	foreach ($vars as $key => $value) {
		if (isset($denied[strtolower($key)])) continue;
		if (! is_scalar($value)) continue;
		$out[$key] = (string)$value;
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
function plugin_pkript_run($args, $type, $body = '') {
	global $vars;

	$name = isset($args[0]) ? trim($args[0]) : '';
	$args = array_slice($args, 1);

	if ($name === '') return plugin_pkript_error('Usage: #pkript(name, arg...)');
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
		$reason  = '';
		$trust   = PKRIPT_TRUST_FILE;
		$program = plugin_pkript_compile($name, $trust, $reason);
		if ($program === FALSE) return plugin_pkript_error($reason);

		$interp = new Pkript_Interpreter($program, $name, $trust);
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
function plugin_pkript_parse_opts($args) {
	$opts = array();
	foreach ($args as $arg) {
		if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/s', $arg, $m)) continue;
		$opts[$m[1]] = $m[2];
	}
	return $opts;
}

/** Extensions accepted for script files, normalized and de-duplicated. */
function plugin_pkript_extensions() {
	static $exts = NULL;
	if ($exts !== NULL) return $exts;

	$exts = array();
	foreach (explode(',', PKRIPT_SCRIPT_EXT) as $ext) {
		$ext = strtolower(ltrim(trim($ext), '.'));
		// Alphanumeric only: an extension is pasted straight into a path
		if ($ext === '' || ! preg_match('/^[a-z0-9]+$/', $ext)) continue;
		if (! in_array($ext, $exts, TRUE)) $exts[] = $ext;
	}
	if (empty($exts)) $exts = array('pks');
	return $exts;
}

/**
 * Locate the script source. Returns FALSE and fills $reason on failure.
 */
function plugin_pkript_load($name, &$reason, &$trust = NULL) {
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
			if (PKRIPT_PAGE_SCRIPT_FROZEN_ONLY && ! $frozen) {
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
 * Load a script and everything it imports, and return one function table.
 *
 * @param string $name   script to start from
 * @param int    $trust  out: the lowest trust level of everything loaded
 * @param string $reason out: why nothing was loaded
 * @return array|FALSE function declarations, keyed by name
 */
function plugin_pkript_compile($name, &$trust, &$reason) {
	$source = plugin_pkript_load($name, $reason, $trust);
	if ($source === FALSE) return FALSE;

	$state = array('loaded' => array($name => TRUE), 'count' => 0, 'trust' => $trust);
	$functions = array();
	plugin_pkript_compile_unit($name, $source, $trust, $functions, $state, 0);
	$trust = $state['trust'];
	return $functions;
}

/** Parse one script, pull in its imports, then add its own functions. */
function plugin_pkript_compile_unit($name, $source, $trust, &$functions, &$state, $depth) {
	$lexer  = new Pkript_Lexer($source, $name);
	$parser = new Pkript_Parser($lexer->tokenize(), $name);
	$own    = $parser->parse();

	foreach ($parser->getImports() as $import) {
		plugin_pkript_import($import, $name, $trust, $functions, $state, $depth);
	}

	foreach ($own as $fnName => $fn) {
		if (isset($functions[$fnName])) {
			throw new Pkript_Error(
				'関数 ' . $fnName . ' は ' . $functions[$fnName]['script'] . ' で定義済みです',
				$name, $fn['line'], $fn['col']);
		}
		// Remembered so an error inside this function names the right script
		$fn['script'] = $name;
		$functions[$fnName] = $fn;
	}
}

/** Follow one `import`, unless it has been followed already. */
function plugin_pkript_import($import, $from, $fromTrust, &$functions, &$state, $depth) {
	$name = $import['name'];
	$fail = function ($message) use ($from, $import) {
		throw new Pkript_Error($message, $from, $import['line'], $import['col']);
	};

	if (! preg_match('/^[A-Za-z0-9_-]+$/', $name)) $fail('import できない名前です: ' . $name);
	if (isset($state['loaded'][$name])) return;   // like require_once

	if ($depth + 1 > PKRIPT_MAX_IMPORT_DEPTH) {
		$fail('import の入れ子が深すぎます (上限 ' . PKRIPT_MAX_IMPORT_DEPTH . ')');
	}
	if (++$state['count'] > PKRIPT_MAX_IMPORTS) {
		$fail('import が多すぎます (上限 ' . PKRIPT_MAX_IMPORTS . ')');
	}

	$reason = '';
	$source = plugin_pkript_load($name, $reason, $trust);
	if ($source === FALSE) $fail($reason);

	if ($trust < $fromTrust && ! PKRIPT_IMPORT_LOWER_TRUST) {
		$fail('信頼度の低いスクリプトは import できません: ' . $name);
	}

	$state['loaded'][$name] = TRUE;
	// The whole run drops to the lowest level involved
	$state['trust'] = min($state['trust'], $trust);
	plugin_pkript_compile_unit($name, $source, min($fromTrust, $trust),
		$functions, $state, $depth + 1);
}

/**
 * Remove PukiWiki page metadata that get_source() leaves in place.
 * Blank the lines instead of deleting them so error line numbers still match
 * what the user sees in the page editor.
 */
function plugin_pkript_strip_metadata($source) {
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

/////////////////////////////////////////////////
// Page writing

/**
 * Per-site secret behind the CSRF token, created on first use.
 * @return string|FALSE FALSE when it cannot be read or created
 */
function plugin_pkript_secret() {
	static $secret = NULL;
	if ($secret !== NULL) return $secret;

	$file = PKRIPT_SECRET_FILE;
	if (is_file($file) && is_readable($file)) {
		$raw = file_get_contents($file);
		if (strlen($raw) >= 32) return $secret = $raw;
	}

	$dir = dirname($file);
	if (! is_dir($dir) || ! is_writable($dir)) return $secret = FALSE;

	$raw = function_exists('random_bytes') ? random_bytes(32) : '';
	if ($raw === '') return $secret = FALSE;
	// LOCK_EX so two requests racing on first use do not write half a secret
	if (file_put_contents($file, $raw, LOCK_EX) === FALSE) return $secret = FALSE;
	@chmod($file, 0600);
	return $secret = $raw;
}

/**
 * Token a script embeds in its own form, checked when that form posts back.
 *
 * PukiWiki 1.5.4 has no CSRF machinery of its own, so this is Pkript's. It is
 * tied to whoever the request is authenticated as, which is what makes it
 * unguessable by another site. **On a wiki with no authentication there is no
 * identity to tie it to**: the token is then only site-wide, and an attacker
 * who can read the wiki can read a token too. Write access on an open wiki has
 * to be limited by $edit_auth_pages, not by this.
 *
 * @return string the token, or '' when no secret is available
 */
function plugin_pkript_token() {
	$secret = plugin_pkript_secret();
	if ($secret === FALSE) return '';
	return hash_hmac('sha256', 'pkript:write:' . plugin_pkript_identity(), $secret);
}

/** Who the request is authenticated as, '' when nobody. */
function plugin_pkript_identity() {
	if (isset($_SESSION['authenticated_user'])) return (string)$_SESSION['authenticated_user'];
	if (isset($_SERVER['PHP_AUTH_USER']))       return (string)$_SERVER['PHP_AUTH_USER'];
	return '';
}

/** Does this request carry the token for its own identity? */
function plugin_pkript_check_token() {
	global $vars;
	$want = plugin_pkript_token();
	if ($want === '') return FALSE;
	$got = isset($vars['pkript_token']) ? (string)$vars['pkript_token'] : '';
	return $got !== '' && hash_equals($want, $got);
}

function plugin_pkript_error($message) {
	return '<span class="pkript-error">Pkript Error: ' .
		htmlsc($message) . '</span>';
}

/**
 * Bind dynamic wrapper functions for a Pkript script named $name.
 * Called by lib/plugin.php when #name or &name; is invoked directly.
 *
 * @param string $name plugin name
 * @return bool TRUE if script exists and functions were bound
 */
function plugin_pkript_bind($name) {
	if (! preg_match('/^[A-Za-z0-9_-]+$/', $name)) return FALSE;
	$reason = '';
	$source = plugin_pkript_load($name, $reason);
	if ($source === FALSE) return FALSE;

	// The wrappers only prepend the script name and hand over to the shared
	// entry points, so no call handling lives in this generated code.
	if (! function_exists('plugin_' . $name . '_convert')) {
		$quoted = var_export($name, TRUE);
		eval('
			function plugin_' . $name . '_init() { return TRUE; }
			function plugin_' . $name . '_convert() {
				return plugin_pkript_dispatch(
					array_merge(array(' . $quoted . '), func_get_args()), "convert");
			}
			function plugin_' . $name . '_inline() {
				return plugin_pkript_dispatch(
					array_merge(array(' . $quoted . '), func_get_args()), "inline");
			}
			function plugin_' . $name . '_action() {
				return plugin_pkript_run(array(' . $quoted . '), "action", "");
			}
		');
	}
	return TRUE;
}


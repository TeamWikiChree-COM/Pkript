<?php
// $Id: loader.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - script loading and import
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * A script and everything it imports, as one function table.
 *
 * What a name means is the environment's - see adapter/script_source.php -
 * and so is where a parse is kept between requests. Everything after that is
 * here, and is the same wherever Pkript runs: which imports are allowed, how
 * deep and how many, what happens to trust along the way, and whether a
 * cached parse still describes what is out there now.
 *
 * The last of those is why the cache adapter is allowed to be careless: an
 * entry is checked against every script that went into it, content and trust
 * alike, on the way out. A stale entry costs a re-parse, never a wrong run.
 */
class Pkript_Loader {
	/** @var Pkript_ScriptSource */
	private $source;

	/** @var Pkript_AstCache */
	private $cache;

	public function __construct($source, $cache) {
		$this->source = $source;
		$this->cache = $cache;
	}

	/////////////////////////////////////////////
	// Loading

	/**
	 * @param int    $trust  out: the lowest trust level of everything loaded
	 * @param string $reason out: why nothing was loaded
	 * @return array|FALSE function declarations, keyed by name
	 */
	public function compile($name, &$trust, &$reason, &$constants = NULL) {
		$source = $this->load($name, $reason, $trust);
		if ($source === FALSE)
			return FALSE;

		$cached = $this->cacheRead($name, $source, $trust, $constants);
		if ($cached !== FALSE)
			return $cached;

		$state = self::newState($name, $source, $trust);
		$functions = array();
		$this->compileUnit($name, $source, $trust, $functions, $state, 0);
		$trust = $state['trust'];
		$constants = $state['constants'];
		$this->cacheWrite($name, $state['units'], $functions, $constants);
		return $functions;
	}

	/**
	 * A script handed over as text rather than looked up by name - what
	 * `&pks;` and its like are, where the source is the call.
	 *
	 * Imports work as they always do and still go through the source, so a
	 * script written into a page can use the ones an administrator installed.
	 * Nothing is cached: an entry is keyed by a name, and this has none that
	 * anybody would ask for again.
	 *
	 * @param array $state out: the run's trust, its constants, what it loaded
	 * @return array function declarations, keyed by name
	 */
	public function compileSource($name, $source, $trust, &$state) {
		$state = self::newState($name, $source, $trust);
		$functions = array();
		$this->compileUnit($name, $source, $trust, $functions, $state, 0);
		return $functions;
	}

	/** What one compilation accumulates, before anything is parsed. */
	private static function newState($name, $source, $trust) {
		return array(
			'loaded' => array($name => TRUE),
			'units' => array($name => array(md5($source), $trust)),
			'count' => 0,
			'trust' => $trust,
			'constants' => array(),
			'constNames' => array()
		);
	}

	/**
	 * One script's text. Returns FALSE and fills $reason on failure.
	 *
	 * @param int $trust out
	 * @return string|FALSE
	 */
	private function load($name, &$reason, &$trust) {
		// A name is pasted into wherever the source looks, so nothing that
		// could name somewhere else is a name. Checked on every lookup rather
		// than at each way in, so a source may use the name as given and an
		// embedder cannot forget to: the entry point is as much a name from
		// outside as an import is.
		if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
			$reason = 'Not a script name: ' . $name;
			return FALSE;
		}

		$found = $this->source->find($name, $reason);
		if ($found === FALSE) {
			if ($reason === '' || $reason === NULL)
				$reason = 'Script not found: ' . $name;
			return FALSE;
		}
		$trust = $found['trust'];
		return $found['source'];
	}

	/** Parse one script, pull in its imports, then add its own functions. */
	private function compileUnit($name, $source, $trust, &$functions, &$state, $depth) {
		$lexer = new Pkript_Lexer($source, $name);
		$parser = new Pkript_Parser($lexer->tokenize(), $name);
		$own = $parser->parse();

		foreach ($parser->getImports() as $import) {
			$this->import($import, $name, $trust, $functions, $state, $depth);
		}

		foreach ($own as $fnName => $fn) {
			self::claim($fnName, 'Function', $name, $fn, $functions, $state);
			$fn['script'] = $name;
			$functions[$fnName] = $fn;
		}

		// After the functions, so a constant may call any of them. Between
		// constants the order is the order they were written in.
		foreach ($parser->getConstants() as $const) {
			self::claim($const['name'], 'const', $name, $const, $functions, $state);
			$const['script'] = $name;
			$state['constNames'][$const['name']] = $name;
			$state['constants'][] = $const;
		}
	}

	/** Functions and constants share one namespace; a clash is an error. */
	private static function claim($what, $kind, $script, $node, $functions, $state) {
		$owner = NULL;
		if (isset($functions[$what]))
			$owner = $functions[$what]['script'];
		if (isset($state['constNames'][$what]))
			$owner = $state['constNames'][$what];
		if ($owner === NULL)
			return;

		throw new Pkript_Error(
			$kind . ' ' . $what . ' is already defined in ' . $owner . '',
			$script,
			$node['line'],
			$node['col']
		);
	}

	/** Follow one `import`, unless it has been followed already. */
	private function import($import, $from, $fromTrust, &$functions, &$state, $depth) {
		$name = $import['name'];
		$fail = function ($message) use ($from, $import) {
			throw new Pkript_Error($message, $from, $import['line'], $import['col']);
		};

		// A name is pasted into wherever the source looks, so nothing that
		// could name somewhere else is a name.
		if (!preg_match('/^[A-Za-z0-9_-]+$/', $name))
			$fail('Not an importable name: ' . $name);
		if (isset($state['loaded'][$name]))
			return;   // like require_once

		if ($depth + 1 > PKRIPT_MAX_IMPORT_DEPTH) {
			$fail('import nesting too deep (limit ' . PKRIPT_MAX_IMPORT_DEPTH . ')');
		}
		if (++$state['count'] > PKRIPT_MAX_IMPORTS) {
			$fail('Too many imports (limit ' . PKRIPT_MAX_IMPORTS . ')');
		}

		$reason = '';
		$trust = NULL;
		$source = $this->load($name, $reason, $trust);
		if ($source === FALSE)
			$fail($reason);

		if ($trust < $fromTrust && !PKRIPT_IMPORT_LOWER_TRUST) {
			$fail('Cannot import a less trusted script: ' . $name);
		}

		$state['loaded'][$name] = TRUE;
		$state['units'][$name] = array(md5($source), $trust);
		// The whole run drops to the lowest level involved
		$state['trust'] = min($state['trust'], $trust);
		$this->compileUnit(
			$name,
			$source,
			min($fromTrust, $trust),
			$functions,
			$state,
			$depth + 1
		);
	}

	/////////////////////////////////////////////
	// The cached parse, and whether it still counts

	/**
	 * Identifies what a cached AST was built by. Anything that changes how a
	 * script compiles has to be in here, or a stale entry would survive a
	 * config change - the source's own settings included, which is what its
	 * signature() is for.
	 */
	public function cacheKey() {
		return md5(serialize(array(
			PKRIPT_AST_VERSION,
			PKRIPT_IMPORT_LOWER_TRUST,
			PKRIPT_MAX_IMPORTS,
			PKRIPT_MAX_IMPORT_DEPTH,
			PKRIPT_JSX,
			PKRIPT_REGEX,
			$this->source->signature(),
		)));
	}

	/**
	 * The parsed script, if what the source holds still matches what was
	 * cached.
	 *
	 * Every script that went into it is checked, imports included: both its
	 * content and how far it was trusted. Freezing a page changes no source
	 * but does change what a script may do, so trust is compared as well.
	 *
	 * @param string $rootSource source of $name, already loaded by the caller
	 * @param int    $trust      in: the root's trust. out: the run's trust
	 * @return array|FALSE the function table
	 */
	private function cacheRead($name, $rootSource, &$trust, &$constants) {
		$entry = $this->cache->load($name);
		if (!self::cacheShape($entry))
			return FALSE;
		if ($entry['key'] !== $this->cacheKey())
			return FALSE;

		$lowest = $trust;
		foreach ($entry['units'] as $unit => $was) {
			if ($unit === $name) {
				$source = $rootSource;
				$now = $trust;
			} else {
				$reason = '';
				$now = NULL;
				$source = $this->load($unit, $reason, $now);
				if ($source === FALSE)
					return FALSE;
			}
			if (!is_array($was) || count($was) !== 2)
				return FALSE;
			if ($was[0] !== md5($source) || $was[1] !== $now)
				return FALSE;

			$lowest = min($lowest, $now);
		}

		$trust = $lowest;
		$constants = isset($entry['constants']) ? $entry['constants'] : array();
		return $entry['functions'];
	}

	/**
	 * Is this an AST entry, and not whatever else was where one should be?
	 *
	 * Asked of anything a cache hands back, however it stores things: an entry
	 * kept where a person could edit it is not evidence about its own shape.
	 */
	private static function cacheShape($entry) {
		if (!is_array($entry))
			return FALSE;
		if (!isset($entry['key'], $entry['units'], $entry['functions']))
			return FALSE;
		if (!is_string($entry['key']) || !is_array($entry['units']) || !is_array($entry['functions']))
			return FALSE;
		// A real entry always names at least the script it was built from
		if (empty($entry['units']))
			return FALSE;
		if (isset($entry['constants']) && !is_array($entry['constants']))
			return FALSE;

		foreach ($entry['functions'] as $fn) {
			if (!is_array($fn) || !isset($fn['params'], $fn['body']))
				return FALSE;
		}
		return TRUE;
	}

	private function cacheWrite($name, $units, $functions, $constants) {
		$this->cache->save($name, array(
			'key' => $this->cacheKey(),
			'units' => $units,
			'functions' => $functions,
			'constants' => $constants,
		));
	}
}

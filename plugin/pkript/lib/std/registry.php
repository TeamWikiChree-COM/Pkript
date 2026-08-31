<?php
// $Id: registry.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - standard library registry
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * The whole standard library: nothing outside the three tables below is
 * reachable from a script.
 *
 * Every lookup - what globals exist, whether `"a".foo` is a method, where a
 * call goes - is answered from here, so the tables are the one place to read
 * to know a script's reach, and the one place to edit to widen it.
 */
class Pkript_Stdlib {
	/** API namespace -> module class. */
	private static $namespaces = array(
		'html' => 'Pkript_Std_Html',
		'url' => 'Pkript_Std_Url',
		'JSON' => 'Pkript_Std_Json',
		'date' => 'Pkript_Std_Date',
		'Math' => 'Pkript_Std_Math',
		'Object' => 'Pkript_Std_Object',
		'wiki' => 'Pkript_Std_Wiki',
		'data' => 'Pkript_Std_Data',
		'console' => 'Pkript_Std_Console',
	);

	/** Value type label -> methods class. See typeOf(). */
	private static $types = array(
		'String' => 'Pkript_Std_StringMethods',
		'Array' => 'Pkript_Std_ArrayMethods',
		'Number' => 'Pkript_Std_NumberMethods',
		'RegExp' => 'Pkript_Std_RegexMethods',
	);

	/**
	 * Bare names, each resolved to a namespaced member before dispatch.
	 *
	 * The PukiWiki spellings are here so code moved over from a PHP plugin
	 * works as written; the rest are the language's own conversions and
	 * argument helpers, which have no namespace to live in.
	 */
	private static $globals = array(
		'htmlsc' => 'html.escape',
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
		'format_date' => 'date.format',

		'func_get_args' => 'lang.func_get_args',
		'func_num_args' => 'lang.func_num_args',
		'func_get_arg' => 'lang.func_get_arg',
		'String' => 'lang.String',
		'Number' => 'lang.Number',
		'Boolean' => 'lang.Boolean',
	);

	/** Backs the global names above; has no namespace of its own. */
	private static $langModule = 'Pkript_Std_Lang';

	private $rt;
	private $modules = array();

	public function __construct($rt) {
		$this->rt = $rt;
	}

	/////////////////////////////////////////////
	// What a script can see

	/** @return array namespace name -> member names, for the global scope. */
	public function namespaces() {
		$out = array();
		foreach (self::$namespaces as $name => $class)
			$out[$name] = $class::members();
		return $out;
	}

	/** @return array bare global function names. */
	public function globalFunctions() {
		return array_keys(self::$globals);
	}

	/** The name a script sees for a value's type, or NULL if it has no methods. */
	public static function typeOf($value) {
		if (is_string($value))
			return 'String';
		if ($value instanceof Pkript_Regex)
			return 'RegExp';
		if ($value instanceof Pkript_Arr)
			return 'Array';
		if (is_int($value) || is_float($value))
			return 'Number';
		return NULL;
	}

	public function isMethod($value, $name) {
		$label = self::typeOf($value);
		if ($label === NULL)
			return FALSE;
		$class = self::$types[$label];
		return in_array($name, $class::methods(), TRUE);
	}

	/////////////////////////////////////////////
	// Dispatch

	public function callBuiltin($name, $args, $node) {
		$canonical = isset(self::$globals[$name]) ? self::$globals[$name] : $name;
		$dot = strpos($canonical, '.');

		if ($dot !== FALSE) {
			$ns = substr($canonical, 0, $dot);
			$class = $this->classFor($ns);
			// members() is the gate: reject anything it does not list, so a
			// module's call() is never reached with a name it did not
			// publish. A $globals entry pointing at a namespace that does not
			// exist fails here too, rather than fatally later.
			$member = substr($canonical, $dot + 1);
			if ($class !== NULL && in_array($member, $class::members(), TRUE))
				return $this->module($ns)->call($member, $args, $node);
		}
		$this->rt->fail('未定義の組み込み関数 ' . $name, $node);
	}

	public function callMethod($recv, $name, $args, $node) {
		$label = self::typeOf($recv);
		if ($label === NULL) {
			$this->rt->fail(Pkript_Interpreter::typeName($recv) .
				' にメソッド ' . $name . ' はありません', $node);
		}
		$class = self::$types[$label];
		if (!in_array($name, $class::methods(), TRUE)) {
			$this->rt->fail($label . ' にメソッド ' . $name . ' はありません', $node);
		}
		return $this->module($label)->call($recv, $name, $args, $node);
	}

	/** @return string|NULL the class registered under $key */
	private function classFor($key) {
		if (isset(self::$namespaces[$key]))
			return self::$namespaces[$key];
		if (isset(self::$types[$key]))
			return self::$types[$key];
		return $key === 'lang' ? self::$langModule : NULL;
	}

	/**
	 * Built on first use, then kept for the run - so a script that never
	 * touches wiki.* never constructs the wiki module. Only reached once a
	 * call has passed the members() / methods() gate, so $key is always one
	 * classFor() knows.
	 */
	private function module($key) {
		if (!isset($this->modules[$key])) {
			$class = $this->classFor($key);
			$this->modules[$key] = new $class($this->rt);
		}
		return $this->modules[$key];
	}
}

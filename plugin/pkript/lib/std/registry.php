<?php
// $Id: registry.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - standard library registry
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * The whole standard library: nothing the loaded packages name is out of a
 * script's reach, and nothing else is in it.
 *
 * Every lookup - what globals exist, whether `"a".foo` is a method, where a
 * call goes - is answered from here, so this is the one place to read to know
 * how a lookup is resolved. What there is to look up is the packages' to say;
 * see std/package.php.
 */
class Pkript_Stdlib {
	/** API namespace -> module class, merged from the packages. */
	private $namespaces = array();

	/** Value type label -> methods class. See typeOf(). */
	private $types = array();

	/** Bare name -> the namespaced member it stands for. */
	private $globals = array();

	/** Bare name -> value. */
	private $constants = array();

	/** Backs the bare conversion names; has no namespace of its own. */
	private static $langModule = 'Pkript_Std_Lang';

	private $rt;
	private $modules = array();

	/**
	 * Later packages win, so an environment can replace a namespace rather
	 * than only add one. Nothing is constructed here - a package names
	 * classes, and module() builds one the first time a script reaches it.
	 */
	public function __construct($rt, $packages) {
		$this->rt = $rt;
		foreach ($packages as $package) {
			$this->namespaces = array_merge($this->namespaces, $package->namespaces());
			$this->types      = array_merge($this->types,      $package->types());
			$this->globals    = array_merge($this->globals,    $package->globals());
			$this->constants  = array_merge($this->constants,  $package->constants());
		}
	}

	/////////////////////////////////////////////
	// What a script can see

	/** @return array namespace name -> member names, for the global scope. */
	public function namespaces() {
		$out = array();
		foreach ($this->namespaces as $name => $class)
			$out[$name] = $class::members();
		return $out;
	}

	/** @return array namespace name -> (member name -> value), e.g. Math.PI. */
	public function namespaceConstants() {
		$out = array();
		foreach ($this->namespaces as $name => $class)
			$out[$name] = $class::constants();
		return $out;
	}

	/** @return array bare global function names. */
	public function globalFunctions() {
		return array_keys($this->globals);
	}

	/** @return array bare global name -> value, e.g. NaN. */
	public function globalConstants() {
		return $this->constants;
	}

	/** @return array bare global name -> the namespaced member it stands for. */
	public function globalTargets() {
		return $this->globals;
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
		$class = $this->types[$label];
		return in_array($name, $class::methods(), TRUE);
	}

	/////////////////////////////////////////////
	// Dispatch

	public function callBuiltin($name, $args, $node) {
		$canonical = isset($this->globals[$name]) ? $this->globals[$name] : $name;
		$dot = strpos($canonical, '.');

		if ($dot !== FALSE) {
			$ns = substr($canonical, 0, $dot);
			$class = $this->namespaceClass($ns);
			// members() is the gate: reject anything it does not list, so a
			// module's call() is never reached with a name it did not
			// publish. A $globals entry pointing at a namespace that does not
			// exist fails here too, rather than fatally later.
			$member = substr($canonical, $dot + 1);
			if ($class !== NULL && in_array($member, $class::members(), TRUE))
				return $this->module($class)->call($member, $args, $node);
		}
		$this->rt->fail('Undefined builtin ' . $name, $node);
	}

	public function callMethod($recv, $name, $args, $node) {
		$label = self::typeOf($recv);
		if ($label === NULL) {
			$this->rt->fail(Pkript_Interpreter::typeName($recv) . '.' .
				$name . ' is not a function', $node);
		}
		$class = $this->types[$label];
		if (!in_array($name, $class::methods(), TRUE)) {
			$this->rt->fail($label . '.' . $name . ' is not a function', $node);
		}
		return $this->module($class)->call($recv, $name, $args, $node);
	}

	/**
	 * The class publishing a namespace.
	 *
	 * Namespaces and types are kept apart on purpose, even though a few names
	 * are in both: `Array` is a namespace holding Array.isArray() and the
	 * label under which an array value finds its methods, and the two are not
	 * the same module. Which table a lookup reads is decided by the caller,
	 * which knows whether it is resolving `Array.x` or `someArray.x`.
	 */
	private function namespaceClass($key) {
		if (isset($this->namespaces[$key]))
			return $this->namespaces[$key];
		return $key === 'lang' ? self::$langModule : NULL;
	}

	/**
	 * Built on first use, then kept for the run - so a script that never
	 * touches wiki.* never constructs the wiki module. Keyed by class name,
	 * because two entries may name the same class and one class may sit in
	 * both tables under one name.
	 */
	private function module($class) {
		if (!isset($this->modules[$class]))
			$this->modules[$class] = new $class($this->rt);
		return $this->modules[$class];
	}
}

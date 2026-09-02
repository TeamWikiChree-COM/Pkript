<?php
// $Id: module.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - standard library base classes
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Common ground for everything a script can reach.
 *
 * Modules never touch the interpreter's internals; they go through the
 * public runtime API on Pkript_Interpreter ($this->rt) - fail(), the
 * check*() limits, toNumber(), callValue(). Anything a module needs that is
 * not there yet belongs there, not in a new back door.
 */
abstract class Pkript_Std_Base {
	/** @var Pkript_Interpreter */
	protected $rt;

	public function __construct($rt) {
		$this->rt = $rt;
	}

	protected function arg($args, $i, $default = '') {
		return array_key_exists($i, $args) ? $args[$i] : $default;
	}

	/**
	 * JSX output is a string like any other to a script, but the marks it
	 * carries are an internal detail of assembling HTML and have no business
	 * inside an API argument.
	 */
	protected function strArg($args, $i, $default = '') {
		return array_key_exists($i, $args)
			? Pkript_Interpreter::stripHtmlMarks(
				Pkript_Interpreter::toStringValue($args[$i]))
			: $default;
	}

	/**
	 * Charge one page lookup. Every API that touches the wiki's pages goes
	 * through here, so a loop that walks pages runs out of budget rather than
	 * out of the request's time.
	 */
	protected function spendRead($node) {
		if (!$this->rt->budget()->spendRead()) {
			$this->rt->failLimit('Too many page reads (limit ' .
				PKRIPT_MAX_READS . ')', $node);
		}
	}

	protected function numArg($args, $i, $node, $default = 0) {
		return array_key_exists($i, $args)
			? $this->rt->toNumber($args[$i], $node) : $default;
	}

	/**
	 * Shared by String.slice / substring and Array.slice, clamped into 0..$len.
	 *
	 * @param bool $fromEnd a negative index counts back from the end
	 *                      (slice does, substring does not)
	 */
	protected function sliceRange($len, $args, $node, $fromEnd) {
		$start = (int) $this->numArg($args, 0, $node, 0);
		if ($fromEnd && $start < 0)
			$start = max(0, $len + $start);

		$end = array_key_exists(1, $args)
			? (int) $this->rt->toNumber($args[1], $node) : $len;
		if ($fromEnd && $end < 0)
			$end = $len + $end;

		$start = max(0, min($start, $len));
		$end = max($start, min($end, $len));
		return array($start, $end);
	}
}

/**
 * One API namespace, e.g. `Math` or `wiki`.
 *
 * To add a namespace: subclass this, then name the class in
 * Pkript_Stdlib::$namespaces. members() is the whole contract - a name that
 * is not listed there is not reachable from a script, whatever call() would
 * do with it.
 */
abstract class Pkript_Std_Module extends Pkript_Std_Base {
	/**
	 * What this namespace publishes. Static because it is a fact about the
	 * class, not about a run: the registry reads it to build the global scope
	 * without having to construct every module first.
	 *
	 * @return array member names, e.g. array('floor', 'ceil')
	 */
	abstract public static function members();

	/**
	 * Values the namespace publishes alongside its functions, e.g. Math.PI.
	 * A module with none - most of them - inherits this empty list.
	 *
	 * @return array member name -> value
	 */
	public static function constants() {
		return array();
	}

	/** @param string $name one of members() */
	abstract public function call($name, $args, $node);
}

/**
 * The methods of one value type, e.g. everything `"abc".` can reach.
 *
 * To add a type: subclass this, then name the class in Pkript_Stdlib::$types
 * against the label Pkript_Stdlib::typeOf() gives its values.
 */
abstract class Pkript_Std_Methods extends Pkript_Std_Base {
	/** Static for the same reason as Pkript_Std_Module::members().
	 *  @return array method names */
	abstract public static function methods();

	abstract public function call($recv, $name, $args, $node);
}

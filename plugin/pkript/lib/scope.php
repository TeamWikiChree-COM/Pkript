<?php
// $Id: scope.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - scopes and signals
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// Interpreter

/** Internal signal used to unwind a `return`. */
class Pkript_Return extends Exception {
	public $value;
	public function __construct($value) {
		parent::__construct('return');
		$this->value = $value;
	}
}

/**
 * Internal signal for wiki.redirect(): unwind the whole run, then send the
 * visitor somewhere else.
 *
 * A signal rather than a header sent on the spot, because deciding what the
 * response is belongs to the entry points, not to a sandbox module - and
 * because a script's try/catch must not be able to swallow it. It is not a
 * Pkript_Error, so nothing in the interpreter catches it on the way out.
 */
class Pkript_Redirect extends Exception {
	public $page;
	public function __construct($page) {
		parent::__construct('redirect');
		$this->page = $page;
	}
}

/**
 * Internal signal for `break`. An empty label means the plain `break`, which
 * the nearest loop or switch takes; a named one travels outward until the
 * statement carrying that label catches it.
 */
class Pkript_Break extends Exception {
	public $label;
	public function __construct($label = '') {
		parent::__construct('break');
		$this->label = $label;
	}
}

/** Internal signal for `continue`. Labelled the same way. */
class Pkript_Continue extends Exception {
	public $label;
	public function __construct($label = '') {
		parent::__construct('continue');
		$this->label = $label;
	}
}

/** Lexical scope. */
class Pkript_Scope {
	private $vars = array();
	private $consts = array();
	private $parent;

	public function __construct($parent = NULL) {
		$this->parent = $parent;
	}

	public function declare_($name, $value, $isConst) {
		if (array_key_exists($name, $this->vars))
			return FALSE;
		$this->vars[$name] = $value;
		if ($isConst)
			$this->consts[$name] = TRUE;
		return TRUE;
	}

	public function has($name) {
		if (array_key_exists($name, $this->vars))
			return TRUE;
		return $this->parent !== NULL && $this->parent->has($name);
	}

	public function get($name) {
		if (array_key_exists($name, $this->vars))
			return $this->vars[$name];
		if ($this->parent !== NULL)
			return $this->parent->get($name);
		return NULL;
	}

	/** @return TRUE on success, 'const' if the target is a constant, FALSE if undeclared */
	public function set($name, $value) {
		if (array_key_exists($name, $this->vars)) {
			if (isset($this->consts[$name]))
				return 'const';
			$this->vars[$name] = $value;
			return TRUE;
		}
		if ($this->parent !== NULL)
			return $this->parent->set($name, $value);
		return FALSE;
	}
}

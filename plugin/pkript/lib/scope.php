<?php
// $Id: scope.php,v 0.2 2026/08/31 11:06:32 WikiChree.COM Team Exp $

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
class Pkript_Return extends Exception
{
	public $value;
	public function __construct($value)
	{
		parent::__construct('return');
		$this->value = $value;
	}
}

/** Internal signal for `break`. */
class Pkript_Break extends Exception
{
}

/** Internal signal for `continue`. */
class Pkript_Continue extends Exception
{
}

/** Lexical scope. */
class Pkript_Scope
{
	private $vars = array();
	private $consts = array();
	private $parent;

	public function __construct($parent = NULL)
	{
		$this->parent = $parent;
	}

	public function declare_($name, $value, $isConst)
	{
		if (array_key_exists($name, $this->vars))
			return FALSE;
		$this->vars[$name] = $value;
		if ($isConst)
			$this->consts[$name] = TRUE;
		return TRUE;
	}

	public function has($name)
	{
		if (array_key_exists($name, $this->vars))
			return TRUE;
		return $this->parent !== NULL && $this->parent->has($name);
	}

	public function get($name)
	{
		if (array_key_exists($name, $this->vars))
			return $this->vars[$name];
		if ($this->parent !== NULL)
			return $this->parent->get($name);
		return NULL;
	}

	/** @return TRUE on success, 'const' if the target is a constant, FALSE if undeclared */
	public function set($name, $value)
	{
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

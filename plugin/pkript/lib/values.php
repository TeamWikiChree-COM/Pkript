<?php
// $Id: values.php,v 0.2 2026/08/31 11:06:32 WikiChree.COM Team Exp $

/**
 * Pkript runtime - value types
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// Values
//
// Arrays and objects are PHP objects so that they behave like JavaScript
// references: hand one to a function, mutate it, and the caller sees it.
// Strings / numbers / booleans / null map straight onto PHP scalars.

/** An array value. */
class Pkript_Arr
{
	public $items;
	public function __construct($items = array())
	{
		$this->items = array_values($items);
	}
}

/** An object value (`{ a: 1 }`, plus `e` and the API namespaces). */
class Pkript_Obj
{
	public $props;

	public function __construct($props = array())
	{
		// Accept plain PHP arrays from the runtime side and wrap them
		$this->props = array();
		foreach ($props as $k => $v)
			$this->props[$k] = Pkript_Interpreter::wrap($v);
	}
}

/** A user-defined function. $scope is the closure of an arrow function. */
class Pkript_Func
{
	public $decl;
	public $scope;
	public function __construct($decl, $scope = NULL)
	{
		$this->decl = $decl;
		$this->scope = $scope;
	}
}

/** A runtime-provided function, e.g. `html.escape`. */
class Pkript_Builtin
{
	public $name;
	public function __construct($name)
	{
		$this->name = $name;
	}
}

/** A runtime-provided method bound to its receiver, e.g. `"a".toUpperCase`. */
class Pkript_Method
{
	public $receiver;
	public $name;
	public function __construct($receiver, $name)
	{
		$this->receiver = $receiver;
		$this->name = $name;
	}
}

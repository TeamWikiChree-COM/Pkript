<?php
// $Id: interpreter.php,v 0.2 2026/08/31 11:06:32 WikiChree.COM Team Exp $

/**
 * Pkript runtime - interpreter core
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Interpreter
{
	// The standard library lives in stdlib.php
	use Pkript_Stdlib;

	private $functions;
	private $script;
	private $depth = 0;
	private $budget;
	private $globals;
	private $argsStack = array();

	// Script each running function came from, so an error inside an imported
	// one is reported against the file it was written in
	private $scriptStack = array();

	// Lowest trust level among the script and everything it imported
	private $trust;

	// 'convert' | 'inline' | 'action'
	private $entryType = '';

	// Top level const declarations, evaluated once before the entry point
	private $constants = array();


	// Trusted HTML produced by wiki.convert(), keyed by the token that stands
	// in for it in the script's output. See Pkript_Sanitizer.
	private $fragments = array();


	public function __construct($functions, $script, $trust = NULL, $constants = array())
	{
		$this->functions = $functions;
		$this->script = $script;
		$this->trust = $trust === NULL ? PKRIPT_TRUST_FILE : $trust;
		$this->budget = Pkript_Budget::current();
		$this->constants = $constants;
		$this->globals = new Pkript_Scope();

		foreach (self::apiNamespaces() as $name => $members) {
			$ns = new Pkript_Obj();
			foreach ($members as $member) {
				$ns->props[$member] = new Pkript_Builtin($name . '.' . $member);
			}
			$this->globals->declare_($name, $ns, TRUE);
		}
		foreach (self::globalFunctions() as $fn) {
			$this->globals->declare_($fn, new Pkript_Builtin($fn), TRUE);
		}
	}


	public function callEntryPoint($name, $context)
	{
		if (!isset($this->functions[$name])) {
			throw new Pkript_Error(
				'エントリポイント ' . $name . ' が見つかりません',
				$this->script
			);
		}
		// wiki.write() is only allowed from an action
		if ($context instanceof Pkript_Obj && isset($context->props['type'])) {
			$this->entryType = self::toStringValue($context->props['type']);
		}

		$this->budget->enterRun();
		try {
			$this->declareConstants();
			return $this->invoke($this->functions[$name], array($context));
		} finally {
			$this->budget->leaveRun();
		}
	}

	/**
	 * Run the top level declarations into the global scope. Their cost is
	 * charged to the budget like any other code, because it is.
	 */
	private function declareConstants()
	{
		foreach ($this->constants as $const) {
			$this->scriptStack[] = isset($const['script'])
				? $const['script'] : $this->script;
			try {
				$value = $const['init'] === NULL
					? NULL : $this->eval_($const['init'], $this->globals);
				$this->globals->declare_($const['name'], $value,
					$const['kind'] === 'const');
			} finally {
				array_pop($this->scriptStack);
			}
		}
		$this->constants = array();
	}

	/** Trusted HTML that the sanitizer must paste back into the output. */
	public function getFragments()
	{
		return $this->fragments;
	}

	/////////////////////////////////////////////
	// Resource limits

	/**
	 * Charged once per statement and per expression node. Keeps a script that
	 * uses no API at all from burning the request.
	 */
	private function tick($node = NULL)
	{
		$steps = $this->budget->step();
		if ($this->budget->overSteps()) {
			$this->failLimit(
				'実行ステップ数が上限を超えました (上限 ' . PKRIPT_MAX_STEPS . ')',
				$node === NULL ? array() : $node
			);
		}
		// microtime() is not free, so only sample it now and then
		if (($steps & 0x3FF) === 0) {
			if ($this->budget->overTime()) {
				$this->failLimit(
					'実行時間が上限を超えました (上限 ' . PKRIPT_MAX_TIME . '秒)',
					$node === NULL ? array() : $node
				);
			}
			$this->checkMemory($node === NULL ? array() : $node);
		}
	}

	/** Also called where big values are built: one step can allocate 1MB. */
	private function checkMemory($node)
	{
		if ($this->budget->overMemory()) {
			$this->failLimit('メモリ使用量が上限を超えました (上限 ' .
				(int) (PKRIPT_MAX_MEMORY / 1048576) . 'MB)', $node);
		}
	}

	private function checkString($s, $node)
	{
		if (strlen($s) > PKRIPT_MAX_STRING)
			$this->failStringTooLong($node);
		$this->checkMemory($node);
		return $s;
	}

	private function failStringTooLong($node)
	{
		$this->failLimit('文字列が長すぎます (上限 ' . PKRIPT_MAX_STRING . 'バイト)', $node);
	}

	private function checkArray($items, $node)
	{
		if (count($items) > PKRIPT_MAX_ARRAY) {
			$this->failLimit('配列の要素数が上限を超えました (上限 ' . PKRIPT_MAX_ARRAY . ')', $node);
		}
		$this->checkMemory($node);
		return $items;
	}

	/////////////////////////////////////////////
	// Execution

	private function invoke($decl, $args, $closure = NULL)
	{
		if ($this->depth >= PKRIPT_MAX_DEPTH) {
			throw new Pkript_LimitError(
				'関数呼び出しが深すぎます (上限 ' . PKRIPT_MAX_DEPTH . ')',
				$this->currentScript(),
				$decl['line'],
				$decl['col']
			);
		}
		$this->depth++;
		$scope = new Pkript_Scope($closure === NULL ? $this->globals : $closure);
		foreach ($decl['params'] as $i => $param) {
			$scope->declare_($param, isset($args[$i]) ? $args[$i] : NULL, FALSE);
		}

		if (
			$this->depth === 1 && count($args) === 1 && $args[0] instanceof Pkript_Obj &&
			isset($args[0]->props['args'])
		) {
			$rawArgs = $args[0]->props['args'];
			$callArgs = ($rawArgs instanceof Pkript_Arr) ? $rawArgs->items : (is_array($rawArgs) ? $rawArgs : array());
		} else {
			$callArgs = $args;
		}
		$this->argsStack[] = $callArgs;
		$this->scriptStack[] = isset($decl['script']) ? $decl['script'] : $this->script;

		try {
			$this->execBlock($decl['body'], $scope);
			return '';
		} catch (Pkript_Return $ret) {
			return $ret->value;
		} finally {
			array_pop($this->argsStack);
			array_pop($this->scriptStack);
			$this->depth--;
		}
	}

	private function execBlock($block, $parentScope)
	{
		$scope = new Pkript_Scope($parentScope);
		foreach ($block['body'] as $stmt) {
			$this->execStatement($stmt, $scope);
		}
	}

	private function execStatement($stmt, $scope)
	{
		$this->tick($stmt);

		switch ($stmt['type']) {
			case 'Block':
				$this->execBlock($stmt, $scope);
				return;

			case 'VarDecl':
				$value = $stmt['init'] === NULL ? NULL : $this->eval_($stmt['init'], $scope);
				if (!$scope->declare_($stmt['name'], $value, $stmt['kind'] === 'const')) {
					$this->fail('変数 ' . $stmt['name'] . ' は既に宣言されています', $stmt);
				}
				return;

			case 'Return':
				$value = $stmt['argument'] === NULL ? '' : $this->eval_($stmt['argument'], $scope);
				throw new Pkript_Return($value);

			case 'ExprStmt':
				$this->eval_($stmt['expression'], $scope);
				return;

			case 'If':
				if (self::toBool($this->eval_($stmt['test'], $scope))) {
					$this->execStatement($stmt['then'], new Pkript_Scope($scope));
				} elseif ($stmt['else'] !== NULL) {
					$this->execStatement($stmt['else'], new Pkript_Scope($scope));
				}
				return;

			case 'While':
				$this->execWhile($stmt, $scope);
				return;

			case 'For':
				$this->execFor($stmt, $scope);
				return;

			case 'ForIn':
				$this->execForIn($stmt, $scope);
				return;

			case 'ForOf':
				$this->execForOf($stmt, $scope);
				return;

			case 'Try':
				$this->execTry($stmt, $scope);
				return;

			case 'Switch':
				$this->execSwitch($stmt, $scope);
				return;

			case 'Break':
				throw new Pkript_Break();

			case 'Continue':
				throw new Pkript_Continue();

			case 'Empty':
				return;
		}
		$this->fail('未対応の文です: ' . $stmt['type'], $stmt);
	}

	/** Run one loop body, absorbing break / continue. @return FALSE to stop the loop */
	private function runLoopBody($body, $scope)
	{
		try {
			$this->execStatement($body, new Pkript_Scope($scope));
		} catch (Pkript_Break $b) {
			return FALSE;
		} catch (Pkript_Continue $c) {
			// fall through to the next iteration
		}
		return TRUE;
	}

	private function guardIterations($count, $stmt)
	{
		if ($count > PKRIPT_MAX_LOOP) {
			$this->failLimit('ループの繰り返しが上限を超えました (上限 ' . PKRIPT_MAX_LOOP . ')', $stmt);
		}
	}

	/**
	 * Only a script error is caught. A Pkript_LimitError goes straight past,
	 * or a script could wrap a runaway loop in try/catch and keep going.
	 * return / break / continue travel as their own signals and are unaffected.
	 */
	private function execTry($stmt, $scope)
	{
		try {
			$this->execStatement($stmt['block'], new Pkript_Scope($scope));
			return;
		} catch (Pkript_LimitError $limit) {
			throw $limit;
		} catch (Pkript_Error $err) {
			$caught = $err;
		}

		$handler = new Pkript_Scope($scope);
		if ($stmt['name'] !== NULL) {
			$handler->declare_($stmt['name'],
				new Pkript_Obj(array('message' => $caught->getScriptMessage())), TRUE);
		}
		$this->execStatement($stmt['handler'], $handler);
	}

	/**
	 * Runs from the first matching label to the end, so `break` is what stops
	 * one case running into the next - as in JavaScript. `continue` is left
	 * alone: it belongs to whatever loop the switch sits in.
	 */
	private function execSwitch($stmt, $scope)
	{
		$subject = $this->eval_($stmt['subject'], $scope);
		$inner = new Pkript_Scope($scope);

		$matched = FALSE;
		$default = NULL;
		try {
			foreach ($stmt['cases'] as $i => $case) {
				if (!$matched) {
					if ($case['test'] === NULL) {
						$default = $i;
						continue;
					}
					if (!self::strictEquals($subject, $this->eval_($case['test'], $inner))) {
						continue;
					}
					$matched = TRUE;
				}
				foreach ($case['body'] as $inCase) {
					$this->execStatement($inCase, $inner);
				}
			}

			// Nothing matched, so start again at `default` and run to the end
			if (!$matched && $default !== NULL) {
				$cases = array_slice($stmt['cases'], $default);
				foreach ($cases as $case) {
					foreach ($case['body'] as $inCase) {
						$this->execStatement($inCase, $inner);
					}
				}
			}
		} catch (Pkript_Break $b) {
			// break ends the switch
		}
	}

	private function execWhile($stmt, $scope)
	{
		$n = 0;
		while (self::toBool($this->eval_($stmt['test'], $scope))) {
			$this->guardIterations(++$n, $stmt);
			if (!$this->runLoopBody($stmt['body'], $scope))
				break;
		}
	}

	private function execFor($stmt, $scope)
	{
		// The init declaration lives in its own scope, shared by every iteration
		$outer = new Pkript_Scope($scope);
		if ($stmt['init'] !== NULL)
			$this->execStatement($stmt['init'], $outer);

		$n = 0;
		while (TRUE) {
			if ($stmt['test'] !== NULL && !self::toBool($this->eval_($stmt['test'], $outer)))
				break;
			$this->guardIterations(++$n, $stmt);
			if (!$this->runLoopBody($stmt['body'], $outer))
				break;
			if ($stmt['update'] !== NULL)
				$this->eval_($stmt['update'], $outer);
		}
	}

	/**
	 * `for (const k in obj)` walks the keys, as JavaScript does. On an array
	 * that means the indexes, as strings - for..of is what you want there.
	 */
	private function execForIn($stmt, $scope)
	{
		$subject = $this->eval_($stmt['subject'], $scope);

		if ($subject instanceof Pkript_Obj) {
			$items = array_keys($subject->props);
		} elseif ($subject instanceof Pkript_Arr) {
			$items = array();
			foreach (array_keys($subject->items) as $i) $items[] = (string)$i;
		} elseif (is_string($subject)) {
			$items = array();
			for ($i = 0, $n = mb_strlen($subject, SOURCE_ENCODING); $i < $n; $i++) {
				$items[] = (string)$i;
			}
		} else {
			$this->fail(self::typeName($subject) . ' は for..in で回せません', $stmt);
			return;
		}

		$this->runForEach($stmt, $scope, $items);
	}

	private function execForOf($stmt, $scope)
	{
		$subject = $this->eval_($stmt['subject'], $scope);

		if ($subject instanceof Pkript_Arr) {
			$items = $subject->items;
		} elseif (is_string($subject)) {
			$items = mb_str_split($subject, 1, SOURCE_ENCODING);
		} else {
			$this->fail(self::typeName($subject) . ' は for..of で回せません', $stmt);
			return;
		}

		$this->runForEach($stmt, $scope, $items);
	}

	/** The loop both for..of and for..in run once they know their items. */
	private function runForEach($stmt, $scope, $items)
	{
		$n = 0;
		foreach ($items as $item) {
			$this->guardIterations(++$n, $stmt);
			// Fresh binding per iteration, so `const` works like it does in JS
			$iter = new Pkript_Scope($scope);
			$iter->declare_($stmt['name'], $item, $stmt['kind'] === 'const');
			if (!$this->runLoopBody($stmt['body'], $iter))
				break;
		}
	}

	/////////////////////////////////////////////
	// Evaluation

	private function eval_($node, $scope)
	{
		$this->tick($node);

		switch ($node['type']) {
			case 'Literal':
				return $node['value'];

			case 'Identifier':
				if ($scope->has($node['name']))
					return $scope->get($node['name']);
				if (isset($this->functions[$node['name']])) {
					return new Pkript_Func($this->functions[$node['name']]);
				}
				$this->fail('未定義の変数 ' . $node['name'], $node);

			case 'Function':
				return new Pkript_Func($node, $scope);

			case 'Template':
				$out = '';
				foreach ($node['parts'] as $part) {
					$out .= $part['type'] === 'str'
						? $part['value']
						: self::toStringValue($this->eval_($part['expression'], $scope));
					$this->checkString($out, $node);
				}
				return $out;

			case 'Jsx':
				return $this->evalJsx($node, $scope);

			case 'ArrayLit':
				$out = array();
				foreach ($node['elements'] as $el)
					$out[] = $this->eval_($el, $scope);
				return new Pkript_Arr($this->checkArray($out, $node));

			case 'ObjectLit':
				$obj = new Pkript_Obj();
				foreach ($node['properties'] as $prop) {
					$obj->props[$prop['key']] = $this->eval_($prop['value'], $scope);
				}
				return $obj;

			case 'Assign':
				return $this->evalAssign($node, $scope);

			case 'Update':
				return $this->evalUpdate($node, $scope);

			case 'Conditional':
				return self::toBool($this->eval_($node['test'], $scope))
					? $this->eval_($node['then'], $scope)
					: $this->eval_($node['else'], $scope);

			case 'Unary':
				return $this->evalUnary($node, $scope);

			case 'Binary':
				return $this->evalBinary($node, $scope);

			case 'Member':
				return $this->evalMember($node, $scope);

			case 'Index':
				return $this->evalIndex($node, $scope);

			case 'Call':
				return $this->evalCall($node, $scope);
		}
		$this->fail('未対応の式です: ' . $node['type'], $node);
	}

	private function evalAssign($node, $scope)
	{
		$target = $node['target'];
		$value = $this->eval_($node['value'], $scope);

		if ($node['op'] !== '=') {
			$current = $this->eval_($target, $scope);
			$binary = array(
				'type' => 'Binary',
				'op' => substr($node['op'], 0, 1),
				'line' => $node['line'],
				'col' => $node['col']
			);
			$value = $this->applyBinary($binary, $current, $value);
		}

		$this->store($target, $value, $scope, $node);
		return $value;
	}

	private function evalUpdate($node, $scope)
	{
		$target = $node['argument'];
		if (
			$target['type'] !== 'Identifier' &&
			$target['type'] !== 'Member' && $target['type'] !== 'Index'
		) {
			$this->fail('++ / -- は変数にのみ使えます', $node);
		}

		$old = $this->toNumber($this->eval_($target, $scope), $node);
		$new = $node['op'] === '++' ? $old + 1 : $old - 1;
		$this->store($target, $new, $scope, $node);
		return $node['prefix'] ? $new : $old;
	}

	/** Write to an assignable expression: a variable, a property or an index. */
	private function store($target, $value, $scope, $node)
	{
		if ($target['type'] === 'Identifier') {
			$res = $scope->set($target['name'], $value);
			if ($res === 'const') {
				$this->fail('const 変数 ' . $target['name'] . ' には再代入できません', $node);
			}
			if ($res === FALSE) {
				$scope->declare_($target['name'], $value, FALSE);
			}
			return;
		}

		if ($target['type'] === 'Member') {
			$obj = $this->eval_($target['object'], $scope);
			if (!($obj instanceof Pkript_Obj)) {
				$this->fail(self::typeName($obj) . ' のプロパティには代入できません', $node);
			}
			$obj->props[$target['property']] = $value;
			return;
		}

		// Index
		$obj = $this->eval_($target['object'], $scope);
		$index = $this->eval_($target['index'], $scope);

		if ($obj instanceof Pkript_Arr) {
			$i = (int) $this->toNumber($index, $node);
			if ($i < 0)
				$this->fail('配列の添字が負の数です', $node);
			if ($i >= PKRIPT_MAX_ARRAY) {
				$this->failLimit('配列の要素数が上限を超えました (上限 ' . PKRIPT_MAX_ARRAY . ')', $node);
			}
			// Writing past the end fills the gap with null, as JS does
			for ($n = count($obj->items); $n < $i; $n++)
				$obj->items[$n] = NULL;
			$obj->items[$i] = $value;
			return;
		}
		if ($obj instanceof Pkript_Obj) {
			$obj->props[self::toStringValue($index)] = $value;
			return;
		}
		$this->fail(self::typeName($obj) . ' には添字で代入できません', $node);
	}

	private function evalUnary($node, $scope)
	{
		$value = $this->eval_($node['argument'], $scope);
		switch ($node['op']) {
			case '!':
				return !self::toBool($value);
			case '-':
				return -1 * $this->toNumber($value, $node);
			case '+':
				return $this->toNumber($value, $node);
		}
		$this->fail('未対応の単項演算子 ' . $node['op'], $node);
	}

	private function evalBinary($node, $scope)
	{
		// Short-circuit operators
		if ($node['op'] === '&&') {
			$left = $this->eval_($node['left'], $scope);
			return self::toBool($left) ? $this->eval_($node['right'], $scope) : $left;
		}
		if ($node['op'] === '||') {
			$left = $this->eval_($node['left'], $scope);
			return self::toBool($left) ? $left : $this->eval_($node['right'], $scope);
		}

		return $this->applyBinary(
			$node,
			$this->eval_($node['left'], $scope),
			$this->eval_($node['right'], $scope)
		);
	}

	/** The non short-circuiting operators, split out so `+=` can reuse them. */
	private function applyBinary($node, $l, $r)
	{
		switch ($node['op']) {
			case '+':
				// String on either side means concatenation
				if (is_string($l) || is_string($r)) {
					return $this->checkString(self::toStringValue($l) . self::toStringValue($r), $node);
				}
				return $this->toNumber($l, $node) + $this->toNumber($r, $node);

			case '-':
				return $this->toNumber($l, $node) - $this->toNumber($r, $node);
			case '*':
				return $this->toNumber($l, $node) * $this->toNumber($r, $node);

			case '/':
				$d = $this->toNumber($r, $node);
				if ($d == 0)
					$this->fail('0 で除算しました', $node);
				return $this->toNumber($l, $node) / $d;

			case '%':
				$d = $this->toNumber($r, $node);
				if ($d == 0)
					$this->fail('0 で剰余を取りました', $node);
				return fmod($this->toNumber($l, $node), $d);

			case '==':
				return self::looseEquals($l, $r);
			case '!=':
				return !self::looseEquals($l, $r);
			case '===':
				return self::strictEquals($l, $r);
			case '!==':
				return !self::strictEquals($l, $r);

			case '<':
				return $this->compare($l, $r, $node) < 0;
			case '>':
				return $this->compare($l, $r, $node) > 0;
			case '<=':
				return $this->compare($l, $r, $node) <= 0;
			case '>=':
				return $this->compare($l, $r, $node) >= 0;
		}
		$this->fail('未対応の演算子 ' . $node['op'], $node);
	}

	private function evalMember($node, $scope)
	{
		$obj = $this->eval_($node['object'], $scope);
		$prop = $node['property'];

		if ($obj instanceof Pkript_Obj) {
			if (array_key_exists($prop, $obj->props))
				return $obj->props[$prop];
			$this->fail('プロパティ ' . $prop . ' は存在しません', $node);
		}
		if ($obj instanceof Pkript_Arr) {
			if ($prop === 'length')
				return count($obj->items);
		}
		if (is_string($obj) && $prop === 'length')
			return mb_strlen(self::stripHtmlMarks($obj), SOURCE_ENCODING);

		if (self::isMethod($obj, $prop)) {
			// A string method works on the HTML a JSX value holds, not on the
			// marks around it
			return new Pkript_Method(self::stripHtmlMarks($obj), $prop);
		}

		$this->fail(self::typeName($obj) . ' にプロパティ ' . $prop . ' はありません', $node);
	}

	private function evalIndex($node, $scope)
	{
		$obj = $this->eval_($node['object'], $scope);
		$index = $this->eval_($node['index'], $scope);

		if ($obj instanceof Pkript_Arr) {
			$i = (int) $this->toNumber($index, $node);
			if ($i < 0 || $i >= count($obj->items))
				return '';   // out of range -> empty string
			return $obj->items[$i];
		}
		if ($obj instanceof Pkript_Obj) {
			$key = self::toStringValue($index);
			return array_key_exists($key, $obj->props) ? $obj->props[$key] : '';
		}
		if (is_string($obj)) {
			$i = (int) $this->toNumber($index, $node);
			if ($i < 0 || $i >= mb_strlen($obj, SOURCE_ENCODING))
				return '';
			return mb_substr($obj, $i, 1, SOURCE_ENCODING);
		}
		$this->fail(self::typeName($obj) . ' は添字アクセスできません', $node);
	}

	private function evalCall($node, $scope)
	{
		$callee = $this->eval_($node['callee'], $scope);
		$args = array();
		foreach ($node['arguments'] as $a)
			$args[] = $this->eval_($a, $scope);
		return $this->callValue($callee, $args, $node);
	}

	/** Also how map / filter / sort reach their callback. */
	private function callValue($callee, $args, $node)
	{
		if ($callee instanceof Pkript_Func) {
			return $this->invoke($callee->decl, $args, $callee->scope);
		}
		if ($callee instanceof Pkript_Builtin) {
			return $this->callBuiltin($callee->name, $args, $node);
		}
		if ($callee instanceof Pkript_Method) {
			return $this->callMethod($callee->receiver, $callee->name, $args, $node);
		}
		$this->fail(self::typeName($callee) . ' は関数ではありません', $node);
	}


	/** The script running now, which is not the root one after an import. */
	private function currentScript()
	{
		$script = end($this->scriptStack);
		return $script === FALSE ? $this->script : $script;
	}

	/** A limit was hit. Not catchable; see Pkript_LimitError. */
	private function failLimit($message, $node)
	{
		throw new Pkript_LimitError(
			$message,
			$this->currentScript(),
			isset($node['line']) ? $node['line'] : 0,
			isset($node['col']) ? $node['col'] : 0
		);
	}

	private function fail($message, $node)
	{
		throw new Pkript_Error(
			$message,
			$this->currentScript(),
			isset($node['line']) ? $node['line'] : 0,
			isset($node['col']) ? $node['col'] : 0
		);
	}

	/////////////////////////////////////////////
	// Value helpers

	/** Wrap a PHP value handed in by the runtime into a Pkript value. */
	public static function wrap($v)
	{
		if (is_array($v)) {
			$items = array();
			foreach ($v as $item)
				$items[] = self::wrap($item);
			return new Pkript_Arr($items);
		}
		// Text from the request carries no HTML marks: only JSX may say that
		// part of a string is markup, and this is where the outside gets in
		return self::stripHtmlMarks($v);
	}

	public static function typeName($v)
	{
		if (is_string($v))
			return 'String';
		if (is_bool($v))
			return 'Boolean';
		if (is_int($v) || is_float($v))
			return 'Number';
		if ($v instanceof Pkript_Arr)
			return 'Array';
		if ($v === NULL)
			return 'Null';
		if ($v instanceof Pkript_Obj)
			return 'Object';
		return 'Function';
	}

	/////////////////////////////////////////////
	// JSX
	//
	// An element evaluates to its HTML, as a plain string. Markup written in
	// the script is HTML; a value interpolated into it is text, and is
	// escaped. Telling the two apart later needs a mark on the string, since
	// a nested element may arrive through a variable, an array or a function
	// call - so JSX output is wrapped in two control characters. They are
	// stripped wherever the string leaves the JSX world: on the way into an
	// API call, and by the sanitizer on the way out.

	const HTML_OPEN = "\x01";
	const HTML_CLOSE = "\x02";

	/** Remove the marks that say "this string is already HTML". */
	public static function stripHtmlMarks($s)
	{
		if (!is_string($s))
			return $s;
		return str_replace(array(self::HTML_OPEN, self::HTML_CLOSE), '', $s);
	}

	private function evalJsx($node, $scope)
	{
		$inner = '';
		foreach ($node['children'] as $child) {
			if ($child['type'] === 'text') {
				$inner .= $child['value'];
			} elseif ($child['type'] === 'element') {
				$inner .= self::stripHtmlMarks(
					$this->eval_($child['element'], $scope));
			} else {
				$inner .= $this->jsxChild(
					$this->eval_($child['expression'], $scope));
			}
			$this->checkString($inner, $node);
		}

		$tag = $node['tag'];
		if ($tag === '')
			return self::HTML_OPEN . $inner . self::HTML_CLOSE;

		$open = '<' . $tag . $this->jsxAttributes($node, $scope);
		$html = ($node['void'] || Pkript_Lexer::isVoidTag($tag))
			? $open . ' />'
			: $open . '>' . $inner . '</' . $tag . '>';
		$this->checkString($html, $node);
		return self::HTML_OPEN . $html . self::HTML_CLOSE;
	}

	/** The opening tag's attributes, with a leading space before each. */
	private function jsxAttributes($node, $scope)
	{
		$out = '';
		foreach ($node['attributes'] as $attr) {
			$name = $attr['name'];

			// `<input disabled>` and `<input disabled={true}>` both set it;
			// null and false leave the attribute out, as JSX does
			if ($attr['value'] === NULL) {
				$out .= ' ' . $name . '="' . $name . '"';
				continue;
			}
			if ($attr['value']['type'] === 'str') {
				$out .= ' ' . $name . '="' . $attr['value']['value'] . '"';
				continue;
			}

			$value = $this->eval_($attr['value']['expression'], $scope);
			if ($value === NULL || $value === FALSE)
				continue;
			if ($value === TRUE) {
				$out .= ' ' . $name . '="' . $name . '"';
				continue;
			}
			$out .= ' ' . $name . '="' .
				htmlsc(self::stripHtmlMarks(self::toStringValue($value)),
					ENT_QUOTES) . '"';
		}
		return $out;
	}

	/**
	 * One interpolated child. null and the booleans render as nothing and an
	 * array renders its elements in order, both as in JSX; everything else
	 * becomes text.
	 */
	private function jsxChild($value)
	{
		if ($value === NULL || is_bool($value))
			return '';
		if ($value instanceof Pkript_Arr) {
			$out = '';
			foreach ($value->items as $item)
				$out .= $this->jsxChild($item);
			return $out;
		}
		return self::jsxText(self::toStringValue($value));
	}

	/**
	 * Text to embed: escaped, except for the runs that came from JSX itself
	 * and are HTML already. The marks never nest - an element strips them
	 * from whatever it embeds - so one pass over the string is enough.
	 */
	private static function jsxText($s)
	{
		if (strpos($s, self::HTML_OPEN) === FALSE)
			return htmlsc($s, ENT_QUOTES);

		$parts = explode(self::HTML_OPEN, $s);
		$out = htmlsc(array_shift($parts), ENT_QUOTES);
		foreach ($parts as $part) {
			$close = strpos($part, self::HTML_CLOSE);
			if ($close === FALSE) {
				$out .= htmlsc($part, ENT_QUOTES);
				continue;
			}
			$out .= substr($part, 0, $close) .
				htmlsc(substr($part, $close + 1), ENT_QUOTES);
		}
		return $out;
	}

	/** @param array $seen arrays already being written, to stop a cycle */
	public static function toStringValue($v, $seen = array())
	{
		if (is_string($v))
			return $v;
		if (is_bool($v))
			return $v ? 'true' : 'false';
		if ($v === NULL)
			return '';
		if (is_int($v))
			return (string) $v;
		if (is_float($v)) {
			if ($v == (int) $v && abs($v) < 1e15)
				return (string) (int) $v;
			return rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
		}
		if ($v instanceof Pkript_Arr) {
			// As in JS: `const a = []; a.push(a)` makes String(a) empty
			$id = spl_object_id($v);
			if (isset($seen[$id]))
				return '';
			$seen[$id] = TRUE;

			$parts = array();
			$length = 0;
			foreach ($v->items as $item) {
				$part = self::toStringValue($item, $seen);
				// `a = [a, a]` doubles the output for one step, outside tick()
				$length += strlen($part) + 1;
				if ($length > PKRIPT_MAX_STRING) {
					throw new Pkript_Error('文字列が長すぎます (上限 ' .
						PKRIPT_MAX_STRING . 'バイト)');
				}
				$parts[] = $part;
			}
			return implode(',', $parts);
		}
		if ($v instanceof Pkript_Obj)
			return '[object Object]';
		return '';
	}

	public static function toBool($v)
	{
		if (is_bool($v))
			return $v;
		if ($v === NULL)
			return FALSE;
		if (is_string($v))
			return $v !== '';
		if (is_int($v) || is_float($v))
			return $v != 0;
		return TRUE;
	}

	/**
	 * An instance method rather than a static one, so a conversion failure is
	 * reported through fail() like every other runtime error, with the script
	 * name and column attached.
	 *
	 * It used to take an $interp argument that nothing ever read, and threw
	 * with an empty script name - which printed as '(:2行目)' while every other
	 * error said '(hello:2行目 12列)'.
	 */
	private function toNumber($v, $node)
	{
		if (is_int($v) || is_float($v))
			return $v;
		if (is_bool($v))
			return $v ? 1 : 0;
		if ($v === NULL)
			return 0;
		if (is_string($v)) {
			$s = trim($v);
			if ($s === '')
				return 0;
			if (!is_numeric($s)) {
				$this->fail('数値に変換できません: "' . $v . '"', $node);
			}
			return $s + 0;
		}
		$this->fail(self::typeName($v) . ' は数値として使えません', $node);
	}

	/** `===`: arrays and objects compare by identity, like JS. */
	private static function strictEquals($l, $r)
	{
		if (is_object($l) || is_object($r))
			return $l === $r;
		if (gettype($l) !== gettype($r))
			return FALSE;
		return $l === $r;
	}

	private static function looseEquals($l, $r)
	{
		if (is_string($l) && is_string($r))
			return $l === $r;
		if ((is_int($l) || is_float($l)) && (is_int($r) || is_float($r)))
			return $l == $r;
		if (is_bool($l) || is_bool($r))
			return self::toBool($l) === self::toBool($r);
		if ($l === NULL || $r === NULL)
			return $l === $r;
		if (is_object($l) && is_object($r))
			return $l === $r;
		return self::toStringValue($l) === self::toStringValue($r);
	}

	private function compare($l, $r, $node)
	{
		if (is_string($l) && is_string($r))
			return strcmp($l, $r);
		$a = $this->toNumber($l, $node);
		$b = $this->toNumber($r, $node);
		if ($a == $b)
			return 0;
		return $a < $b ? -1 : 1;
	}
}

<?php
// $Id: parser.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - parser
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// Parser

class Pkript_Parser {
	/**
	 * What a top level statement is refused with. Named because #pks reads
	 * it: this one message, and no other the parser can give, is what tells
	 * inline source apart from a whole script. See plugin_pks_compile().
	 */
	const NOT_A_SCRIPT = 'function or a variable declaration is required';

	/** Declarations but no function. #pks reads this as a body too. */
	const NO_FUNCTION = 'No function is defined';

	private $tokens;
	private $pos = 0;
	private $script;

	// Binary operator precedence (higher binds tighter)
	private static $precedence = array(
		'||' => 1,
		'&&' => 2,
		'==' => 3,
		'!=' => 3,
		'===' => 3,
		'!==' => 3,
		'<' => 4,
		'>' => 4,
		'<=' => 4,
		'>=' => 4,
		'+' => 5,
		'-' => 5,
		'*' => 6,
		'/' => 6,
		'%' => 6,
	);

	private static $assignOps = array('=', '+=', '-=', '*=', '/=', '%=');

	// isArrowAhead() results, keyed by token position
	private $arrowAhead = array();

	// Labels in scope where the parser is now, name => is it on a loop.
	// `continue` needs a loop; `break` takes either.
	private $labels = array();

	// `import "name"` at the top of the script
	private $imports = array();

	// Top level variable declarations, in the order written
	private $constants = array();

	public function __construct($tokens, $script) {
		$this->tokens = $tokens;
		$this->script = $script;
	}

	/** @return array list of function declarations, keyed by name */
	public function parse() {
		$functions = array();
		while (!$this->isEof()) {
			if ($this->isImportAhead()) {
				$this->imports[] = $this->parseImport();
				continue;
			}
			if ($this->check('keyword', 'function')) {
				$this->addFunction($functions, $this->parseFunction());
				continue;
			}

			$decl = $this->parseTopLevelDecl();
			if ($decl['init'] !== NULL && $decl['init']['type'] === 'Function') {
				// `const name = (a) => {...}` is a function declaration
				$fn = $decl['init'];
				$fn['name'] = $decl['name'];
				$this->addFunction($functions, $fn);
			} else {
				$this->claim($functions, $decl['name'], $decl);
				$this->constants[$decl['name']] = $decl;
			}
		}
		if (empty($functions) && empty($this->imports)) {
			throw new Pkript_Error(self::NO_FUNCTION, $this->script);
		}
		return $functions;
	}

	private function addFunction(&$functions, $fn) {
		$this->claim($functions, $fn['name'], $fn);
		$functions[$fn['name']] = $fn;
	}

	/** Functions and constants share one namespace within a script. */
	private function claim($functions, $name, $node) {
		if (isset($functions[$name]) || isset($this->constants[$name])) {
			$this->error("Identifier '" . $name .
				"' has already been declared", $node);
		}
	}

	/** Top level declarations, in order, run once before the entry point. */
	public function getConstants() {
		return array_values($this->constants);
	}

	/** Scripts named by `import`, in the order they were written. */
	public function getImports() {
		return $this->imports;
	}

	/**
	 * `import` is not a keyword: it only starts an import when a string
	 * follows, so a script may still use the word as a name.
	 */
	private function isImportAhead() {
		return $this->check('ident', 'import') && $this->peek(1)['type'] === 'str';
	}

	private function parseImport() {
		$kw = $this->next();
		$name = $this->next();
		$this->endStatement();
		return array('name' => $name['value']) + self::at($kw);
	}

	/////////////////////////////////////////////
	// Token helpers

	/** Source position of a token, folded into a node with `+`. */
	private static function at($token) {
		return array('line' => $token['line'], 'col' => $token['col']);
	}

	private function prev() {
		return $this->pos > 0 ? $this->tokens[$this->pos - 1] : NULL;
	}

	private function peek($offset = 0) {
		$i = $this->pos + $offset;
		return isset($this->tokens[$i]) ? $this->tokens[$i] : $this->tokens[count($this->tokens) - 1];
	}

	private function next() {
		$t = $this->peek();
		if ($t['type'] !== 'eof')
			$this->pos++;
		return $t;
	}

	private function isEof() {
		return $this->peek() === NULL || $this->peek()['type'] === 'eof';
	}

	private function check($type, $value = NULL) {
		$t = $this->peek();
		if ($t['type'] !== $type)
			return FALSE;
		return $value === NULL || $t['value'] === $value;
	}

	private function accept($type, $value = NULL) {
		if ($this->check($type, $value))
			return $this->next();
		return FALSE;
	}

	private function expect($type, $value = NULL) {
		if ($this->check($type, $value))
			return $this->next();
		$t = $this->peek();
		$want = $value === NULL ? $type : "'" . $value . "'";
		$got = $t['type'] === 'eof' ? 'end of file' : "'" . $t['value'] . "'";
		$this->error($want . ' expected but found ' . $got . '', $t);
	}

	private function error($message, $token = NULL) {
		$token = $token === NULL ? $this->peek() : $token;
		throw new Pkript_Error(
			$message,
			$this->script,
			isset($token['line']) ? $token['line'] : 0,
			isset($token['col']) ? $token['col'] : 0
		);
	}

	/////////////////////////////////////////////
	// Declarations and statements

	private function parseFunction() {
		$kw = $this->expect('keyword', 'function');
		$name = $this->expect('ident');
		$this->expect('op', '(');
		$params = $this->parseParams(')');
		$this->expect('op', ')');
		$body = $this->parseBlock();

		return array(
			'type' => 'Function',
			'name' => $name['value'],
			'params' => $params,
			'body' => $body,
			'line' => $kw['line'],
			'col' => $kw['col'],
		);
	}

	/**
	 * `const name = (a, b) => { ... };` at the top level. The same thing as a
	 * `function name(a, b) { ... }` declaration, so it yields the same node.
	 */
	private function parseTopLevelDecl() {
		if (!$this->check('keyword', 'const') && !$this->check('keyword', 'let') &&
			!$this->check('keyword', 'var')) {
			$this->error(self::NOT_A_SCRIPT);
		}
		// parseVarDecl() already refuses a const without a value
		return $this->parseVarDecl();
	}

	private function parseBlock() {
		$open = $this->expect('op', '{');
		$stmts = array();
		while (!$this->check('op', '}')) {
			if ($this->isEof()) {
				$this->error('{ is not closed', $open);
			}
			$stmts[] = $this->parseStatement();
		}
		$this->expect('op', '}');
		return array('type' => 'Block', 'body' => $stmts) + self::at($open);
	}

	private function parseStatement() {
		if ($this->check('op', '{'))
			return $this->parseBlock();
		if ($this->check('keyword', 'var'))
			return $this->parseVarDecl();
		if ($this->check('keyword', 'let'))
			return $this->parseVarDecl();
		if ($this->check('keyword', 'const'))
			return $this->parseVarDecl();
		if ($this->check('keyword', 'return'))
			return $this->parseReturn();
		if ($this->check('keyword', 'if'))
			return $this->parseIf();
		if ($this->check('keyword', 'while'))
			return $this->parseWhile();
		if ($this->check('keyword', 'do'))
			return $this->parseDoWhile();
		if ($this->isLabelAhead())
			return $this->parseLabelled();
		if ($this->check('keyword', 'switch'))
			return $this->parseSwitch();
		if ($this->check('keyword', 'try'))
			return $this->parseTry();
		if ($this->check('keyword', 'for'))
			return $this->parseFor();
		if ($this->check('keyword', 'break'))
			return $this->parseBreakOrContinue('Break');
		if ($this->check('keyword', 'continue'))
			return $this->parseBreakOrContinue('Continue');
		if ($this->check('keyword', 'function')) {
			$this->error('A function may only be declared at the top level');
		}
		if ($this->accept('op', ';')) {
			return array('type' => 'Empty', 'line' => 0, 'col' => 0);
		}
		return $this->parseExpressionStatement();
	}

	private function parseVarDecl($withSemicolon = TRUE) {
		$kw = $this->next();
		$name = $this->expect('ident');
		$init = NULL;
		if ($this->accept('op', '=')) {
			$init = $this->parseExpression();
		} elseif ($kw['value'] === 'const') {
			$this->error('const requires an initial value', $kw);
		}
		if ($withSemicolon)
			$this->endStatement();
		return array(
			'type' => 'VarDecl',
			'kind' => $kw['value'],
			'name' => $name['value'],
			'init' => $init
		) + self::at($kw);
	}

	private function parseReturn() {
		$kw = $this->next();
		$arg = NULL;
		if (!$this->check('op', ';') && !$this->check('op', '}')) {
			$arg = $this->parseExpression();
		}
		$this->endStatement();
		return array('type' => 'Return', 'argument' => $arg) + self::at($kw);
	}

	/**
	 * `break` / `continue`, each with an optional label.
	 *
	 * The label has to be on the same line as the keyword: a newline ends the
	 * statement, so `break` followed by `foo;` on the next line is a plain
	 * break and a statement, not a labelled one.
	 */
	private function parseBreakOrContinue($type) {
		$kw = $this->next();

		$label = '';
		if ($this->check('ident') && $this->peek()['line'] === $kw['line']) {
			$token = $this->next();
			$label = $token['value'];
			if (!isset($this->labels[$label]))
				$this->error("Undefined label '" . $label . "'", $token);
			if ($type === 'Continue' && !$this->labels[$label]) {
				$this->error('A continue label must name a loop',
					$token);
			}
		}
		$this->endStatement();
		return array('type' => $type, 'label' => $label) + self::at($kw);
	}

	/** `name:` before a statement. `case`/`default` are read by parseSwitch. */
	private function isLabelAhead() {
		if (!$this->check('ident'))
			return FALSE;
		$after = $this->peek(1);
		return $after['type'] === 'op' && $after['value'] === ':';
	}

	/**
	 * A labelled statement. A label on a loop is folded into the loop node,
	 * because `continue name` has to be caught by that loop's own iteration
	 * rather than by a wrapper around it, which would end the loop instead.
	 * On anything else only `break name` can reach it.
	 */
	private function parseLabelled() {
		$token = $this->next();
		$name = $token['value'];
		$this->expect('op', ':');

		if (isset($this->labels[$name]))
			$this->error("Label '" . $name . "' has already been declared",
				$token);

		static $loops = array('While' => 1, 'DoWhile' => 1, 'For' => 1,
			'ForIn' => 1, 'ForOf' => 1);

		// Declared before the body is read, so `break name` inside it resolves
		$this->labels[$name] = $this->isLoopAhead($loops);
		try {
			$body = $this->parseStatement();
		} finally {
			unset($this->labels[$name]);
		}

		if (isset($loops[$body['type']])) {
			$body['label'] = $name;
			return $body;
		}
		return array('type' => 'Labelled', 'label' => $name, 'body' => $body)
			+ self::at($token);
	}

	/** Is the statement starting here a loop? Decides what the label may do. */
	private function isLoopAhead($loops) {
		unset($loops);
		return $this->check('keyword', 'while') || $this->check('keyword', 'do') ||
			$this->check('keyword', 'for');
	}

	private function parseIf() {
		$kw = $this->next();
		$this->expect('op', '(');
		$test = $this->parseExpression();
		$this->expect('op', ')');
		$then = $this->parseStatement();

		$else = NULL;
		if ($this->check('keyword', 'else')) {
			$this->next();
			// 'else if' is just an if-statement in the else branch
			$else = $this->parseStatement();
		}
		return array('type' => 'If', 'test' => $test, 'then' => $then, 'else' => $else) + self::at($kw);
	}

	private function parseWhile() {
		$kw = $this->next();
		$this->expect('op', '(');
		$test = $this->parseExpression();
		$this->expect('op', ')');
		$body = $this->parseStatement();
		return array('type' => 'While', 'test' => $test, 'body' => $body,
			'label' => '') + self::at($kw);
	}

	/** `do { ... } while (test);` - the body runs before the test. */
	private function parseDoWhile() {
		$kw = $this->next();
		$body = $this->parseStatement();
		$this->expect('keyword', 'while');
		$this->expect('op', '(');
		$test = $this->parseExpression();
		$this->expect('op', ')');
		// The closing semicolon is part of the statement in JavaScript, and
		// optional here for the same reason every other one is
		$this->endStatement();
		return array('type' => 'DoWhile', 'test' => $test, 'body' => $body,
			'label' => '') + self::at($kw);
	}

	/** `for (init; test; update)` and `for (let x of expr)`. */
	/** `try { ... } catch (e) { ... }`. The binding may be left out. */
	private function parseTry() {
		$kw = $this->next();
		$block = $this->parseBlock();
		$this->expect('keyword', 'catch');

		$name = NULL;
		if ($this->accept('op', '(')) {
			$name = $this->expect('ident')['value'];
			$this->expect('op', ')');
		}
		$handler = $this->parseBlock();

		return array('type' => 'Try', 'block' => $block, 'name' => $name,
			'handler' => $handler) + self::at($kw);
	}

	/**
	 * `switch (x) { case a: ... default: ... }`, with JavaScript's fall
	 * through: a case runs on to the next one unless it breaks.
	 */
	private function parseSwitch() {
		$kw = $this->next();
		$this->expect('op', '(');
		$subject = $this->parseExpression();
		$this->expect('op', ')');
		$open = $this->expect('op', '{');

		$cases = array();
		$seenDefault = FALSE;
		while (!$this->check('op', '}')) {
			if ($this->isEof()) {
				$this->error('switch is not closed', $open);
			}

			if ($this->accept('keyword', 'default')) {
				if ($seenDefault) {
					$this->error('Only one default is allowed');
				}
				$seenDefault = TRUE;
				$test = NULL;
			} else {
				$this->expect('keyword', 'case');
				$test = $this->parseExpression();
			}
			$this->expect('op', ':');

			// Statements up to the next label, so fall through is just running on
			$body = array();
			while (
				!$this->check('op', '}') && !$this->check('keyword', 'case') &&
				!$this->check('keyword', 'default') && !$this->isEof()
			) {
				$body[] = $this->parseStatement();
			}
			$cases[] = array('test' => $test, 'body' => $body);
		}
		$this->expect('op', '}');

		return array('type' => 'Switch', 'subject' => $subject, 'cases' => $cases)
			+ self::at($kw);
	}

	private function parseFor() {
		$kw = $this->next();
		$this->expect('op', '(');

		// for (var|let|const IDENT of ...) and its `in` twin. Neither `of` nor
		// `in` is a keyword, so both are recognised by where they sit.
		$isDecl = $this->check('keyword', 'var') || $this->check('keyword', 'let') || $this->check('keyword', 'const');
		if (
			$isDecl && $this->peek(1)['type'] === 'ident' &&
			$this->peek(2)['type'] === 'ident' &&
			($this->peek(2)['value'] === 'of' || $this->peek(2)['value'] === 'in')
		) {
			$kind = $this->next(); // var / let / const
			$name = $this->next(); // loop variable
			$over = $this->next(); // 'of' or 'in'
			$subject = $this->parseExpression();
			$this->expect('op', ')');
			$body = $this->parseStatement();
			return array(
				'type' => $over['value'] === 'in' ? 'ForIn' : 'ForOf',
				'kind' => $kind['value'],
				'name' => $name['value'],
				'subject' => $subject,
				'body' => $body,
				'label' => ''
			) + self::at($kw);
		}

		$init = NULL;
		if (!$this->check('op', ';')) {
			$init = $isDecl
				? $this->parseVarDecl(FALSE)
				: array('type' => 'ExprStmt', 'expression' => $this->parseExpression()) + self::at($kw);
		}
		$this->expect('op', ';');

		$test = $this->check('op', ';') ? NULL : $this->parseExpression();
		$this->expect('op', ';');

		$update = $this->check('op', ')') ? NULL : $this->parseExpression();
		$this->expect('op', ')');

		$body = $this->parseStatement();
		return array(
			'type' => 'For',
			'init' => $init,
			'test' => $test,
			'update' => $update,
			'body' => $body,
			'label' => ''
		) + self::at($kw);
	}

	private function parseExpressionStatement() {
		$expr = $this->parseExpression();
		$this->endStatement();
		return array('type' => 'ExprStmt', 'expression' => $expr) + self::at($expr);
	}

	private function endStatement() {
		if ($this->accept('op', ';'))
			return;
		// Allow a missing ';' before '}' or at end of file
		if ($this->check('op', '}') || $this->isEof())
			return;
		$prev = $this->prev();
		$curr = $this->peek();
		if ($prev !== NULL && $curr !== NULL && $curr['line'] > $prev['line'])
			return;
		$this->error("Unexpected token, expected ';'");
	}

	/////////////////////////////////////////////
	// Expressions

	private function parseExpression() {
		return $this->parseAssignment();
	}

	private function parseAssignment() {
		$left = $this->parseConditional();

		$t = $this->peek();
		if ($t['type'] === 'op' && in_array($t['value'], self::$assignOps, TRUE)) {
			$op = $this->next();
			if (
				$left['type'] !== 'Identifier' &&
				$left['type'] !== 'Member' && $left['type'] !== 'Index'
			) {
				$this->error('Invalid assignment target', $op);
			}
			$value = $this->parseAssignment();
			return array(
				'type' => 'Assign',
				'op' => $op['value'],
				'target' => $left,
				'value' => $value
			) + self::at($op);
		}
		return $left;
	}

	private function parseConditional() {
		$test = $this->parseBinary(0);
		if (!$this->check('op', '?'))
			return $test;

		$q = $this->next();
		$then = $this->parseAssignment();
		$this->expect('op', ':');
		$else = $this->parseAssignment();
		return array(
			'type' => 'Conditional',
			'test' => $test,
			'then' => $then,
			'else' => $else
		) + self::at($q);
	}

	private function parseBinary($minPrec) {
		$left = $this->parseUnary();
		while (TRUE) {
			$t = $this->peek();
			if ($t['type'] !== 'op' || !isset(self::$precedence[$t['value']]))
				break;
			$prec = self::$precedence[$t['value']];
			if ($prec < $minPrec)
				break;
			$this->next();
			$right = $this->parseBinary($prec + 1);
			$left = array(
				'type' => 'Binary',
				'op' => $t['value'],
				'left' => $left,
				'right' => $right
			) + self::at($t);
		}
		return $left;
	}

	private function parseUnary() {
		if ($this->check('op', '!') || $this->check('op', '-') || $this->check('op', '+')) {
			$op = $this->next();
			$arg = $this->parseUnary();
			return array('type' => 'Unary', 'op' => $op['value'], 'argument' => $arg) + self::at($op);
		}
		if ($this->check('op', '++') || $this->check('op', '--')) {
			$op = $this->next();
			$arg = $this->parseUnary();
			return array(
				'type' => 'Update',
				'op' => $op['value'],
				'prefix' => TRUE,
				'argument' => $arg
			) + self::at($op);
		}
		return $this->parsePostfix();
	}

	private function parsePostfix() {
		$expr = $this->parsePrimary();
		while (TRUE) {
			if ($this->check('op', '.')) {
				$dot = $this->next();
				$prop = $this->expect('ident');
				$expr = array(
					'type' => 'Member',
					'object' => $expr,
					'property' => $prop['value']
				) + self::at($dot);
				continue;
			}
			if ($this->check('op', '[')) {
				$br = $this->next();
				$index = $this->parseExpression();
				$this->expect('op', ']');
				$expr = array('type' => 'Index', 'object' => $expr, 'index' => $index) + self::at($br);
				continue;
			}
			if ($this->check('op', '(')) {
				$par = $this->next();
				$args = array();
				if (!$this->check('op', ')')) {
					do {
						if ($this->check('op', ')'))
							break; // trailing comma
						$args[] = $this->parseExpression();
					} while ($this->accept('op', ','));
				}
				$this->expect('op', ')');
				$expr = array('type' => 'Call', 'callee' => $expr, 'arguments' => $args) + self::at($par);
				continue;
			}
			if ($this->check('op', '++') || $this->check('op', '--')) {
				$op = $this->next();
				$expr = array(
					'type' => 'Update',
					'op' => $op['value'],
					'prefix' => FALSE,
					'argument' => $expr
				) + self::at($op);
				continue;
			}
			break;
		}
		return $expr;
	}

	private function parsePrimary() {
		$t = $this->peek();

		if ($this->isArrowAhead())
			return $this->parseArrow();

		if ($this->accept('op', '(')) {
			$expr = $this->parseExpression();
			$this->expect('op', ')');
			return $expr;
		}
		if ($this->check('op', '[')) {
			$br = $this->next();
			$elements = array();
			if (!$this->check('op', ']')) {
				do {
					if ($this->check('op', ']'))
						break; // trailing comma
					$elements[] = $this->parseExpression();
				} while ($this->accept('op', ','));
			}
			$this->expect('op', ']');
			return array('type' => 'ArrayLit', 'elements' => $elements) + self::at($br);
		}
		if ($this->check('op', '{')) {
			return $this->parseObjectLit();
		}
		if ($this->check('template')) {
			$this->next();
			return $this->buildTemplate($t);
		}
		if ($this->check('jsx')) {
			$this->next();
			return $this->buildJsx($t['value'], $t);
		}
		if ($this->check('regex')) {
			$this->next();
			return $this->buildRegex($t);
		}
		if ($this->check('num') || $this->check('str')) {
			$this->next();
			return array('type' => 'Literal', 'value' => $t['value']) + self::at($t);
		}
		if ($this->check('keyword', 'true') || $this->check('keyword', 'false')) {
			$this->next();
			return array('type' => 'Literal', 'value' => ($t['value'] === 'true')) + self::at($t);
		}
		if ($this->check('keyword', 'null')) {
			$this->next();
			return array('type' => 'Literal', 'value' => NULL) + self::at($t);
		}
		if ($this->check('ident')) {
			$this->next();
			return array('type' => 'Identifier', 'name' => $t['value']) + self::at($t);
		}

		$got = $t['type'] === 'eof' ? 'end of file' : "'" . $t['value'] . "'";
		$this->error('Unexpected token, expected an expression but found ' . $got . '', $t);
	}

	/** Is an arrow function starting here? `x => ...` or `(a, b) => ...` */
	private function isArrowAhead() {
		if ($this->check('ident')) {
			$after = $this->peek(1);
			return $after['type'] === 'op' && $after['value'] === '=>';
		}
		if (!$this->check('op', '('))
			return FALSE;

		// Cached per position: without it, nested parentheses would each
		// rescan the rest of the token stream.
		if (isset($this->arrowAhead[$this->pos]))
			return $this->arrowAhead[$this->pos];

		// Scan to the matching ')' - the token after it decides
		$depth = 0;
		for ($i = 0; ; $i++) {
			$tok = $this->peek($i);
			if ($tok['type'] === 'eof')
				return $this->arrowAhead[$this->pos] = FALSE;
			if ($tok['type'] !== 'op')
				continue;
			if ($tok['value'] === '(')
				$depth++;
			elseif ($tok['value'] === ')') {
				if (--$depth > 0)
					continue;
				$after = $this->peek($i + 1);
				return $this->arrowAhead[$this->pos] =
					($after['type'] === 'op' && $after['value'] === '=>');
			}
		}
	}

	/**
	 * `(a, b) => { ... }` / `x => x + 1`. An expression body is wrapped in a
	 * return so every function has the one shape the interpreter runs.
	 */
	private function parseArrow() {
		$start = $this->peek();
		$params = array();

		if ($this->check('ident')) {
			$params[] = $this->next()['value'];
		} else {
			$this->expect('op', '(');
			$params = $this->parseParams(')');
			$this->expect('op', ')');
		}
		$this->expect('op', '=>');

		if ($this->check('op', '{')) {
			$body = $this->parseBlock();
		} else {
			$expr = $this->parseAssignment();
			$body = array(
				'type' => 'Block',
				'body' => array(
					array('type' => 'Return', 'argument' => $expr) + self::at($start),
				)
			) + self::at($start);
		}

		return array(
			'type' => 'Function',
			'name' => '',
			'params' => $params,
			'body' => $body
		) + self::at($start);
	}

	/** Comma separated parameter names, up to but not including $closeOp. */
	private function parseParams($closeOp) {
		$params = array();
		if ($this->check('op', $closeOp))
			return $params;
		do {
			if ($this->check('op', $closeOp))
				break; // trailing comma
			$p = $this->expect('ident');
			$params[] = $p['value'];
		} while ($this->accept('op', ','));
		return $params;
	}

	/**
	 * The parts the lexer collected for a template literal. Each `${...}` was
	 * tokenized on its own, so each gets its own parser here.
	 */
	private function buildTemplate($token) {
		$parts = array();
		foreach ($token['value'] as $part) {
			if ($part['type'] === 'str') {
				$parts[] = array('type' => 'str', 'value' => $part['value']);
				continue;
			}
			$inner = new Pkript_Parser($part['tokens'], $this->script);
			$parts[] = array('type' => 'expr', 'expression' => $inner->parseSingleExpression());
		}
		return array('type' => 'Template', 'parts' => $parts) + self::at($token);
	}

	/** One expression and nothing after it. Used for `${...}` and JSX `{...}`. */
	public function parseSingleExpression($what = '${}') {
		$expr = $this->parseExpression();
		if (!$this->isEof()) {
			$this->error($what . ' has trailing tokens');
		}
		return $expr;
	}

	/////////////////////////////////////////////
	// JSX

	// Attribute names JSX spells differently, because JavaScript took the
	// HTML ones. A script may write either spelling.
	private static $jsxAttrAliases = array(
		'className' => 'class',
		'htmlFor' => 'for',
	);

	/**
	 * The tree the lexer built for one element. Text and quoted attribute
	 * values are escaped here, once, because they can never change; only the
	 * braced expressions are left for the interpreter.
	 */
	private function buildJsx($node, $token) {
		$attrs = array();
		foreach ($node['attrs'] as $attr) {
			$name = $attr['name'];
			if (isset(self::$jsxAttrAliases[$name]))
				$name = self::$jsxAttrAliases[$name];

			$value = NULL;
			if ($attr['value'] !== NULL) {
				$value = $attr['value']['type'] === 'str'
					? array('type' => 'str',
						'value' => self::escapeJsxSource($attr['value']['value']))
					: array('type' => 'expr',
						'expression' => $this->parseJsxExpression($attr['value']));
			}
			$attrs[] = array('name' => $name, 'value' => $value);
		}

		$children = array();
		foreach ($node['children'] as $child) {
			if ($child['type'] === 'text') {
				$children[] = array('type' => 'text',
					'value' => self::escapeJsxSource($child['value']));
			} elseif ($child['type'] === 'expr') {
				$children[] = array('type' => 'expr',
					'expression' => $this->parseJsxExpression($child));
			} else {
				$children[] = array('type' => 'element',
					'element' => $this->buildJsx($child['node'], $token));
			}
		}

		return array(
			'type' => 'Jsx',
			'tag' => $node['tag'],
			'attributes' => $attrs,
			'children' => $children,
			'void' => $node['void'],
			'line' => $node['line'],
			'col' => $node['col'],
		);
	}

	/**
	 * A regex literal. The pattern is checked here rather than when it runs:
	 * a bad one is then an error before anything happens, and the cached AST
	 * can only have been built from a pattern that passed.
	 */
	private function buildRegex($token) {
		$re = $token['value'];
		$reason = Pkript_Regex::check($re['source'], $re['flags']);
		if ($reason !== '')
			$this->error($reason, $token);

		return array('type' => 'Regex', 'source' => $re['source'],
			'flags' => $re['flags']) + self::at($token);
	}

	private function parseJsxExpression($part) {
		$inner = new Pkript_Parser($part['tokens'], $this->script);
		return $inner->parseSingleExpression('JSX {}');
	}

	/**
	 * Markup written in the script is HTML, so only what would change the
	 * shape of a tag is escaped. An '&' that already starts a character
	 * reference is left alone, so `&amp;` and `&nbsp;` mean what they say.
	 */
	private static function escapeJsxSource($text) {
		$text = preg_replace(
			'/&(?![A-Za-z][A-Za-z0-9]{1,30};|#[0-9]{1,7};|#[xX][0-9A-Fa-f]{1,6};)/',
			'&amp;', $text);
		return str_replace(
			array('<', '>', '"'),
			array('&lt;', '&gt;', '&quot;'),
			$text);
	}

	/** `{ key: value, "key": value }` */
	private function parseObjectLit() {
		$open = $this->next();
		$props = array();
		if (!$this->check('op', '}')) {
			do {
				if ($this->check('op', '}'))
					break; // trailing comma

				$k = $this->peek();
				if ($k['type'] === 'ident' || $k['type'] === 'keyword' || $k['type'] === 'str') {
					$this->next();
					$key = (string) $k['value'];
				} elseif ($k['type'] === 'num') {
					$this->next();
					$key = Pkript_Interpreter::toStringValue($k['value']);
				} else {
					$this->error('A property name is required', $k);
				}

				$this->expect('op', ':');
				$props[] = array('key' => $key, 'value' => $this->parseExpression());
			} while ($this->accept('op', ','));
		}
		$this->expect('op', '}');
		return array('type' => 'ObjectLit', 'properties' => $props) + self::at($open);
	}
}

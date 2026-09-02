<?php
// $Id: parser.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

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

	// Binary operator precedence (higher binds tighter). The rungs are
	// JavaScript's own, so an expression copied from a browser groups here
	// the way it does there.
	private static $precedence = array(
		'??' => 1,
		'||' => 2,
		'&&' => 3,
		'|' => 4,
		'^' => 5,
		'&' => 6,
		'==' => 7,
		'!=' => 7,
		'===' => 7,
		'!==' => 7,
		'<' => 8,
		'>' => 8,
		'<=' => 8,
		'>=' => 8,
		'instanceof' => 8,
		'in' => 8,
		'<<' => 9,
		'>>' => 9,
		'>>>' => 9,
		'+' => 10,
		'-' => 10,
		'*' => 11,
		'/' => 11,
		'%' => 11,
		'**' => 12,
	);

	/** The one binary operator that groups to the right: 2**3**2 is 2**9. */
	private static $rightAssoc = array('**' => 1);

	/** Two of the operators are spelled as words, so they arrive as keywords. */
	private static $wordOps = array('instanceof' => 1, 'in' => 1);

	private static $assignOps = array(
		'=', '+=', '-=', '*=', '/=', '%=', '**=',
		'<<=', '>>=', '>>>=', '&=', '|=', '^=',
		// The three that only assign when the test says to
		'&&=', '||=', '??=',
	);

	/** Prefix operators, by the token type they arrive as. */
	private static $unaryOps = array('!' => 1, '-' => 1, '+' => 1, '~' => 1);
	private static $unaryWords = array('typeof' => 1, 'void' => 1);

	// isArrowAhead() results, keyed by token position
	private $arrowAhead = array();

	// The var / let / const token of the declaration being read, so the
	// declarators after a comma know which one they belong to
	private $declKeyword = NULL;

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

			// `const a = 1, b = 2;` declares two of them; each stands on its
			// own up here, so the list is taken apart rather than kept
			foreach ($this->parseTopLevelDecl() as $decl) {
				if ($decl['pattern'] !== NULL) {
					// Top level declarations are the script's exports, and a
					// pattern would make what a script publishes depend on
					// what its initialiser turned out to hold
					$this->error(
						'A top level declaration must name one thing', $decl);
				}
				if ($decl['init'] !== NULL && $decl['init']['type'] === 'Function') {
					// `const name = (a) => {...}` is a function declaration
					$fn = $decl['init'];
					$fn['name'] = $decl['name'];
					$this->addFunction($functions, $fn);
					continue;
				}
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
		return self::declarationsOf($this->parseVarDecl());
	}

	/** The declarators of a VarDecl or a VarDeclList, as a flat list. */
	public static function declarationsOf($node) {
		return $node['type'] === 'VarDeclList'
			? $node['declarations'] : array($node);
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
		if ($this->check('keyword', 'throw'))
			return $this->parseThrow();
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

	/**
	 * `let a = 1, b = 2;`. One declarator yields a VarDecl, which is the
	 * shape everything downstream already knows; several yield a VarDeclList
	 * holding one VarDecl each, so nothing has to learn a second shape.
	 */
	private function parseVarDecl($withSemicolon = TRUE) {
		$kw = $this->peek();
		$decls = array();
		do {
			$decls[] = $this->parseDeclarator();
		} while ($this->accept('op', ','));

		if ($withSemicolon)
			$this->endStatement();
		if (count($decls) === 1)
			return $decls[0];
		return array('type' => 'VarDeclList', 'kind' => $kw['value'],
			'declarations' => $decls) + self::at($kw);
	}

	/** One `name = value` of a declaration; the keyword is peeked, not eaten. */
	private function parseDeclarator() {
		$kw = $this->peek();
		if ($kw['type'] === 'keyword' &&
			($kw['value'] === 'var' || $kw['value'] === 'let' ||
			 $kw['value'] === 'const')) {
			$this->next();
		} else {
			// A second declarator after a comma; it keeps the first's keyword
			$kw = $this->declKeyword;
		}
		$this->declKeyword = $kw;

		$target = $this->parseBindingTarget();
		$init = NULL;
		if ($this->accept('op', '=')) {
			$init = $this->parseAssignment();
		} elseif ($kw['value'] === 'const') {
			$this->error('const requires an initial value', $kw);
		} elseif ($target['type'] !== 'Identifier') {
			// There is nothing to take apart, so the pattern would bind
			// every one of its names to null - which nobody means to write
			$this->error('A destructuring declaration requires a value', $kw);
		}

		return self::bindingNode('VarDecl', $target,
			array('kind' => $kw['value'], 'init' => $init)) + self::at($kw);
	}

	/**
	 * A node that binds something: a plain name, or a pattern to take apart.
	 *
	 * The two are told apart by which key is filled in, and a plain name
	 * still yields the same 'name' every consumer already reads - so only the
	 * places that want to handle a pattern have to know patterns exist.
	 */
	private static function bindingNode($type, $target, $extra) {
		if ($target['type'] === 'Identifier') {
			return array('type' => $type, 'name' => $target['name'],
				'pattern' => NULL) + $extra;
		}
		return array('type' => $type, 'name' => NULL,
			'pattern' => $target) + $extra;
	}

	/////////////////////////////////////////////
	// Destructuring patterns
	//
	// `const { a, b: c = 1, ...rest } = o` and `const [x, , y] = a`. A pattern
	// is a shape to match a value against, not an expression - `{ a }` on the
	// left of an `=` names a variable, where the same text on the right builds
	// an object - so it is read by its own functions rather than by
	// parsePrimary().

	/** A name, or a pattern to take apart. */
	private function parseBindingTarget() {
		if ($this->check('op', '{'))
			return $this->parseObjectPattern();
		if ($this->check('op', '['))
			return $this->parseArrayPattern();

		$name = $this->expect('ident');
		return array('type' => 'Identifier', 'name' => $name['value'])
			+ self::at($name);
	}

	/**
	 * `{ a, b: c, d = 1, [k]: e, ...rest }`
	 *
	 * Each property says which key to read and where to put it. The two are
	 * the same name in the shorthand `{ a }`, and the target may itself be a
	 * pattern, which is what makes `{ a: { b } }` work.
	 */
	private function parseObjectPattern() {
		$open = $this->expect('op', '{');
		$props = array();

		while (!$this->check('op', '}')) {
			if ($this->accept('op', '...')) {
				$name = $this->expect('ident');
				$props[] = array('key' => NULL, 'computed' => NULL,
					'target' => array('type' => 'Identifier',
						'name' => $name['value']) + self::at($name),
					'default' => NULL, 'rest' => TRUE) + self::at($name);
				if (!$this->check('op', '}'))
					$this->error('A rest property must come last', $this->peek());
				break;
			}
			$props[] = $this->parseObjectPatternProperty();
			if (!$this->accept('op', ','))
				break;
		}

		$this->expect('op', '}');
		return array('type' => 'ObjectPattern', 'properties' => $props)
			+ self::at($open);
	}

	private function parseObjectPatternProperty() {
		$k = $this->peek();

		$key = NULL;
		$computed = NULL;
		if ($this->accept('op', '[')) {
			$computed = $this->parseAssignment();
			$this->expect('op', ']');
			$this->expect('op', ':');
			$target = $this->parseBindingTarget();
		} else {
			$key = $this->expectPropertyKey();
			if ($this->accept('op', ':')) {
				// `{ a: b }` reads a and binds b
				$target = $this->parseBindingTarget();
			} else {
				// `{ a }` binds a, which only a name can mean: `{ "a" }`
				// names nothing to bind it to
				if ($k['type'] !== 'ident') {
					$this->error("':' expected after the property name",
						$this->peek());
				}
				$target = array('type' => 'Identifier', 'name' => $key)
					+ self::at($k);
			}
		}

		$default = $this->accept('op', '=') ? $this->parseAssignment() : NULL;
		return array('key' => $key, 'computed' => $computed, 'target' => $target,
			'default' => $default, 'rest' => FALSE) + self::at($k);
	}

	/** A property name in a pattern: an identifier, a keyword, a string, a number. */
	private function expectPropertyKey() {
		$k = $this->peek();
		if ($k['type'] === 'ident' || $k['type'] === 'keyword' || $k['type'] === 'str') {
			$this->next();
			return (string) $k['value'];
		}
		if ($k['type'] === 'num') {
			$this->next();
			return Pkript_Interpreter::toStringValue($k['value']);
		}
		$this->error('A property name is required', $k);
	}

	/**
	 * `[a, , b = 1, ...rest]`
	 *
	 * A hole - two commas in a row - skips that position without binding
	 * anything, so `[, second]` reads only the second element.
	 */
	private function parseArrayPattern() {
		$open = $this->expect('op', '[');
		$elements = array();

		while (!$this->check('op', ']')) {
			if ($this->check('op', ',')) {
				$this->next();
				$elements[] = NULL;   // a hole
				continue;
			}
			if ($this->accept('op', '...')) {
				$name = $this->expect('ident');
				$elements[] = array(
					'target' => array('type' => 'Identifier',
						'name' => $name['value']) + self::at($name),
					'default' => NULL, 'rest' => TRUE);
				if (!$this->check('op', ']'))
					$this->error('A rest element must come last', $this->peek());
				break;
			}

			$target = $this->parseBindingTarget();
			$default = $this->accept('op', '=') ? $this->parseAssignment() : NULL;
			$elements[] = array('target' => $target, 'default' => $default,
				'rest' => FALSE);
			if (!$this->accept('op', ','))
				break;
		}

		$this->expect('op', ']');
		return array('type' => 'ArrayPattern', 'elements' => $elements)
			+ self::at($open);
	}

	/** Every name a pattern binds, in the order it binds them. */
	public static function patternNames($pattern, &$out) {
		if ($pattern['type'] === 'Identifier') {
			$out[] = $pattern['name'];
			return;
		}
		if ($pattern['type'] === 'ObjectPattern') {
			foreach ($pattern['properties'] as $prop)
				self::patternNames($prop['target'], $out);
			return;
		}
		foreach ($pattern['elements'] as $el) {
			if ($el !== NULL)
				self::patternNames($el['target'], $out);
		}
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
	/**
	 * `throw expr;`
	 *
	 * The value has to be on the same line as the keyword, as in JavaScript:
	 * a newline there would make the throw argumentless, which nothing can
	 * mean, so it is refused rather than read as a bare `throw`.
	 */
	private function parseThrow() {
		$kw = $this->next();
		if ($this->check('op', ';') || $this->check('op', '}') ||
			$this->isEof() || $this->peek()['line'] > $kw['line']) {
			$this->error('throw needs a value on the same line', $kw);
		}
		$arg = $this->parseExpression();
		$this->endStatement();
		return array('type' => 'Throw', 'argument' => $arg) + self::at($kw);
	}

	/**
	 * `try { ... } catch (e) { ... } finally { ... }`. The binding may be
	 * left out, and so may either trailing clause - but not both.
	 */
	private function parseTry() {
		$kw = $this->next();
		$block = $this->parseBlock();

		$name = NULL;
		$handler = NULL;
		if ($this->accept('keyword', 'catch')) {
			if ($this->accept('op', '(')) {
				$name = $this->expect('ident')['value'];
				$this->expect('op', ')');
			}
			$handler = $this->parseBlock();
		}

		$finally = NULL;
		if ($this->accept('keyword', 'finally'))
			$finally = $this->parseBlock();

		if ($handler === NULL && $finally === NULL)
			$this->error('try needs a catch or a finally', $kw);

		return array('type' => 'Try', 'block' => $block, 'name' => $name,
			'handler' => $handler, 'finally' => $finally) + self::at($kw);
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

		// for (var|let|const IDENT of ...) and its `in` twin. `of` is a plain
		// identifier, so a script may still use the word as a name; `in` has
		// to be a keyword because it is also an operator. Both are picked out
		// by sitting right after the loop variable.
		$isDecl = $this->check('keyword', 'var') || $this->check('keyword', 'let') || $this->check('keyword', 'const');
		if ($isDecl && $this->isForEachAhead()) {
			$kind = $this->next();                    // var / let / const
			$target = $this->parseBindingTarget();    // a name, or a pattern
			$over = $this->next();                    // 'of' or 'in'
			$subject = $this->parseExpression();
			$this->expect('op', ')');
			$body = $this->parseStatement();
			return self::bindingNode(
				$over['value'] === 'in' ? 'ForIn' : 'ForOf', $target,
				array('kind' => $kind['value'], 'subject' => $subject,
					'body' => $body, 'label' => '')) + self::at($kw);
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

	/**
	 * Is this `for (let X of ...)` rather than `for (let i = 0; ...)`?
	 *
	 * What tells them apart is the word right after the loop variable, and
	 * the variable may be a whole pattern - so the brackets are counted past
	 * rather than assumed to be one token.
	 */
	private function isForEachAhead() {
		$t = $this->peek(1);
		if ($t['type'] === 'ident') {
			$after = 2;
		} elseif ($t['type'] === 'op' &&
			($t['value'] === '{' || $t['value'] === '[')) {
			$after = $this->afterBracketed(1);
			if ($after < 0)
				return FALSE;
		} else {
			return FALSE;
		}

		$word = $this->peek($after);
		return ($word['type'] === 'ident' && $word['value'] === 'of') ||
			($word['type'] === 'keyword' && $word['value'] === 'in');
	}

	/** Offset just past the bracketed group starting at $i, or -1 if unclosed. */
	private function afterBracketed($i) {
		$depth = 0;
		while (TRUE) {
			$t = $this->peek($i);
			if ($t['type'] === 'eof')
				return -1;
			if ($t['type'] === 'op') {
				if ($t['value'] === '{' || $t['value'] === '[') {
					$depth++;
				} elseif ($t['value'] === '}' || $t['value'] === ']') {
					if (--$depth === 0)
						return $i + 1;
				}
			}
			$i++;
		}
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
			if (!self::isBinaryOp($t))
				break;
			$op = $t['value'];
			$prec = self::$precedence[$op];
			if ($prec < $minPrec)
				break;
			$this->next();
			// A right-associative operator re-enters at its own rung, so the
			// operator to its right wins the operand between them
			$right = $this->parseBinary(
				isset(self::$rightAssoc[$op]) ? $prec : $prec + 1);
			$this->refuseNullishMix($op, $left, $right, $t);
			$left = array(
				'type' => 'Binary',
				'op' => $op,
				'left' => $left,
				'right' => $right
			) + self::at($t);
		}
		return $left;
	}

	private static function isBinaryOp($t) {
		if ($t['type'] === 'op')
			return isset(self::$precedence[$t['value']]);
		return $t['type'] === 'keyword' && isset(self::$wordOps[$t['value']]);
	}

	/**
	 * `a ?? b || c` has two readings and JavaScript refuses to pick one, so
	 * it is a syntax error there and here. Parentheses say which was meant -
	 * and a parenthesised operand is marked, so `(a ?? b) || c` is fine.
	 */
	private function refuseNullishMix($op, $left, $right, $token) {
		if ($op !== '??' && $op !== '&&' && $op !== '||')
			return;
		$clashes = $op === '??' ? array('&&' => 1, '||' => 1) : array('??' => 1);

		foreach (array($left, $right) as $side) {
			if ($side['type'] === 'Binary' && empty($side['paren']) &&
				isset($clashes[$side['op']])) {
				$this->error(
					'?? cannot be mixed with && or || without parentheses',
					$token);
			}
		}
	}

	private function parseUnary() {
		$t = $this->peek();
		if (($t['type'] === 'op' && isset(self::$unaryOps[$t['value']])) ||
			($t['type'] === 'keyword' && isset(self::$unaryWords[$t['value']]))) {
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

		// Once one link of a chain is optional the whole rest of it is: in
		// `a?.b.c`, a being null yields null rather than failing on `.c`. So
		// every link after the first `?.` is marked too, and the interpreter
		// needs to look no further than the node in front of it.
		$optional = FALSE;

		while (TRUE) {
			// `a?.b`, `a?.[i]`, `a?.(x)`: each link yields null rather than
			// failing when what it reads from is null
			if ($this->check('op', '?.')) {
				$optional = TRUE;
				$q = $this->next();
				if ($this->check('op', '['))
					$expr = $this->parseIndexTail($expr, TRUE);
				elseif ($this->check('op', '('))
					$expr = $this->parseCallTail($expr, TRUE);
				else
					$expr = $this->parseMemberTail($expr, $q, TRUE);
				continue;
			}
			if ($this->check('op', '.')) {
				$expr = $this->parseMemberTail($expr, $this->next(), $optional);
				continue;
			}
			if ($this->check('op', '[')) {
				$expr = $this->parseIndexTail($expr, $optional);
				continue;
			}
			if ($this->check('op', '(')) {
				$expr = $this->parseCallTail($expr, $optional);
				continue;
			}
			// A newline before `++` ends the statement instead, as in
			// JavaScript, where a line starting with ++ increments what is
			// on that line rather than what came before it
			if (($this->check('op', '++') || $this->check('op', '--')) &&
				$this->onSameLineAsPrevious()) {
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

	private function onSameLineAsPrevious() {
		$prev = $this->prev();
		return $prev === NULL || $this->peek()['line'] === $prev['line'];
	}

	private function parseMemberTail($expr, $dot, $optional) {
		return array(
			'type' => 'Member',
			'object' => $expr,
			'property' => $this->expectPropertyName(),
			'optional' => $optional
		) + self::at($dot);
	}

	private function parseIndexTail($expr, $optional) {
		$br = $this->next();
		$index = $this->parseExpression();
		$this->expect('op', ']');
		return array('type' => 'Index', 'object' => $expr, 'index' => $index,
			'optional' => $optional) + self::at($br);
	}

	private function parseCallTail($expr, $optional) {
		$par = $this->next();
		$args = $this->parseArguments(')');
		$this->expect('op', ')');
		return array('type' => 'Call', 'callee' => $expr, 'arguments' => $args,
			'optional' => $optional) + self::at($par);
	}

	/** Comma separated arguments, `...spread` included, up to $closeOp. */
	private function parseArguments($closeOp) {
		$args = array();
		if ($this->check('op', $closeOp))
			return $args;
		do {
			if ($this->check('op', $closeOp))
				break; // trailing comma
			$args[] = $this->parseSpreadable();
		} while ($this->accept('op', ','));
		return $args;
	}

	/** One element of a list, which may be `...expr`. */
	private function parseSpreadable() {
		if ($this->check('op', '...')) {
			$dots = $this->next();
			return array('type' => 'Spread',
				'argument' => $this->parseAssignment()) + self::at($dots);
		}
		return $this->parseAssignment();
	}

	/**
	 * A property name after a dot. JavaScript took the keywords for itself,
	 * but an object may still have a property called `default` or `in`, so
	 * one is read here as the name it is.
	 */
	private function expectPropertyName() {
		$t = $this->peek();
		if ($t['type'] === 'ident' || $t['type'] === 'keyword')
			return $this->next()['value'];
		$this->error('A property name is required', $t);
	}

	private function parsePrimary() {
		$t = $this->peek();

		if ($this->isArrowAhead())
			return $this->parseArrow();

		if ($this->accept('op', '(')) {
			$expr = $this->parseExpression();
			$this->expect('op', ')');
			// Marked so refuseNullishMix() can tell `(a ?? b) || c`, which is
			// fine, from `a ?? b || c`, which is not
			$expr['paren'] = TRUE;
			return $expr;
		}
		if ($this->check('op', '[')) {
			$br = $this->next();
			$elements = $this->parseArguments(']');
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
			$params[] = array('name' => $this->next()['value'], 'pattern' => NULL,
				'default' => NULL, 'rest' => FALSE);
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

	/**
	 * Parameters, up to but not including $closeOp. Each one is a name, with
	 * an optional default and a flag saying whether it is the rest parameter
	 * that collects everything left over.
	 *
	 * A parameter may also be a pattern - `function f({ a, b })` - and then
	 * 'name' is NULL and 'pattern' holds the shape to take the argument apart
	 * with.
	 *
	 * @return array of array('name' => string|NULL, 'pattern' => node|NULL,
	 *                        'default' => node|NULL, 'rest' => bool)
	 */
	private function parseParams($closeOp) {
		$params = array();
		if ($this->check('op', $closeOp))
			return $params;
		do {
			if ($this->check('op', $closeOp))
				break; // trailing comma

			$p = $this->peek();
			$rest = (bool) $this->accept('op', '...');
			// A rest parameter is a name: there is nothing to take apart in
			// "everything that is left"
			$target = $rest
				? array('type' => 'Identifier',
					'name' => $this->expect('ident')['value']) + self::at($p)
				: $this->parseBindingTarget();

			$default = NULL;
			if ($this->accept('op', '=')) {
				if ($rest)
					$this->error('A rest parameter may not have a default', $p);
				$default = $this->parseAssignment();
			}
			$params[] = self::bindingNode('Param', $target,
				array('default' => $default, 'rest' => $rest));

			if ($rest && !$this->check('op', $closeOp)) {
				$this->error('A rest parameter must come last', $this->peek());
			}
		} while ($this->accept('op', ','));

		$this->refuseDuplicateParams($params);
		return $params;
	}

	private function refuseDuplicateParams($params) {
		$seen = array();
		foreach ($params as $param) {
			$names = array();
			if ($param['pattern'] === NULL)
				$names[] = $param['name'];
			else
				self::patternNames($param['pattern'], $names);

			foreach ($names as $name) {
				if (isset($seen[$name]))
					$this->error("Duplicate parameter name '" . $name . "'");
				$seen[$name] = TRUE;
			}
		}
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

	/**
	 * `{ key: value, "key": value, [expr]: value, shorthand, ...rest }`
	 *
	 * A property is one of four things, and which one is decided by what
	 * follows the name: a ':' makes it an ordinary pair, a ',' or the closing
	 * brace makes it the shorthand for `name: name`. A '[' before the name
	 * means the key is computed when the object is built, and '...' spreads
	 * another object's properties in at that point.
	 */
	private function parseObjectLit() {
		$open = $this->next();
		$props = array();
		if (!$this->check('op', '}')) {
			do {
				if ($this->check('op', '}'))
					break; // trailing comma
				$props[] = $this->parseObjectProperty();
			} while ($this->accept('op', ','));
		}
		$this->expect('op', '}');
		return array('type' => 'ObjectLit', 'properties' => $props) + self::at($open);
	}

	private function parseObjectProperty() {
		if ($this->check('op', '...')) {
			$dots = $this->next();
			return array('key' => NULL, 'computed' => NULL, 'spread' => TRUE,
				'value' => $this->parseAssignment()) + self::at($dots);
		}

		$k = $this->peek();
		if ($this->check('op', '[')) {
			$this->next();
			$computed = $this->parseAssignment();
			$this->expect('op', ']');
			$this->expect('op', ':');
			return array('key' => NULL, 'computed' => $computed,
				'spread' => FALSE, 'value' => $this->parseAssignment())
				+ self::at($k);
		}

		if ($k['type'] === 'ident' || $k['type'] === 'keyword' || $k['type'] === 'str') {
			$this->next();
			$key = (string) $k['value'];
		} elseif ($k['type'] === 'num') {
			$this->next();
			$key = Pkript_Interpreter::toStringValue($k['value']);
		} else {
			$this->error('A property name is required', $k);
		}

		// `{ x }` is `{ x: x }`, which only a plain identifier can mean
		if (!$this->check('op', ':')) {
			if ($k['type'] !== 'ident') {
				$this->error("':' expected after the property name", $this->peek());
			}
			return array('key' => $key, 'computed' => NULL, 'spread' => FALSE,
				'value' => array('type' => 'Identifier', 'name' => $key)
					+ self::at($k)) + self::at($k);
		}
		$this->next();
		return array('key' => $key, 'computed' => NULL, 'spread' => FALSE,
			'value' => $this->parseAssignment()) + self::at($k);
	}
}

<?php
// $Id: parser.php,v 0.3 2026/08/30 00:00:00 Pitan Exp $

/**
* Pkript runtime - parser
*
* @link https://blog.pitan76.net/?Pkript
* @author Pitan
* @license https://opensource.org/license/mit MIT
*/

// Loaded by plugin/pkript.inc.php; not meant to be requested directly.
if (! defined('PKRIPT_RUNTIME')) exit;

/////////////////////////////////////////////////
// Parser

class Pkript_Parser {
	private $tokens;
	private $pos = 0;
	private $script;

	// Binary operator precedence (higher binds tighter)
	private static $precedence = array(
		'||'  => 1,
		'&&'  => 2,
		'=='  => 3, '!=' => 3, '===' => 3, '!==' => 3,
		'<'   => 4, '>'  => 4, '<='  => 4, '>='  => 4,
		'+'   => 5, '-'  => 5,
		'*'   => 6, '/'  => 6, '%'   => 6,
	);

	private static $assignOps = array('=', '+=', '-=', '*=', '/=', '%=');

	// isArrowAhead() results, keyed by token position
	private $arrowAhead = array();

	// `import "name"` at the top of the script
	private $imports = array();

	public function __construct($tokens, $script) {
		$this->tokens = $tokens;
		$this->script = $script;
	}

	/** @return array list of function declarations, keyed by name */
	public function parse() {
		$functions = array();
		while (! $this->isEof()) {
			if ($this->isImportAhead()) {
				$this->imports[] = $this->parseImport();
				continue;
			}
			$fn = $this->check('keyword', 'function')
				? $this->parseFunction() : $this->parseNamedArrow();
			if (isset($functions[$fn['name']])) {
				$this->error('関数 ' . $fn['name'] . ' が二重に定義されています', $fn);
			}
			$functions[$fn['name']] = $fn;
		}
		if (empty($functions) && empty($this->imports)) {
			throw new Pkript_Error('関数が定義されていません', $this->script);
		}
		return $functions;
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
		if ($t['type'] !== 'eof') $this->pos++;
		return $t;
	}

	private function isEof() {
		return $this->peek() === NULL || $this->peek()['type'] === 'eof';
	}

	private function check($type, $value = NULL) {
		$t = $this->peek();
		if ($t['type'] !== $type) return FALSE;
		return $value === NULL || $t['value'] === $value;
	}

	private function accept($type, $value = NULL) {
		if ($this->check($type, $value)) return $this->next();
		return FALSE;
	}

	private function expect($type, $value = NULL) {
		if ($this->check($type, $value)) return $this->next();
		$t = $this->peek();
		$want = $value === NULL ? $type : "'" . $value . "'";
		$got  = $t['type'] === 'eof' ? 'ファイル終端' : "'" . $t['value'] . "'";
		$this->error($want . ' が必要ですが ' . $got . ' がありました', $t);
	}

	private function error($message, $token = NULL) {
		$token = $token === NULL ? $this->peek() : $token;
		throw new Pkript_Error($message, $this->script,
			isset($token['line']) ? $token['line'] : 0,
			isset($token['col'])  ? $token['col']  : 0);
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
			'type'   => 'Function',
			'name'   => $name['value'],
			'params' => $params,
			'body'   => $body,
			'line'   => $kw['line'],
			'col'    => $kw['col'],
		);
	}

	/**
	 * `const name = (a, b) => { ... };` at the top level. The same thing as a
	 * `function name(a, b) { ... }` declaration, so it yields the same node.
	 */
	private function parseNamedArrow() {
		if (! $this->check('keyword', 'const') && ! $this->check('keyword', 'let') &&
			! $this->check('keyword', 'var')) {
			$this->error('関数定義が必要です');
		}
		$decl = $this->parseVarDecl();
		if ($decl['init'] === NULL || $decl['init']['type'] !== 'Function') {
			$this->error('トップレベルには関数定義しか書けません', $decl);
		}
		$fn = $decl['init'];
		$fn['name'] = $decl['name'];
		return $fn;
	}

	private function parseBlock() {
		$open = $this->expect('op', '{');
		$stmts = array();
		while (! $this->check('op', '}')) {
			if ($this->isEof()) {
				$this->error('{ が閉じられていません', $open);
			}
			$stmts[] = $this->parseStatement();
		}
		$this->expect('op', '}');
		return array('type' => 'Block', 'body' => $stmts) + self::at($open);
	}

	private function parseStatement() {
		if ($this->check('op', '{'))             return $this->parseBlock();
		if ($this->check('keyword', 'var'))      return $this->parseVarDecl();
		if ($this->check('keyword', 'let'))      return $this->parseVarDecl();
		if ($this->check('keyword', 'const'))    return $this->parseVarDecl();
		if ($this->check('keyword', 'return'))   return $this->parseReturn();
		if ($this->check('keyword', 'if'))       return $this->parseIf();
		if ($this->check('keyword', 'while'))    return $this->parseWhile();
		if ($this->check('keyword', 'for'))      return $this->parseFor();
		if ($this->check('keyword', 'break'))    return $this->parseBreakOrContinue('Break');
		if ($this->check('keyword', 'continue')) return $this->parseBreakOrContinue('Continue');
		if ($this->check('keyword', 'function')) {
			$this->error('関数はトップレベルにのみ定義できます');
		}
		if ($this->accept('op', ';')) {
			return array('type' => 'Empty', 'line' => 0, 'col' => 0);
		}
		return $this->parseExpressionStatement();
	}

	private function parseVarDecl($withSemicolon = TRUE) {
		$kw   = $this->next();
		$name = $this->expect('ident');
		$init = NULL;
		if ($this->accept('op', '=')) {
			$init = $this->parseExpression();
		} elseif ($kw['value'] === 'const') {
			$this->error('const は初期値が必要です', $kw);
		}
		if ($withSemicolon) $this->endStatement();
		return array('type' => 'VarDecl', 'kind' => $kw['value'],
			'name' => $name['value'], 'init' => $init) + self::at($kw);
	}

	private function parseReturn() {
		$kw = $this->next();
		$arg = NULL;
		if (! $this->check('op', ';') && ! $this->check('op', '}')) {
			$arg = $this->parseExpression();
		}
		$this->endStatement();
		return array('type' => 'Return', 'argument' => $arg) + self::at($kw);
	}

	private function parseBreakOrContinue($type) {
		$kw = $this->next();
		$this->endStatement();
		return array('type' => $type) + self::at($kw);
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
		return array('type' => 'While', 'test' => $test, 'body' => $body) + self::at($kw);
	}

	/** `for (init; test; update)` and `for (let x of expr)`. */
	private function parseFor() {
		$kw = $this->next();
		$this->expect('op', '(');

		// Look ahead for the for..of form: for (var|let|const IDENT of ...)
		$isDecl = $this->check('keyword', 'var') || $this->check('keyword', 'let') || $this->check('keyword', 'const');
		if ($isDecl && $this->peek(1)['type'] === 'ident' &&
			$this->peek(2)['type'] === 'ident' && $this->peek(2)['value'] === 'of') {
			$kind = $this->next(); // var / let / const
			$name = $this->next(); // loop variable
			$this->next();         // 'of'
			$subject = $this->parseExpression();
			$this->expect('op', ')');
			$body = $this->parseStatement();
			return array('type' => 'ForOf', 'kind' => $kind['value'], 'name' => $name['value'],
				'subject' => $subject, 'body' => $body) + self::at($kw);
		}

		$init = NULL;
		if (! $this->check('op', ';')) {
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
		return array('type' => 'For', 'init' => $init, 'test' => $test,
			'update' => $update, 'body' => $body) + self::at($kw);
	}

	private function parseExpressionStatement() {
		$expr = $this->parseExpression();
		$this->endStatement();
		return array('type' => 'ExprStmt', 'expression' => $expr) + self::at($expr);
	}

	private function endStatement() {
		if ($this->accept('op', ';')) return;
		// Allow a missing ';' before '}' or at end of file
		if ($this->check('op', '}') || $this->isEof()) return;
		$prev = $this->prev();
		$curr = $this->peek();
		if ($prev !== NULL && $curr !== NULL && $curr['line'] > $prev['line']) return;
		$this->error("';' が必要です");
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
			if ($left['type'] !== 'Identifier' &&
				$left['type'] !== 'Member' && $left['type'] !== 'Index') {
				$this->error('代入できない式です', $op);
			}
			$value = $this->parseAssignment();
			return array('type' => 'Assign', 'op' => $op['value'],
				'target' => $left, 'value' => $value) + self::at($op);
		}
		return $left;
	}

	private function parseConditional() {
		$test = $this->parseBinary(0);
		if (! $this->check('op', '?')) return $test;

		$q = $this->next();
		$then = $this->parseAssignment();
		$this->expect('op', ':');
		$else = $this->parseAssignment();
		return array('type' => 'Conditional', 'test' => $test,
			'then' => $then, 'else' => $else) + self::at($q);
	}

	private function parseBinary($minPrec) {
		$left = $this->parseUnary();
		while (TRUE) {
			$t = $this->peek();
			if ($t['type'] !== 'op' || ! isset(self::$precedence[$t['value']])) break;
			$prec = self::$precedence[$t['value']];
			if ($prec < $minPrec) break;
			$this->next();
			$right = $this->parseBinary($prec + 1);
			$left = array('type' => 'Binary', 'op' => $t['value'],
				'left' => $left, 'right' => $right) + self::at($t);
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
			$op  = $this->next();
			$arg = $this->parseUnary();
			return array('type' => 'Update', 'op' => $op['value'], 'prefix' => TRUE,
				'argument' => $arg) + self::at($op);
		}
		return $this->parsePostfix();
	}

	private function parsePostfix() {
		$expr = $this->parsePrimary();
		while (TRUE) {
			if ($this->check('op', '.')) {
				$dot  = $this->next();
				$prop = $this->expect('ident');
				$expr = array('type' => 'Member', 'object' => $expr,
					'property' => $prop['value']) + self::at($dot);
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
				$par  = $this->next();
				$args = array();
				if (! $this->check('op', ')')) {
					do {
						if ($this->check('op', ')')) break; // trailing comma
						$args[] = $this->parseExpression();
					} while ($this->accept('op', ','));
				}
				$this->expect('op', ')');
				$expr = array('type' => 'Call', 'callee' => $expr, 'arguments' => $args) + self::at($par);
				continue;
			}
			if ($this->check('op', '++') || $this->check('op', '--')) {
				$op = $this->next();
				$expr = array('type' => 'Update', 'op' => $op['value'], 'prefix' => FALSE,
					'argument' => $expr) + self::at($op);
				continue;
			}
			break;
		}
		return $expr;
	}

	private function parsePrimary() {
		$t = $this->peek();

		if ($this->isArrowAhead()) return $this->parseArrow();

		if ($this->accept('op', '(')) {
			$expr = $this->parseExpression();
			$this->expect('op', ')');
			return $expr;
		}
		if ($this->check('op', '[')) {
			$br = $this->next();
			$elements = array();
			if (! $this->check('op', ']')) {
				do {
					if ($this->check('op', ']')) break; // trailing comma
					$elements[] = $this->parseExpression();
				} while ($this->accept('op', ','));
			}
			$this->expect('op', ']');
			return array('type' => 'ArrayLit', 'elements' => $elements) + self::at($br);
		}
		if ($this->check('op', '{')) {
			return $this->parseObjectLit();
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

		$got = $t['type'] === 'eof' ? 'ファイル終端' : "'" . $t['value'] . "'";
		$this->error('式が必要ですが ' . $got . ' がありました', $t);
	}

	/** Is an arrow function starting here? `x => ...` or `(a, b) => ...` */
	private function isArrowAhead() {
		if ($this->check('ident')) {
			$after = $this->peek(1);
			return $after['type'] === 'op' && $after['value'] === '=>';
		}
		if (! $this->check('op', '(')) return FALSE;

		// Cached per position: without it, nested parentheses would each
		// rescan the rest of the token stream.
		if (isset($this->arrowAhead[$this->pos])) return $this->arrowAhead[$this->pos];

		// Scan to the matching ')' - the token after it decides
		$depth = 0;
		for ($i = 0; ; $i++) {
			$tok = $this->peek($i);
			if ($tok['type'] === 'eof') return $this->arrowAhead[$this->pos] = FALSE;
			if ($tok['type'] !== 'op') continue;
			if ($tok['value'] === '(') $depth++;
			elseif ($tok['value'] === ')') {
				if (--$depth > 0) continue;
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
		$start  = $this->peek();
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
			$body = array('type' => 'Block', 'body' => array(
				array('type' => 'Return', 'argument' => $expr) + self::at($start),
			)) + self::at($start);
		}

		return array('type' => 'Function', 'name' => '',
			'params' => $params, 'body' => $body) + self::at($start);
	}

	/** Comma separated parameter names, up to but not including $closeOp. */
	private function parseParams($closeOp) {
		$params = array();
		if ($this->check('op', $closeOp)) return $params;
		do {
			if ($this->check('op', $closeOp)) break; // trailing comma
			$p = $this->expect('ident');
			$params[] = $p['value'];
		} while ($this->accept('op', ','));
		return $params;
	}

	/** `{ key: value, "key": value }` */
	private function parseObjectLit() {
		$open = $this->next();
		$props = array();
		if (! $this->check('op', '}')) {
			do {
				if ($this->check('op', '}')) break; // trailing comma

				$k = $this->peek();
				if ($k['type'] === 'ident' || $k['type'] === 'keyword' || $k['type'] === 'str') {
					$this->next();
					$key = (string)$k['value'];
				} elseif ($k['type'] === 'num') {
					$this->next();
					$key = Pkript_Interpreter::toStringValue($k['value']);
				} else {
					$this->error('プロパティ名が必要です', $k);
				}

				$this->expect('op', ':');
				$props[] = array('key' => $key, 'value' => $this->parseExpression());
			} while ($this->accept('op', ','));
		}
		$this->expect('op', '}');
		return array('type' => 'ObjectLit', 'properties' => $props) + self::at($open);
	}
}

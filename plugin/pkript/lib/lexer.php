<?php
// $Id: lexer.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - lexer
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// Lexer

class Pkript_Lexer {
	private $src;
	private $len;
	private $pos = 0;
	private $line = 1;
	private $col = 1;
	private $script;

	private static $keywords = array(
		'function' => 1,
		'return' => 1,
		'var' => 1,
		'let' => 1,
		'const' => 1,
		'true' => 1,
		'false' => 1,
		'null' => 1,
		'if' => 1,
		'else' => 1,
		'while' => 1,
		'do' => 1,
		'for' => 1,
		'break' => 1,
		'continue' => 1,
		'switch' => 1,
		'case' => 1,
		'default' => 1,
		'try' => 1,
		'catch' => 1,
		// NOTE: 'of' (for..of) stays a normal identifier so scripts can use it
		// as a variable name; the parser recognises it by position.
	);

	const IDENT_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$';
	const DIGITS = '0123456789';
	const SPACE_CHARS = " 	
";

	// Longest first
	private static $operators = array(
		'===',
		'!==',
		'==',
		'!=',
		'<=',
		'>=',
		'&&',
		'||',
		'++',
		'--',
		'=>',
		'+=',
		'-=',
		'*=',
		'/=',
		'%=',
		'+',
		'-',
		'*',
		'/',
		'%',
		'=',
		'!',
		'<',
		'>',
		'(',
		')',
		'{',
		'}',
		'[',
		']',
		',',
		';',
		'.',
		':',
		'?',
	);

	public function __construct($src, $script, $line = 1, $col = 1) {
		$this->line = $line;
		$this->col = $col;
		// Normalize newlines and strip a UTF-8 BOM
		$src = str_replace(array("\r\n", "\r"), "\n", $src);
		if (substr($src, 0, 3) === "\xEF\xBB\xBF")
			$src = substr($src, 3);
		$this->src = $src;
		$this->len = strlen($src);
		$this->script = $script;
	}

	public function tokenize() {
		$tokens = array();
		while (TRUE) {
			$this->skipWhitespaceAndComments();
			if ($this->pos >= $this->len)
				break;

			$line = $this->line;
			$col = $this->col;
			$ch = $this->src[$this->pos];

			// '<' is either the operator or the start of JSX markup, and '/'
			// is either division or a regular expression. Only the token
			// before it can tell either pair apart. See atExpressionStart().
			if ($ch === '<' && PKRIPT_JSX && $this->atExpressionStart($tokens)) {
				$tokens[] = $this->token('jsx', $this->readJsxElement(), $line, $col);
				continue;
			}
			if ($ch === '/' && PKRIPT_REGEX && $this->atExpressionStart($tokens)) {
				$tokens[] = $this->token('regex', $this->readRegex(), $line, $col);
				continue;
			}

			if ($ch === '"' || $ch === "'") {
				$tokens[] = $this->token('str', $this->readString($ch), $line, $col);
				continue;
			}
			if ($ch === "`") {
				$tokens[] = $this->token('template', $this->readTemplate(), $line, $col);
				continue;
			}
			if (
				ctype_digit($ch) ||
				($ch === '.' && $this->pos + 1 < $this->len && ctype_digit($this->src[$this->pos + 1]))
			) {
				$tokens[] = $this->token('num', $this->readNumber(), $line, $col);
				continue;
			}
			if (ctype_alpha($ch) || $ch === '_' || $ch === '$') {
				$word = $this->readIdentifier();
				$type = isset(self::$keywords[$word]) ? 'keyword' : 'ident';
				$tokens[] = $this->token($type, $word, $line, $col);
				continue;
			}

			$op = $this->readOperator();
			if ($op === NULL) {
				throw new Pkript_Error(
					'Invalid character ' . $this->describeChar($ch),
					$this->script,
					$line,
					$col
				);
			}
			$tokens[] = $this->token('op', $op, $line, $col);
		}
		$tokens[] = $this->token('eof', '', $this->line, $this->col);
		return $tokens;
	}

	private function token($type, $value, $line, $col) {
		return array('type' => $type, 'value' => $value, 'line' => $line, 'col' => $col);
	}

	/**
	 * Move over $n bytes, keeping line and column right. Counting the newlines
	 * in one go beats stepping through the bytes in PHP.
	 */
	private function advance($n = 1) {
		if ($this->pos + $n > $this->len) $n = $this->len - $this->pos;
		if ($n <= 0) return;

		$chunk = substr($this->src, $this->pos, $n);
		$lines = substr_count($chunk, "\n");
		if ($lines === 0) {
			$this->col += $n;
		} else {
			$this->line += $lines;
			$this->col = $n - strrpos($chunk, "\n");
		}
		$this->pos += $n;
	}

	/** Operators that start with each character, longest first. */
	private static function operatorsByFirst() {
		static $index = NULL;
		if ($index !== NULL) return $index;

		$index = array();
		foreach (self::$operators as $op) {
			$index[$op[0]][] = $op;
		}
		return $index;
	}

	private function skipWhitespaceAndComments() {
		while ($this->pos < $this->len) {
			$run = strspn($this->src, self::SPACE_CHARS, $this->pos);
			if ($run > 0) {
				$this->advance($run);
				continue;
			}
			if ($this->src[$this->pos] !== '/' || $this->pos + 1 >= $this->len) break;

			$next = $this->src[$this->pos + 1];
			if ($next === '/') {
				$end = strpos($this->src, "\n", $this->pos);
				$this->advance(($end === FALSE ? $this->len : $end) - $this->pos);
				continue;
			}
			if ($next === '*') {
				$end = strpos($this->src, '*/', $this->pos + 2);
				if ($end === FALSE) {
					throw new Pkript_Error(
						'Unterminated comment',
						$this->script,
						$this->line,
						$this->col
					);
				}
				$this->advance($end + 2 - $this->pos);
				continue;
			}
			break;
		}
	}

	private function readString($quote) {
		$line = $this->line;
		$col = $this->col;
		$this->advance(); // opening quote
		// Everything that ends a plain run; none of them is a newline, so a
		// run never moves the line counter
		$stops = $quote . "\\" . "\n";
		$out = '';
		while (TRUE) {
			if ($this->pos >= $this->len) {
				throw new Pkript_Error(
					'Unterminated string',
					$this->script,
					$line,
					$col
				);
			}
			$plain = strcspn($this->src, $stops, $this->pos);
			if ($plain > 0) {
				$out .= substr($this->src, $this->pos, $plain);
				$this->pos += $plain;
				$this->col += $plain;
				continue;
			}

			$ch = $this->src[$this->pos];
			if ($ch === "\n") {
				throw new Pkript_Error(
					'A string may not contain a newline',
					$this->script,
					$this->line,
					$this->col
				);
			}
			if ($ch === $quote) {
				$this->advance();
				break;
			}
			if ($ch === '\\') {
				$out .= $this->readEscape();
				continue;
			}
			$out .= $ch;
			$this->advance();
		}
		return $out;
	}

	/** One backslash escape, from the backslash. */
	private function readEscape() {
		$this->advance();
		if ($this->pos >= $this->len)
			return '';

		static $simple = NULL;
		if ($simple === NULL) {
			$simple = array(
				'n' => "\n",
				't' => "\t",
				'r' => "\r",
				'\\' => '\\',
				'"' => '"',
				"'" => "'",
			);
		}

		$esc = $this->src[$this->pos];
		if (!isset($simple[$esc])) {
			throw new Pkript_Error(
				'Invalid escape \\' . $esc,
				$this->script,
				$this->line,
				$this->col
			);
		}
		$this->advance();
		return $simple[$esc];
	}

	/**
	 * A template literal, as a list of parts: either a piece of text or the
	 * tokens of a `${...}` expression. Newlines are allowed, and the pieces
	 * take the same escapes a normal string does.
	 *
	 * @return array of array('type' => 'str'|'expr', ...)
	 */
	private function readTemplate() {
		$openLine = $this->line;
		$openCol = $this->col;
		$this->advance();   // opening quote

		$parts = array();
		$text = '';
		while (TRUE) {
			if ($this->pos >= $this->len) {
				throw new Pkript_Error(
					'Unterminated template literal',
					$this->script,
					$openLine,
					$openCol
				);
			}

			$ch = $this->src[$this->pos];
			if ($ch === "`") {
				$this->advance();
				break;
			}
			if ($ch === '\\') {
				$text .= $this->readTemplateEscape();
				continue;
			}
			if ($ch === '$' && $this->pos + 1 < $this->len && $this->src[$this->pos + 1] === '{') {
				if ($text !== '') {
					$parts[] = array('type' => 'str', 'value' => $text);
					$text = '';
				}
				$parts[] = $this->readTemplateExpression();
				continue;
			}

			$text .= $ch;
			$this->advance();
		}

		if ($text !== '') $parts[] = array('type' => 'str', 'value' => $text);
		return $parts;
	}

	/** The escapes a string takes, plus the two a template adds. */
	private function readTemplateEscape() {
		$this->advance();
		if ($this->pos >= $this->len) return '';

		$esc = $this->src[$this->pos];
		if ($esc === "`" || $esc === '$') {
			$this->advance();
			return $esc;
		}
		$this->pos--;   // hand the backslash back
		$this->col--;
		return $this->readEscape();
	}

	/** `${ expression }`, tokenized on its own so the parser can read it. */
	private function readTemplateExpression() {
		$this->advance(2);   // past '${'
		$line = $this->line;
		$col = $this->col;
		$start = $this->pos;

		$end = $this->findTemplateExpressionEnd();
		$source = substr($this->src, $start, $end - $start);
		$this->advance($end - $start);
		$this->advance();    // closing brace

		if (trim($source) === '') {
			throw new Pkript_Error('Empty ${} in a template literal', $this->script, $line, $col);
		}

		$inner = new Pkript_Lexer($source, $this->script, $line, $col);
		return array('type' => 'expr', 'tokens' => $inner->tokenize());
	}

	/**
	 * Position of the brace that closes the current `${`. Braces inside
	 * strings, templates and comments do not count.
	 */
	private function findTemplateExpressionEnd($what = '${}') {
		$depth = 0;
		$i = $this->pos;
		while ($i < $this->len) {
			$ch = $this->src[$i];

			if ($ch === '"' || $ch === "'" || $ch === "`") {
				$i = $this->skipQuoted($i, $ch);
				continue;
			}
			if ($ch === '/' && $i + 1 < $this->len) {
				$next = $this->src[$i + 1];
				if ($next === '/') {
					$nl = strpos($this->src, "\n", $i);
					$i = ($nl === FALSE) ? $this->len : $nl;
					continue;
				}
				if ($next === '*') {
					$close = strpos($this->src, '*/', $i + 2);
					$i = ($close === FALSE) ? $this->len : $close + 2;
					continue;
				}
			}
			if ($ch === '{') $depth++;
			if ($ch === '}') {
				if ($depth === 0) return $i;
				$depth--;
			}
			$i++;
		}
		throw new Pkript_Error(
			$what . ' is not closed',
			$this->script,
			$this->line,
			$this->col
		);
	}

	/** Byte after the quoted run starting at $i. */
	private function skipQuoted($i, $quote) {
		$i++;
		while ($i < $this->len) {
			$ch = $this->src[$i];
			if ($ch === '\\') { $i += 2; continue; }
			if ($ch === $quote) return $i + 1;
			$i++;
		}
		return $this->len;
	}

	// One number, anchored at the current position. The three based forms
	// come first so 0x1 is not read as 0 followed by x1, and every run of
	// digits is written the same way: a digit, then optionally-underscored
	// digits, which is what makes 1_000 legal and 1__0, _1 and 1_ not.
	const NUMBER_RE = '/\\G(?:
		0[xX](?P<hex>[0-9a-fA-F](?:_?[0-9a-fA-F])*)
		|0[bB](?P<bin>[01](?:_?[01])*)
		|0[oO](?P<oct>[0-7](?:_?[0-7])*)
		|(?P<dec>
			(?:[0-9](?:_?[0-9])*(?:\\.[0-9](?:_?[0-9])*)?|\\.[0-9](?:_?[0-9])*)
			(?:[eE][+-]?[0-9](?:_?[0-9])*)?
		)
	)/x';

	/**
	 * Decimal, hex, binary and octal, with `_` allowed between digits.
	 *
	 * The value is produced by PHP's own string-to-number conversion, which
	 * gives an int when one fits and a float when it does not - the same
	 * place JavaScript stops being exact. A form with a '.' or an exponent
	 * stays a float even when it lands on a whole number, so 1.0 and 1e3 do
	 * not silently become integers.
	 */
	private function readNumber() {
		if (!preg_match(self::NUMBER_RE, $this->src, $m, 0, $this->pos)) {
			throw new Pkript_Error('Invalid number literal',
				$this->script, $this->line, $this->col);
		}

		$text = $m[0];
		$this->pos += strlen($text);
		$this->col += strlen($text);

		// 123abc and 0x are both this: something a number may not run into
		if ($this->pos < $this->len &&
			strpos(self::IDENT_CHARS, $this->src[$this->pos]) !== FALSE) {
			throw new Pkript_Error(
				'Unexpected ' . $this->describeChar($this->src[$this->pos]) .
				' after a number literal',
				$this->script, $this->line, $this->col);
		}

		foreach (array('hex' => 16, 'bin' => 2, 'oct' => 8) as $key => $base) {
			if (isset($m[$key]) && $m[$key] !== '') {
				// base_convert() loses precision without warning; these do not
				$digits = str_replace('_', '', $m[$key]);
				return $base === 16 ? hexdec($digits)
					: ($base === 2 ? bindec($digits) : octdec($digits));
			}
		}

		$dec = str_replace('_', '', $m[0]);
		// '+ 0' picks int or float the way PHP reads the literal itself
		return $dec + 0;
	}

	private function readIdentifier() {
		$n = strspn($this->src, self::IDENT_CHARS, $this->pos);
		$word = substr($this->src, $this->pos, $n);
		// No newline can be in there, so the column moves by the length
		$this->pos += $n;
		$this->col += $n;
		return $word;
	}

	private function readOperator() {
		$index = self::operatorsByFirst();
		$first = $this->src[$this->pos];
		if (!isset($index[$first])) return NULL;

		foreach ($index[$first] as $op) {
			$n = strlen($op);
			if ($n === 1 || substr($this->src, $this->pos, $n) === $op) {
				// An operator never contains a newline
				$this->pos += $n;
				$this->col += $n;
				return $op;
			}
		}
		return NULL;
	}

	private function describeChar($ch) {
		$code = ord($ch);
		if ($code < 0x20 || $code >= 0x7F)
			return '(0x' . dechex($code) . ')';
		return "'" . $ch . "'";
	}

	/////////////////////////////////////////////
	// JSX

	/**
	 * Can markup start here? Only where an expression may start: after an
	 * operator or a keyword (return, =, (, comma, ?, :, ...), or at the very
	 * beginning. After a value - an identifier, a number, a closing bracket -
	 * a '<' is the comparison it has always been, so a < b keeps working.
	 */
	// Tags with no content, so <br> needs no closing tag and <br /> is the
	// same element written the other way
	private static $voidTags = array(
		'area' => 1, 'base' => 1, 'br' => 1, 'col' => 1, 'embed' => 1,
		'hr' => 1, 'img' => 1, 'input' => 1, 'link' => 1, 'meta' => 1,
		'param' => 1, 'source' => 1, 'track' => 1, 'wbr' => 1,
	);

	public static function isVoidTag($tag) {
		return isset(self::$voidTags[$tag]);
	}

	/**
	 * Could an expression start here? Only after an operator or a keyword
	 * (return, =, (, comma, ?, :, ...), or at the very beginning. After a
	 * value - an identifier, a number, a closing bracket - what follows is an
	 * operator, so `a < b` stays a comparison and `a / b` stays a division.
	 */
	private function atExpressionStart($tokens) {
		if (empty($tokens))
			return TRUE;

		$prev = $tokens[count($tokens) - 1];
		if ($prev['type'] === 'op') {
			return !in_array($prev['value'],
				array(')', ']', '}', '++', '--'), TRUE);
		}
		if ($prev['type'] === 'keyword') {
			return $prev['value'] !== 'true' && $prev['value'] !== 'false' &&
				$prev['value'] !== 'null';
		}
		return FALSE;
	}

	/**
	 * One element, from its '<'. Elements nest, so this recurses; the parser
	 * gets a tree and never sees the angle brackets.
	 *
	 * @return array tag / attrs / children / void
	 */
	private function readJsxElement() {
		$line = $this->line;
		$col = $this->col;
		$this->advance();   // '<'

		// '<>' opens a fragment: children with no tag of their own
		$tag = '';
		if ($this->pos < $this->len && $this->src[$this->pos] !== '>') {
			$tag = $this->readJsxName();
			if ($tag === '')
				$this->jsxError('A tag name is required');
		}

		$attrs = ($tag === '') ? array() : $this->readJsxAttributes($line, $col);

		$void = FALSE;
		if ($tag !== '' && $this->pos < $this->len && $this->src[$this->pos] === '/') {
			$this->advance();
			$void = TRUE;
		}
		if ($this->pos >= $this->len || $this->src[$this->pos] !== '>')
			$this->jsxError('Missing > on a JSX tag');
		$this->advance();

		if (self::isVoidTag($tag))
			$void = TRUE;
		$children = $void ? array() : $this->readJsxChildren($tag, $line, $col);

		return array(
			'tag' => $tag,
			'attrs' => $attrs,
			'children' => $children,
			'void' => $void,
			'line' => $line,
			'col' => $col,
		);
	}

	/** Attributes up to the '/' or '>' that ends the opening tag. */
	private function readJsxAttributes($line, $col) {
		$attrs = array();
		while (TRUE) {
			$this->skipJsxSpaces();
			if ($this->pos >= $this->len) {
				throw new Pkript_Error('Unterminated JSX tag',
					$this->script, $line, $col);
			}

			$ch = $this->src[$this->pos];
			if ($ch === '>' || $ch === '/')
				return $attrs;
			if ($ch === '{')
				$this->jsxError('Spread attributes are not supported');

			$nameLine = $this->line;
			$nameCol = $this->col;
			$name = $this->readJsxAttrName();
			if ($name === '')
				$this->jsxError('An attribute name is required');

			$value = NULL;
			$this->skipJsxSpaces();
			if ($this->pos < $this->len && $this->src[$this->pos] === '=') {
				$this->advance();
				$this->skipJsxSpaces();
				$value = $this->readJsxAttrValue();
			}
			$attrs[] = array(
				'name' => $name,
				'value' => $value,
				'line' => $nameLine,
				'col' => $nameCol,
			);
		}
	}

	/** A quoted string or a braced expression. */
	private function readJsxAttrValue() {
		if ($this->pos >= $this->len)
			$this->jsxError('Missing attribute value');

		$ch = $this->src[$this->pos];
		if ($ch === '"' || $ch === "'")
			return array('type' => 'str', 'value' => $this->readJsxAttrString($ch));
		if ($ch === '{') {
			$expr = $this->readJsxExpression();
			if ($expr === NULL)
				$this->jsxError('Empty {} in an attribute');
			return $expr;
		}
		$this->jsxError('An attribute value must be a string or {expression}');
	}

	/**
	 * An attribute string, taken literally: JSX has no backslash escapes
	 * there, and a written-out character reference survives into the output.
	 */
	private function readJsxAttrString($quote) {
		$line = $this->line;
		$col = $this->col;
		$this->advance();   // opening quote

		$n = strcspn($this->src, $quote, $this->pos);
		if ($this->pos + $n >= $this->len) {
			throw new Pkript_Error('Unterminated attribute value',
				$this->script, $line, $col);
		}
		$text = substr($this->src, $this->pos, $n);
		$this->advance($n);
		$this->advance();   // closing quote
		return $text;
	}

	/**
	 * Children up to the matching closing tag: runs of text, braced
	 * expressions and nested elements, in the order written.
	 */
	private function readJsxChildren($tag, $line, $col) {
		$children = array();
		$text = '';
		while (TRUE) {
			if ($this->pos >= $this->len) {
				throw new Pkript_Error('<' . $tag . '> is not closed',
					$this->script, $line, $col);
			}

			$ch = $this->src[$this->pos];
			if ($ch === '<') {
				$this->flushJsxText($children, $text);
				if ($this->pos + 1 < $this->len && $this->src[$this->pos + 1] === '/') {
					$this->readJsxClosingTag($tag);
					return $children;
				}
				$children[] = array(
					'type' => 'element',
					'node' => $this->readJsxElement(),
				);
				continue;
			}
			if ($ch === '{') {
				$this->flushJsxText($children, $text);
				$expr = $this->readJsxExpression();
				// An empty pair of braces holds nothing, as a comment does
				if ($expr !== NULL)
					$children[] = $expr;
				continue;
			}

			$n = strcspn($this->src, '<{', $this->pos);
			if ($n === 0) $n = 1;
			$text .= substr($this->src, $this->pos, $n);
			$this->advance($n);
		}
	}

	/** A closing tag, which has to name the element it closes. */
	private function readJsxClosingTag($tag) {
		$line = $this->line;
		$col = $this->col;
		$this->advance(2);   // '</'
		$this->skipJsxSpaces();

		$close = '';
		if ($this->pos < $this->len && $this->src[$this->pos] !== '>')
			$close = $this->readJsxName();

		$this->skipJsxSpaces();
		if ($this->pos >= $this->len || $this->src[$this->pos] !== '>') {
			throw new Pkript_Error('Missing > on a closing tag',
				$this->script, $line, $col);
		}
		$this->advance();

		if ($close !== $tag) {
			throw new Pkript_Error(
				'Closing tag </' . $close . '> does not match <' . $tag . '>',
				$this->script, $line, $col);
		}
	}

	/** Text collected so far becomes a child, if anything is left of it. */
	private function flushJsxText(&$children, &$text) {
		$value = self::normalizeJsxText($text);
		$text = '';
		if ($value !== '')
			$children[] = array('type' => 'text', 'value' => $value);
	}

	/**
	 * How JSX reads indented markup: each line is trimmed, blank ones are
	 * dropped, and what is left is joined with single spaces. Text written on
	 * one line is kept exactly as it was, spaces included.
	 */
	private static function normalizeJsxText($text) {
		if (strpos($text, "\n") === FALSE)
			return $text;

		$out = array();
		foreach (explode("\n", $text) as $piece) {
			$piece = trim($piece, " \t");
			if ($piece !== '')
				$out[] = $piece;
		}
		return implode(' ', $out);
	}

	/** A braced expression, tokenized on its own the way a template one is. */
	private function readJsxExpression() {
		$this->advance();   // '{'
		$line = $this->line;
		$col = $this->col;
		$start = $this->pos;

		$end = $this->findTemplateExpressionEnd('{}');
		$source = substr($this->src, $start, $end - $start);
		$this->advance($end - $start);
		$this->advance();   // closing brace

		if (trim($source) === '')
			return NULL;

		$inner = new Pkript_Lexer($source, $this->script, $line, $col);
		return array('type' => 'expr', 'tokens' => $inner->tokenize());
	}

	const JSX_NAME_CHARS =
		'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-';
	const JSX_ATTR_CHARS =
		'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_:.';

	private function readJsxName() {
		if ($this->pos >= $this->len || !ctype_alpha($this->src[$this->pos]))
			return '';
		return $this->readJsxWord(self::JSX_NAME_CHARS);
	}

	private function readJsxAttrName() {
		$ch = $this->src[$this->pos];
		if (!ctype_alpha($ch) && $ch !== '_')
			return '';
		return $this->readJsxWord(self::JSX_ATTR_CHARS);
	}

	private function readJsxWord($chars) {
		$n = strspn($this->src, $chars, $this->pos);
		$word = substr($this->src, $this->pos, $n);
		// No newline can be in there, so the column moves by the length
		$this->pos += $n;
		$this->col += $n;
		return $word;
	}

	private function skipJsxSpaces() {
		$run = strspn($this->src, self::SPACE_CHARS, $this->pos);
		if ($run > 0)
			$this->advance($run);
	}

	/////////////////////////////////////////////
	// Regular expressions

	/**
	 * `/pattern/flags`, from the opening slash.
	 *
	 * The pattern is kept exactly as written and handed to the parser, which
	 * checks it. An unescaped '/' ends the literal, so the source can never
	 * contain one - which is what lets the runtime wrap it in '/' delimiters
	 * later without escaping anything.
	 *
	 * @return array source and flags
	 */
	private function readRegex() {
		$line = $this->line;
		$col = $this->col;
		$this->advance();   // opening '/'

		$source = '';
		$inClass = FALSE;   // '/' inside [...] is a literal slash, as in JS
		while (TRUE) {
			if ($this->pos >= $this->len || $this->src[$this->pos] === "\n") {
				throw new Pkript_Error('Unterminated regular expression',
					$this->script, $line, $col);
			}

			$ch = $this->src[$this->pos];
			if ($ch === '\\') {
				if ($this->pos + 1 >= $this->len || $this->src[$this->pos + 1] === "\n") {
					throw new Pkript_Error('Unterminated regular expression',
						$this->script, $line, $col);
				}
				$source .= substr($this->src, $this->pos, 2);
				$this->advance(2);
				continue;
			}
			if ($ch === '[') $inClass = TRUE;
			if ($ch === ']') $inClass = FALSE;
			if ($ch === '/' && !$inClass) {
				$this->advance();
				break;
			}
			$source .= $ch;
			$this->advance();
		}

		if ($source === '') {
			throw new Pkript_Error('Empty regular expression', $this->script, $line, $col);
		}

		// Flags run right up against the closing slash
		$flags = '';
		if ($this->pos < $this->len &&
			strpos(self::IDENT_CHARS, $this->src[$this->pos]) !== FALSE) {
			$flags = $this->readIdentifier();
		}
		return array('source' => $source, 'flags' => $flags,
			'line' => $line, 'col' => $col);
	}

	private function jsxError($message) {
		throw new Pkript_Error($message, $this->script, $this->line, $this->col);
	}
}

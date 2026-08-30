<?php
// $Id: lexer.php,v 0.3 2026/08/30 00:00:00 Pitan Exp $

/**
* Pkript runtime - lexer
*
* @link https://blog.pitan76.net/?Pkript
* @author Pitan
* @license https://opensource.org/license/mit MIT
*/

// Loaded by plugin/pkript.inc.php; not meant to be requested directly.
if (! defined('PKRIPT_RUNTIME')) exit;

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
		'function' => 1, 'return' => 1, 'var' => 1, 'let' => 1, 'const' => 1,
		'true' => 1, 'false' => 1, 'null' => 1,
		'if' => 1, 'else' => 1, 'while' => 1, 'for' => 1,
		'break' => 1, 'continue' => 1,
		// NOTE: 'of' (for..of) stays a normal identifier so scripts can use it
		// as a variable name; the parser recognises it by position.
	);

	// Longest first
	private static $operators = array(
		'===', '!==',
		'==', '!=', '<=', '>=', '&&', '||', '++', '--', '=>',
		'+=', '-=', '*=', '/=', '%=',
		'+', '-', '*', '/', '%', '=', '!', '<', '>',
		'(', ')', '{', '}', '[', ']', ',', ';', '.', ':', '?',
	);

	public function __construct($src, $script) {
		// Normalize newlines and strip a UTF-8 BOM
		$src = str_replace(array("\r\n", "\r"), "\n", $src);
		if (substr($src, 0, 3) === "\xEF\xBB\xBF") $src = substr($src, 3);
		$this->src    = $src;
		$this->len    = strlen($src);
		$this->script = $script;
	}

	public function tokenize() {
		$tokens = array();
		while (TRUE) {
			$this->skipWhitespaceAndComments();
			if ($this->pos >= $this->len) break;

			$line = $this->line;
			$col  = $this->col;
			$ch   = $this->src[$this->pos];

			if ($ch === '"' || $ch === "'") {
				$tokens[] = $this->token('str', $this->readString($ch), $line, $col);
				continue;
			}
			if (ctype_digit($ch) ||
				($ch === '.' && $this->pos + 1 < $this->len && ctype_digit($this->src[$this->pos + 1]))) {
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
					'不正な文字 ' . $this->describeChar($ch), $this->script, $line, $col);
			}
			$tokens[] = $this->token('op', $op, $line, $col);
		}
		$tokens[] = $this->token('eof', '', $this->line, $this->col);
		return $tokens;
	}

	private function token($type, $value, $line, $col) {
		return array('type' => $type, 'value' => $value, 'line' => $line, 'col' => $col);
	}

	private function advance($n = 1) {
		for ($i = 0; $i < $n && $this->pos < $this->len; $i++) {
			if ($this->src[$this->pos] === "\n") {
				$this->line++;
				$this->col = 1;
			} else {
				$this->col++;
			}
			$this->pos++;
		}
	}

	private function skipWhitespaceAndComments() {
		while ($this->pos < $this->len) {
			$ch = $this->src[$this->pos];
			if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\v" || $ch === "\f") {
				$this->advance();
				continue;
			}
			if ($ch === '/' && $this->pos + 1 < $this->len) {
				$next = $this->src[$this->pos + 1];
				if ($next === '/') {
					while ($this->pos < $this->len && $this->src[$this->pos] !== "\n") $this->advance();
					continue;
				}
				if ($next === '*') {
					$startLine = $this->line;
					$startCol  = $this->col;
					$this->advance(2);
					while (TRUE) {
						if ($this->pos + 1 >= $this->len) {
							throw new Pkript_Error(
								'コメントが閉じられていません', $this->script, $startLine, $startCol);
						}
						if ($this->src[$this->pos] === '*' && $this->src[$this->pos + 1] === '/') {
							$this->advance(2);
							break;
						}
						$this->advance();
					}
					continue;
				}
			}
			break;
		}
	}

	private function readString($quote) {
		$line = $this->line;
		$col  = $this->col;
		$this->advance(); // opening quote
		$out = '';
		while (TRUE) {
			if ($this->pos >= $this->len) {
				throw new Pkript_Error(
					'文字列が閉じられていません', $this->script, $line, $col);
			}
			$ch = $this->src[$this->pos];
			if ($ch === "\n") {
				throw new Pkript_Error(
					'文字列内で改行はできません', $this->script, $this->line, $this->col);
			}
			if ($ch === $quote) {
				$this->advance();
				break;
			}
			if ($ch === '\\') {
				$this->advance();
				if ($this->pos >= $this->len) continue;
				$esc = $this->src[$this->pos];
				switch ($esc) {
					case 'n':  $out .= "\n"; break;
					case 't':  $out .= "\t"; break;
					case 'r':  $out .= "\r"; break;
					case '\\': $out .= '\\'; break;
					case '"':  $out .= '"';  break;
					case "'":  $out .= "'";  break;
					default:
						throw new Pkript_Error(
							'不明なエスケープ \\' . $esc, $this->script, $this->line, $this->col);
				}
				$this->advance();
				continue;
			}
			$out .= $ch;
			$this->advance();
		}
		return $out;
	}

	private function readNumber() {
		$start = $this->pos;
		$seenDot = FALSE;
		while ($this->pos < $this->len) {
			$ch = $this->src[$this->pos];
			if (ctype_digit($ch)) {
				$this->advance();
			} elseif ($ch === '.' && ! $seenDot &&
				$this->pos + 1 < $this->len && ctype_digit($this->src[$this->pos + 1])) {
				$seenDot = TRUE;
				$this->advance();
			} else {
				break;
			}
		}
		$text = substr($this->src, $start, $this->pos - $start);
		return $seenDot ? (float)$text : (int)$text;
	}

	private function readIdentifier() {
		$start = $this->pos;
		while ($this->pos < $this->len) {
			$ch = $this->src[$this->pos];
			if (ctype_alnum($ch) || $ch === '_' || $ch === '$') {
				$this->advance();
			} else {
				break;
			}
		}
		return substr($this->src, $start, $this->pos - $start);
	}

	private function readOperator() {
		foreach (self::$operators as $op) {
			$n = strlen($op);
			if (substr($this->src, $this->pos, $n) === $op) {
				$this->advance($n);
				return $op;
			}
		}
		return NULL;
	}

	private function describeChar($ch) {
		$code = ord($ch);
		if ($code < 0x20 || $code >= 0x7F) return '(0x' . dechex($code) . ')';
		return "'" . $ch . "'";
	}
}

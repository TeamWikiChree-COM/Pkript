<?php
// $Id: date.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - date namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Std_Date extends Pkript_Std_Module {
	// Letters date() may be asked for. Everything else in a format string is
	// literal text, so a script cannot reach for the server's timezone name
	// or locale and get output that changes with the host.
	const LETTERS = 'YymndjHGhgisDlNwMFaAUt L';

	const MAX_FORMAT = 64;

	public static function members() {
		return array('format', 'now');
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'now':
				return self::now();
			case 'format':
				return $this->format(
					(int) $this->numArg($args, 0, $node, self::now()),
					$this->arg($args, 1, NULL),
					$node
				);
		}
	}

	/**
	 * PukiWiki keeps times as "seconds since the epoch, minus the server's
	 * offset", and adds ZONETIME back when it prints one. wiki.time() and
	 * date.now() both hand out that value, and format() reads it, so the
	 * three agree with each other and with PukiWiki's own timestamps.
	 */
	public static function now() {
		return pkript_now();
	}

	private static function zoneTime() {
		return pkript_zone_offset();
	}

	/**
	 * Without a format, exactly what PukiWiki's format_date() prints, so a
	 * script rendering a timestamp looks like the rest of the wiki. With a
	 * format string, the date() letters listed above.
	 *
	 * format_date()'s second argument is a boolean - wrap it in parentheses -
	 * and that spelling keeps working here.
	 */
	private function format($time, $format, $node) {
		if ($format === NULL || $format === '' || is_bool($format))
			return self::defaultFormat($time, $format === TRUE);

		$format = Pkript_Interpreter::stripHtmlMarks(
			Pkript_Interpreter::toStringValue($format));
		if (strlen($format) > self::MAX_FORMAT) {
			$this->rt->fail('Date format too long (limit ' .
				self::MAX_FORMAT . ' bytes)', $node);
		}
		return self::expand($format, $time + self::zoneTime());
	}

	private static function defaultFormat($time, $paren) {
		return pkript_format_date($time, $paren);
	}

	private static function expand($format, $time) {
		$out = '';
		$n = strlen($format);
		for ($i = 0; $i < $n; $i++) {
			$ch = $format[$i];
			// A backslash quotes the next character, as date() does
			if ($ch === '\\' && $i + 1 < $n) {
				$out .= $format[++$i];
				continue;
			}
			$out .= (strpos(self::LETTERS, $ch) === FALSE && $ch !== ' ')
				? $ch : date($ch, $time);
		}
		return $out;
	}
}

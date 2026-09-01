<?php
// $Id: budget.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - per request resource budget
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// Budget

/**
 * What one HTTP request may spend, shared by every script it runs.
 *
 * Counting here rather than on Pkript_Interpreter, which gets a new instance
 * per `#pkript` and per nested wiki.convert() - each of which would otherwise
 * start with a full allowance.
 */
class Pkript_Budget {
	private $steps = 0;
	private $converts = 0;
	private $reads = 0;
	private $writes = 0;

	// Seconds spent inside finished runs, plus the run in progress
	private $spent = 0.0;
	private $runStart = NULL;
	private $runDepth = 0;

	private static $current = NULL;

	/** Default for PKRIPT_MAX_MEMORY: three quarters of PHP's memory_limit. */
	public static function defaultLimit() {
		$limit = self::parseBytes(ini_get('memory_limit'));
		if ($limit <= 0)
			return 64 * 1024 * 1024;
		return max(16 * 1024 * 1024, (int) ($limit * 3 / 4));
	}

	/** '128M' -> 134217728. -1 when there is no limit. */
	public static function parseBytes($value) {
		$value = trim((string) $value);
		if ($value === '')
			return -1;
		$unit = strtolower(substr($value, -1));
		$n = (int) $value;
		if ($unit === 'g')
			return $n * 1024 * 1024 * 1024;
		if ($unit === 'm')
			return $n * 1024 * 1024;
		if ($unit === 'k')
			return $n * 1024;
		return $n;
	}

	public static function current() {
		if (self::$current === NULL)
			self::$current = new self();
		return self::$current;
	}

	/** Start over. For tests: a request never calls this. */
	public static function reset() {
		self::$current = new self();
	}

	/** Only the outermost run holds the clock, so nesting is not counted twice. */
	public function enterRun() {
		if ($this->runDepth++ === 0)
			$this->runStart = microtime(TRUE);
	}

	public function leaveRun() {
		if (--$this->runDepth <= 0) {
			$this->runDepth = 0;
			if ($this->runStart !== NULL) {
				$this->spent += microtime(TRUE) - $this->runStart;
				$this->runStart = NULL;
			}
		}
	}

	/** Time inside Pkript, not wall clock since the first `#pkript`. */
	public function elapsed() {
		if ($this->runStart === NULL)
			return $this->spent;
		return $this->spent + (microtime(TRUE) - $this->runStart);
	}

	public function step() {
		return ++$this->steps;
	}

	public function overSteps() {
		return $this->steps > PKRIPT_MAX_STEPS;
	}
	public function overTime() {
		return $this->elapsed() > PKRIPT_MAX_TIME;
	}

	/** Measured, not tallied: nothing here would know when a value was freed. */
	public function overMemory() {
		return memory_get_usage(TRUE) > PKRIPT_MAX_MEMORY;
	}

	public function spendConvert() {
		return ++$this->converts <= PKRIPT_MAX_CONVERT;
	}

	public function spendRead() {
		return ++$this->reads <= PKRIPT_MAX_READS;
	}

	public function spendWrite() {
		return ++$this->writes <= PKRIPT_MAX_WRITES;
	}
}

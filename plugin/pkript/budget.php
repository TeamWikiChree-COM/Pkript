<?php
// $Id: budget.php,v 0.3 2026/08/30 00:00:00 Pitan Exp $

/**
* Pkript runtime - per request resource budget
*
* @link https://blog.pitan76.net/?Pkript
* @author Pitan
* @license https://opensource.org/license/mit MIT
*/

// Loaded by plugin/pkript.inc.php; not meant to be requested directly.
if (! defined('PKRIPT_RUNTIME')) exit;

/////////////////////////////////////////////////
// Budget

/**
 * What one HTTP request may spend, shared by every script it runs.
 *
 * The limits used to live on Pkript_Interpreter, which meant a fresh instance
 * got a fresh allowance - and a page gets a new instance per `#pkript`, as does
 * every script reached through a nested wiki.convert(). Ten `#pkript` on a page
 * were ten times the budget; three levels of convert were 32x32x32 renders.
 * Counting here instead makes the constants mean what they say.
 */
class Pkript_Budget {
	private $steps    = 0;
	private $converts = 0;
	private $reads    = 0;
	private $writes   = 0;

	// Seconds spent inside finished runs, plus the run in progress
	private $spent    = 0.0;
	private $runStart = NULL;
	private $runDepth = 0;

	private static $current = NULL;

	public static function current() {
		if (self::$current === NULL) self::$current = new self();
		return self::$current;
	}

	/** Start over. For tests: a request never calls this. */
	public static function reset() {
		self::$current = new self();
	}

	/**
	 * Only the outermost run holds the clock, so a script reached through
	 * wiki.convert() does not have its time counted twice.
	 */
	public function enterRun() {
		if ($this->runDepth++ === 0) $this->runStart = microtime(TRUE);
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

	/**
	 * Time charged to Pkript so far. Whatever PukiWiki does between two
	 * `#pkript` on the same page is not part of it.
	 */
	public function elapsed() {
		if ($this->runStart === NULL) return $this->spent;
		return $this->spent + (microtime(TRUE) - $this->runStart);
	}

	/** @return int steps used, once this one is counted */
	public function step() { return ++$this->steps; }

	public function overSteps() { return $this->steps > PKRIPT_MAX_STEPS; }
	public function overTime()  { return $this->elapsed() > PKRIPT_MAX_TIME; }

	/** @return bool FALSE when there is no budget left for this one */
	public function spendConvert() {
		return ++$this->converts <= PKRIPT_MAX_CONVERT;
	}

	/** Reading or stat-ing a page. Cheap each, unbounded in a loop. */
	public function spendRead() {
		return ++$this->reads <= PKRIPT_MAX_READS;
	}

	public function spendWrite() {
		return ++$this->writes <= PKRIPT_MAX_WRITES;
	}
}

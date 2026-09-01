<?php
// $Id: data.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - data namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * A place for a script to keep something between requests.
 *
 * Where that is depends on the environment and is none of this module's
 * business: it settles Pkript's own rules - the store being on at all, what
 * a key may look like, how much trust a write needs, JSON, the budget - and
 * hands plain text to Pkript_Store. See adapter/store.php for the line.
 *
 * Writing goes through exactly the same policy as wiki.write(): an action, a
 * POST, a token and enough trust. A store that could be written while a page
 * was merely being rendered would be a way to make a visitor change the wiki
 * without knowing it, whatever the data is called.
 *
 * Reading does not check page permissions, and cannot: a guestbook has to
 * read its own store for a visitor who may not read :config pages at all.
 * So the store is shared - any script can read any key. Keep a secret out of
 * it; it is configuration and accumulated state, not private storage.
 */
class Pkript_Std_Data extends Pkript_Std_Module {
	private $json = NULL;

	public static function members() {
		return array('get', 'set', 'has', 'remove', 'keys', 'canWrite');
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'get':
				return $this->get($this->strArg($args, 0),
					$this->arg($args, 1, NULL), $node);

			case 'set':
				return $this->set($this->strArg($args, 0),
					$this->arg($args, 1, NULL), $node);

			case 'has':
				return $this->has($this->strArg($args, 0), $node);

			case 'remove':
				return $this->remove($this->strArg($args, 0), $node);

			case 'keys':
				return $this->keys($this->strArg($args, 0, ''), $node);

			// Whether a form would be worth drawing at all. Only the standing
			// refusals, since the form supplies what requestRefusal() wants.
			case 'canWrite':
				return $this->storeRefusal($this->strArg($args, 0)) === '';
		}
	}

	/** @return Pkript_Store */
	private function store() {
		return $this->rt->env()->store();
	}

	/////////////////////////////////////////////
	// Keys

	/**
	 * A key names a place in the store, so what may be in one is decided here
	 * rather than by whatever that place turns out to be. Segments of
	 * letters, digits, '_' and '-', joined by '/', and no segment may be '.'
	 * or '..': a key can only ever name something the store put there.
	 */
	private static function isKey($key) {
		if ($key === '' || strlen($key) > 128)
			return FALSE;
		return preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#', $key) === 1;
	}

	private function checkKey($key, $node) {
		if (!self::isKey($key))
			$this->rt->fail('Invalid data name: ' . $key, $node);
		return $key;
	}

	/////////////////////////////////////////////
	// Reading

	/**
	 * @param mixed $default what to return when the key was never written
	 */
	private function get($key, $default, $node) {
		$this->checkKey($key, $node);
		$this->spendRead($node);

		if (!PKRIPT_ALLOW_DATA)
			return $default;

		$text = $this->store()->get($key);
		if ($text === NULL || $text === '')
			return $default;

		// set() wrote it, so it parses - unless a person edited the store by
		// hand and got it wrong. That is worth saying plainly rather than
		// reporting as a JSON syntax error against a page they cannot see.
		try {
			return $this->json()->parse($text, $node);
		} catch (Pkript_LimitError $limit) {
			throw $limit;   // a limit is never a "broken data" answer
		} catch (Pkript_Error $e) {
			$this->rt->fail('Data ' . $key . ' is corrupt', $node);
		}
	}

	private function has($key, $node) {
		$this->checkKey($key, $node);
		$this->spendRead($node);
		return PKRIPT_ALLOW_DATA && $this->store()->has($key);
	}

	/** Every key, or the ones under $prefix. Sorted, so listings are stable. */
	private function keys($prefix, $node) {
		if ($prefix !== '' && !self::isKey($prefix))
			$this->rt->fail('Invalid data name: ' . $prefix, $node);
		if (!PKRIPT_ALLOW_DATA)
			return new Pkript_Arr();
		$this->spendRead($node);

		// A store may hand back whatever it happens to hold; only what this
		// module would accept as a key is a key.
		$out = array();
		foreach ($this->store()->keys($prefix) as $key) {
			if (self::isKey($key))
				$out[] = $key;
		}
		sort($out);

		if (count($out) > PKRIPT_MAX_PAGES) {
			$this->rt->failLimit('Too many pages (limit ' .
				PKRIPT_MAX_PAGES . ')', $node);
		}
		return new Pkript_Arr($this->rt->checkArray($out, $node));
	}

	/////////////////////////////////////////////
	// Writing

	/**
	 * Split the same way Pkript_Std_WikiWriter is, and for the same reason:
	 * canWrite() is asked while a page renders, so it must not be told no by
	 * the checks the form it is about to draw will satisfy.
	 *
	 * @return string why a write to $key would be refused now, '' if it
	 *                would go through
	 */
	private function refusal($key) {
		$refusal = $this->storeRefusal($key);
		return $refusal !== '' ? $refusal : $this->store()->requestRefusal();
	}

	/**
	 * What no form can change: this script, this key, this store.
	 *
	 * How far a script has to be trusted to write is the store's to say,
	 * since only the host knows what its trust levels mean; the run's own
	 * level is passed along and not read here.
	 */
	private function storeRefusal($key) {
		if (!PKRIPT_ALLOW_DATA)
			return 'The data store is disabled';
		if (!self::isKey($key))
			return 'Invalid data name';
		return $this->store()->refusal($this->rt->trust());
	}

	private function set($key, $value, $node) {
		$refusal = $this->refusal($key);
		if ($refusal !== '')
			$this->rt->fail($refusal, $node);

		$text = $this->json()->stringify($value, 0, $node);
		if (strlen($text) > PKRIPT_MAX_PAGE_BYTES) {
			$this->rt->failLimit('Data too large (limit ' .
				PKRIPT_MAX_PAGE_BYTES . ' bytes)', $node);
		}

		$this->spendWrite($node);
		$this->store()->set($key, $text);
		return TRUE;
	}

	/** Removing a key deletes what held it, the way the store deletes anything. */
	private function remove($key, $node) {
		$refusal = $this->refusal($key);
		if ($refusal !== '')
			$this->rt->fail($refusal, $node);

		if (!$this->store()->has($key))
			return FALSE;

		$this->spendWrite($node);
		return $this->store()->remove($key);
	}

	private function spendWrite($node) {
		if (!$this->rt->budget()->spendWrite()) {
			$this->rt->failLimit('Too many page writes (limit ' .
				PKRIPT_MAX_WRITES . ')', $node);
		}
	}

	/** JSON is the storage format, so the JSON module is where it is done. */
	private function json() {
		if ($this->json === NULL)
			$this->json = new Pkript_Std_Json($this->rt);
		return $this->json;
	}
}

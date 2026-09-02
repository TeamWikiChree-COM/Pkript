<?php
// $Id: env.php,v 0.5 2026/09/02 22:09:38 WikiChree.COM Team Exp $

/**
 * Pkript runtime - environment
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * Everything Pkript needs from outside itself, in one place.
 *
 * Deliberately not an interface: an environment is not asked to implement a
 * list of methods, it is handed one of these already filled with defaults
 * that work anywhere, and replaces the parts it has an opinion about.
 *
 *   $env = new Pkript_Env();
 *   $env->setStore(new Pkript_Store_WikiPage());
 *
 * So the cost of running Pkript somewhere new is only the adapters that
 * place actually cares about, and adding an adapter later does not break the
 * environments that never heard of it.
 *
 * Built where a run starts - see plugin_pkript_env() - and handed to
 * Pkript_Interpreter. The runtime never goes looking for one, so nothing in
 * lib/ has to know which environment it is in.
 */
class Pkript_Env {
	/** @var Pkript_Store */
	private $store = NULL;

	/** @var array of Pkript_Package, core first */
	private $packages = NULL;

	/** @return Pkript_Store */
	public function store() {
		if ($this->store === NULL)
			$this->store = new Pkript_Store_Memory();
		return $this->store;
	}

	/** @return Pkript_Env this, so setters chain */
	public function setStore(Pkript_Store $store) {
		$this->store = $store;
		return $this;
	}

	/**
	 * What a script can reach. The core package is always first and is not
	 * optional - it is the language itself - so an environment only ever
	 * names what it adds.
	 *
	 * @return array of Pkript_Package
	 */
	public function packages() {
		if ($this->packages === NULL)
			$this->packages = array(new Pkript_Package_Core());
		return $this->packages;
	}

	/** Added after the core package, so it may replace what the core named. */
	public function addPackage(Pkript_Package $package) {
		$this->packages = $this->packages();
		$this->packages[] = $package;
		return $this;
	}
}

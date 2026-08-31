<?php
// $Id: math.php,v 0.3 2026/08/31 18:20:16 WikiChree.COM Team Exp $

/**
 * Pkript runtime - Math namespace
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

class Pkript_Std_Math extends Pkript_Std_Module {
	public static function members() {
		return array('floor', 'ceil', 'round', 'abs', 'min', 'max', 'random');
	}

	public function call($name, $args, $node) {
		switch ($name) {
			case 'floor':
				return (int) floor($this->numArg($args, 0, $node));
			case 'ceil':
				return (int) ceil($this->numArg($args, 0, $node));
			case 'round':
				return (int) round($this->numArg($args, 0, $node));
			case 'abs':
				return abs($this->numArg($args, 0, $node));

			case 'random':
				// 0 <= n < 1, like JavaScript. Not security grade: a script
				// must never make a token, password or id with this.
				return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();

			case 'min':
			case 'max':
				return $this->extreme($name, $args, $node);
		}
	}

	private function extreme($name, $args, $node) {
		if (empty($args))
			$this->rt->fail($name . ' には引数が必要です', $node);

		$nums = array();
		foreach ($args as $a)
			$nums[] = $this->rt->toNumber($a, $node);
		return $name === 'min' ? min($nums) : max($nums);
	}
}

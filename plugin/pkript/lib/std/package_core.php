<?php
// $Id: package_core.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - core package
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/**
 * The standard library every environment gets: the language's own
 * conversions, the value types' methods, and the namespaces that need
 * nothing but PHP.
 *
 * data is here rather than with a host because keeping something between
 * requests is a promise the language makes; where it is kept is the
 * environment's answer, given as a Pkript_Store. See adapter/store.php.
 */
class Pkript_Package_Core extends Pkript_Package_Base {
	public function namespaces() {
		return array(
			'html' => 'Pkript_Std_Html',
			'url' => 'Pkript_Std_Url',
			'JSON' => 'Pkript_Std_Json',
			'date' => 'Pkript_Std_Date',
			'Math' => 'Pkript_Std_Math',
			'Object' => 'Pkript_Std_Object',
			'data' => 'Pkript_Std_Data',
			'console' => 'Pkript_Std_Console',
		);
	}

	public function types() {
		return array(
			'String' => 'Pkript_Std_StringMethods',
			'Array' => 'Pkript_Std_ArrayMethods',
			'Number' => 'Pkript_Std_NumberMethods',
			'RegExp' => 'Pkript_Std_RegexMethods',
		);
	}

	public function globals() {
		return array(
			'htmlsc' => 'html.escape',
			'format_date' => 'date.format',

			// The language's own conversions and argument helpers, which
			// have no namespace to live in.
			'func_get_args' => 'lang.func_get_args',
			'func_num_args' => 'lang.func_num_args',
			'func_get_arg' => 'lang.func_get_arg',
			'String' => 'lang.String',
			'Number' => 'lang.Number',
			'Boolean' => 'lang.Boolean',
			'parseInt' => 'lang.parseInt',
			'parseFloat' => 'lang.parseFloat',
			'isNaN' => 'lang.isNaN',
			'isFinite' => 'lang.isFinite',
		);
	}

	/**
	 * NaN is what parseInt() answers with when the text is not a number, so a
	 * script needs a name to compare against - though `NaN === NaN` is false
	 * there as everywhere, and isNaN() is the test.
	 */
	public function constants() {
		return array(
			'NaN' => NAN,
			'Infinity' => INF,
		);
	}
}

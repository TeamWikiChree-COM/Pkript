<?php
// $Id: sanitizer.php,v 0.4 2026/09/01 22:34:53 WikiChree.COM Team Exp $

/**
 * Pkript runtime - HTML sanitizer
 *
 * @link https://wikichree.com/guide/?Pkript
 * @author WikiChree.COM Team
 * @license https://opensource.org/license/mit MIT
 */

/////////////////////////////////////////////////
// HTML Sanitizer
//
// Whitelist based. Anything not explicitly allowed is removed.

class Pkript_Sanitizer {
	private static $allowedTags = array(
		'b',
		'strong',
		'i',
		'em',
		'u',
		's',
		'code',
		'pre',
		'br',
		'hr',
		'p',
		'div',
		'span',
		'ul',
		'ol',
		'li',
		'dl',
		'dt',
		'dd',
		'table',
		'thead',
		'tbody',
		'tr',
		'th',
		'td',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		'a',
		'img',
		'blockquote',
		// Forms. See filterUrl() for why a form may only post to this wiki.
		'form',
		'input',
		'textarea',
		'select',
		'option',
		'optgroup',
		'label',
		'button',
		'fieldset',
		'legend',
	);

	// Removed together with their contents
	private static $strippedTags = array(
		'script',
		'style',
		'iframe',
		'object',
		'embed',
		'link',
		'meta',
		'base',
		'frame',
		'frameset',
		'applet',
		'svg',
		'math',
	);

	private static $allowedAttrs = array(
		'alt',
		'title',
		'colspan',
		'rowspan',
		'width',
		'height',
		// Form controls
		'method',
		'type',
		'value',
		'size',
		'maxlength',
		'rows',
		'cols',
		'placeholder',
		'checked',
		'selected',
		'disabled',
		'readonly',
		'multiple',
		'min',
		'max',
		'step',
	);

	// input types a script may render. 'password' and 'file' are left out on
	// purpose: neither has an honest use in a wiki page written by a script,
	// and both raise the value of tricking someone into filling the form in.
	// 'image' is out because it takes an external src.
	private static $allowedInputTypes = array(
		'text',
		'hidden',
		'submit',
		'reset',
		'button',
		'checkbox',
		'radio',
		'number',
		'search',
		'email',
		'url',
		'tel',
		'date',
	);

	// Presentation only. Anything that can move an element out of the flow of
	// the page and put it over the wiki's own chrome - position, z-index,
	// float to the viewport, background-image - stays out: a script may make
	// its own output look like anything, but not make it look like the site.
	private static $allowedStyleProps = array(
		'color',
		'background-color',
		'font-size',
		'font-weight',
		'font-style',
		'font-family',
		'text-align',
		'text-decoration',
		'line-height',
		'margin',
		'margin-top',
		'margin-right',
		'margin-bottom',
		'margin-left',
		'padding',
		'padding-top',
		'padding-right',
		'padding-bottom',
		'padding-left',
		'border',
		'border-top',
		'border-right',
		'border-bottom',
		'border-left',
		'border-color',
		'border-width',
		'border-style',
		'border-radius',
		'width',
		'height',
		'max-width',
		'max-height',
		'display',
		'vertical-align',
		'opacity',
		'min-width',
		'min-height',
		'box-sizing',
		'box-shadow',
		'overflow',
		'overflow-x',
		'overflow-y',
		'visibility',
		'clear',
		'float',

		// Text
		'letter-spacing',
		'word-spacing',
		'text-indent',
		'text-transform',
		'text-shadow',
		'white-space',
		'word-break',
		'overflow-wrap',
		'text-overflow',
		'font-variant',
		'list-style',
		'list-style-type',
		'list-style-position',

		// Tables
		'border-collapse',
		'border-spacing',
		'table-layout',
		'caption-side',
		'outline',
		'outline-color',
		'outline-width',
		'outline-style',
		'outline-offset',

		// Flexbox
		'flex',
		'flex-direction',
		'flex-wrap',
		'flex-flow',
		'flex-grow',
		'flex-shrink',
		'flex-basis',
		'justify-content',
		'justify-items',
		'justify-self',
		'align-items',
		'align-content',
		'align-self',
		'order',
		'gap',
		'row-gap',
		'column-gap',

		// Grid
		'grid-template-columns',
		'grid-template-rows',
		'grid-template-areas',
		'grid-auto-columns',
		'grid-auto-rows',
		'grid-auto-flow',
		'grid-area',
		'grid-column',
		'grid-row',
		'grid-column-start',
		'grid-column-end',
		'grid-row-start',
		'grid-row-end',
		'place-items',
		'place-content',
		'place-self',

		// Motion. animation-name can only reach keyframes the skin already
		// defines: a script cannot write a <style> block, and the sanitizer
		// would remove one if it did.
		'transition',
		'transition-property',
		'transition-duration',
		'transition-delay',
		'transition-timing-function',
		'animation',
		'animation-name',
		'animation-duration',
		'animation-delay',
		'animation-iteration-count',
		'animation-direction',
		'animation-timing-function',
		'animation-fill-mode',
		'animation-play-state',
		'transform',
		'transform-origin',
		'will-change',
		'cursor',
		'filter',
	);

	/**
	 * Functions a value may use, and nothing else. Each takes numbers,
	 * lengths, angles, times, percentages or colors - never a URL, an
	 * attribute or an expression, which is what makes the whole set safe to
	 * hand to a browser.
	 */
	private static $allowedStyleFunctions = array(
		'rgb', 'rgba', 'hsl', 'hsla',
		'translate', 'translatex', 'translatey', 'translate3d',
		'scale', 'scalex', 'scaley', 'scale3d',
		'rotate', 'rotatex', 'rotatey', 'rotatez', 'rotate3d',
		'skew', 'skewx', 'skewy', 'matrix', 'perspective',
		'cubic-bezier', 'steps',
		'repeat', 'minmax', 'fit-content',
		'blur', 'brightness', 'contrast', 'grayscale', 'invert',
		'opacity', 'saturate', 'sepia', 'drop-shadow', 'hue-rotate',
	);

	private static $allowedDisplay = array(
		'block',
		'inline',
		'inline-block',
		'none',
		'flex',
		'inline-flex',
		'grid',
		'inline-grid',
		'flow-root',
		'table',
		'table-row',
		'table-cell',
		'list-item',
		'contents',
	);

	// Token that stands in for trusted HTML while the script's output is being
	// cleaned. Plain text, so it survives parsing untouched wherever it lands.
	const FRAGMENT_PREFIX = '@@PKRIPT-FRAGMENT-';
	const FRAGMENT_SUFFIX = '@@';

	/**
	 * Park trusted HTML and return the token that stands in for it.
	 * @param array $fragments passed by reference; the caller owns the table
	 */
	public static function addFragment(&$fragments, $html) {
		$index = count($fragments);
		$fragments[$index] = $html;
		return self::FRAGMENT_PREFIX . $index . self::FRAGMENT_SUFFIX;
	}

	/**
	 * @param string $html      the script's return value
	 * @param array  $fragments trusted HTML from wiki.convert(), by index
	 */
	public static function sanitize($html, $fragments = array()) {
		// JSX marks which runs of a string are HTML already. They have done
		// their work by now, and are not meant to reach the page.
		$html = Pkript_Interpreter::stripHtmlMarks($html);
		if ($html === '')
			return '';

		// Without the DOM extension we cannot parse reliably, so escape everything.
		if (!extension_loaded('dom'))
			return pkript_htmlsc($html);

		$doc = new DOMDocument();
		$prev = libxml_use_internal_errors(TRUE);
		$wrapped = '<?xml encoding="UTF-8"?><div id="pkript-root">' . $html . '</div>';
		$ok = $doc->loadHTML($wrapped, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);

		if (!$ok)
			return pkript_htmlsc($html);

		$root = $doc->getElementById('pkript-root');
		if ($root === NULL) {
			// getElementById needs a DTD-declared id; fall back to a manual search
			foreach ($doc->getElementsByTagName('div') as $div) {
				if ($div->getAttribute('id') === 'pkript-root') {
					$root = $div;
					break;
				}
			}
		}
		if ($root === NULL)
			return pkript_htmlsc($html);

		self::cleanNode($root);
		if (!empty($fragments))
			self::restoreFragments($root, $fragments);

		$out = '';
		foreach ($root->childNodes as $child) {
			$out .= $doc->saveHTML($child);
		}
		return $out;
	}

	/**
	 * Swap fragment tokens back for their trusted HTML.
	 *
	 * Only text nodes are examined, and this runs after cleanNode(), so a token
	 * that a script hid inside an attribute stays inert text: the substitution
	 * can never open an attribute value or a tag.
	 */
	private static function restoreFragments($root, $fragments) {
		$doc = $root->ownerDocument;
		$xpath = new DOMXPath($doc);
		$texts = array();
		foreach ($xpath->query('.//text()', $root) as $text)
			$texts[] = $text;

		$pattern = '/' . preg_quote(self::FRAGMENT_PREFIX, '/') . '(\d{1,4})' .
			preg_quote(self::FRAGMENT_SUFFIX, '/') . '/';

		foreach ($texts as $text) {
			if (strpos($text->nodeValue, self::FRAGMENT_PREFIX) === FALSE)
				continue;

			// No PREG_SPLIT_NO_EMPTY: empty pieces keep the even/odd rhythm
			// that tells text apart from a captured fragment index.
			$parts = preg_split($pattern, $text->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
			$parent = $text->parentNode;
			if ($parent === NULL)
				continue;

			foreach ($parts as $i => $part) {
				// Odd entries are the captured indexes
				if ($i % 2 === 1 && isset($fragments[(int) $part])) {
					$parent->insertBefore(
						self::importHtml($doc, $fragments[(int) $part]),
						$text
					);
				} elseif ($part !== '') {
					$parent->insertBefore($doc->createTextNode($part), $text);
				}
			}
			$parent->removeChild($text);
		}
	}

	/** Parse trusted HTML into a fragment belonging to $doc. */
	private static function importHtml($doc, $html) {
		$tmp = new DOMDocument();
		$prev = libxml_use_internal_errors(TRUE);
		$ok = $tmp->loadHTML('<?xml encoding="UTF-8"?><div id="pkript-frag">' .
			$html . '</div>', LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);

		$out = $doc->createDocumentFragment();
		if (!$ok)
			return $out;

		$holder = NULL;
		foreach ($tmp->getElementsByTagName('div') as $div) {
			if ($div->getAttribute('id') === 'pkript-frag') {
				$holder = $div;
				break;
			}
		}
		if ($holder === NULL)
			return $out;

		foreach ($holder->childNodes as $child) {
			$out->appendChild($doc->importNode($child, TRUE));
		}
		return $out;
	}

	/** Recursively clean the children of $node. */
	private static function cleanNode($node) {
		// Iterate over a snapshot: the live NodeList shifts as we remove nodes
		$children = array();
		foreach ($node->childNodes as $child)
			$children[] = $child;

		foreach ($children as $child) {
			if ($child->nodeType === XML_TEXT_NODE)
				continue;

			if (
				$child->nodeType === XML_COMMENT_NODE ||
				$child->nodeType === XML_PI_NODE ||
				$child->nodeType === XML_CDATA_SECTION_NODE
			) {
				$node->removeChild($child);
				continue;
			}
			if ($child->nodeType !== XML_ELEMENT_NODE) {
				$node->removeChild($child);
				continue;
			}

			$tag = strtolower($child->nodeName);

			// Dangerous element: drop it and everything inside
			if (in_array($tag, self::$strippedTags, TRUE)) {
				$node->removeChild($child);
				continue;
			}

			// Unknown element: unwrap it, keeping the (cleaned) children
			if (!in_array($tag, self::$allowedTags, TRUE)) {
				self::cleanNode($child);
				while ($child->firstChild !== NULL) {
					$node->insertBefore($child->firstChild, $child);
				}
				$node->removeChild($child);
				continue;
			}

			self::cleanAttributes($child, $tag);
			self::cleanNode($child);
		}
	}

	private static function cleanAttributes($el, $tag) {
		$attrs = array();
		foreach ($el->attributes as $attr)
			$attrs[] = $attr;

		foreach ($attrs as $attr) {
			$name = strtolower($attr->nodeName);
			$value = $attr->nodeValue;

			// Event handlers are never allowed
			if (strpos($name, 'on') === 0) {
				$el->removeAttribute($attr->nodeName);
				continue;
			}

			// A form may only post back to this wiki. Plain Wiki markup cannot
			// produce a <form> at all, so this is capability a script would not
			// otherwise have - and a form pointing at someone else's server is
			// how a convincing credential prompt gets built.
			if ($name === 'action') {
				$safe = self::filterUrl($value);
				// filterUrl() allows http(s): and mailto:; a form target must be relative
				if ($safe !== NULL && preg_match('#^[a-z]+:#i', $safe))
					$safe = NULL;
				self::setOrRemove($el, $attr, $safe);
				continue;
			}

			// Field names reach PHP as request keys, so they are checked but
			// never rewritten - prefixing them would break every form.
			if ($name === 'name' && $tag !== 'a') {
				if (preg_match('/^[A-Za-z_][A-Za-z0-9_\-]{0,63}(\[\])?$/', $value)) {
					$el->setAttribute($name, $value);
				} else {
					$el->removeAttribute($attr->nodeName);
				}
				continue;
			}

			// <label for> has to keep pointing at the id it labels, and ids get
			// the pkript- prefix, so this needs the same treatment.
			if ($name === 'for' || $name === 'class' || $name === 'id') {
				self::setOrRemove($el, $attr, self::filterClassOrId($value));
				continue;
			}

			if ($name === 'href' || $name === 'src') {
				self::setOrRemove($el, $attr, self::filterUrl($value));
				continue;
			}

			if ($name === 'style') {
				self::setOrRemove($el, $attr, self::filterStyle($value));
				continue;
			}

			if (!in_array($name, self::$allowedAttrs, TRUE)) {
				$el->removeAttribute($attr->nodeName);
			}
		}

		// Outbound links should not leak the referrer or grant window.opener
		if ($tag === 'a' && $el->hasAttribute('href')) {
			if (preg_match('#^https?://#i', $el->getAttribute('href'))) {
				$el->setAttribute('rel', 'nofollow noopener noreferrer');
			}
		}

		if ($tag === 'input') {
			// An unknown or missing type would fall back to 'text' in the
			// browser, so pin it rather than leaving it to chance
			$type = strtolower($el->getAttribute('type'));
			if (!in_array($type, self::$allowedInputTypes, TRUE)) {
				$el->setAttribute('type', 'text');
			}
		}

		// A form with nowhere to post would silently submit to the current
		// URL, which is rarely what the script meant
		if ($tag === 'form') {
			if (!$el->hasAttribute('action')) {
				$el->setAttribute('action', self::selfUri());
			}
			$method = strtolower($el->getAttribute('method'));
			$el->setAttribute('method', $method === 'get' ? 'get' : 'post');
		}
	}

	/**
	 * Write back a filtered attribute, or drop it when the filter kept nothing
	 * of it - every filter signals that as NULL or an empty string.
	 */
	private static function setOrRemove($el, $attr, $safe) {
		if ($safe === NULL || $safe === '') {
			$el->removeAttribute($attr->nodeName);
		} else {
			$el->setAttribute(strtolower($attr->nodeName), $safe);
		}
	}

	/** Where a form with no action of its own should post back to. */
	private static function selfUri() {
		return pkript_self_uri();
	}

	/** @return string|NULL the URL, or NULL when it must be dropped */
	private static function filterUrl($url) {
		$u = trim($url);
		// Strip control characters used to smuggle "java\0script:"
		$u = preg_replace('/[\x00-\x20\x7F]/', '', $u);
		if ($u === '')
			return NULL;

		if (preg_match('#^(https?:|mailto:)#i', $u))
			return $u;
		// Relative URLs only: no scheme, no protocol-relative "//host"
		if (strpos($u, '//') === 0)
			return NULL;
		if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $u))
			return NULL;
		return $u;
	}

	private static function filterClassOrId($value) {
		$out = array();
		foreach (preg_split('/\s+/', trim($value)) as $token) {
			if ($token === '')
				continue;
			if (!preg_match('/^[A-Za-z0-9_-]+$/', $token))
				continue;
			$out[] = 'pkript-' . $token;
		}
		return implode(' ', $out);
	}

	/**
	 * Property whitelist plus value pattern checks.
	 * Properties that fail the check are dropped silently; the element stays.
	 */
	private static function filterStyle($style) {
		// Anything that could start a new context or escape the value
		if (preg_match('/(expression|javascript:|vbscript:|@import|\\\\|\/\*|url\s*\()/i', $style)) {
			return '';
		}

		$out = array();
		foreach (explode(';', $style) as $decl) {
			if (trim($decl) === '')
				continue;
			$parts = explode(':', $decl, 2);
			if (count($parts) !== 2)
				continue;

			$prop = strtolower(trim($parts[0]));
			$value = trim($parts[1]);

			if (!in_array($prop, self::$allowedStyleProps, TRUE))
				continue;
			if ($value === '' || strlen($value) > 200)
				continue;
			if (!self::isValidStyleValue($prop, $value))
				continue;

			$out[] = $prop . ':' . $value;
		}
		return implode('; ', $out);
	}

	private static function isValidStyleValue($prop, $value) {
		if ($prop === 'display') {
			return in_array(strtolower($value), self::$allowedDisplay, TRUE);
		}
		if ($prop === 'opacity') {
			return preg_match('/^(0|1|0?\.[0-9]{1,3})$/', $value) === 1;
		}
		if ($prop === 'color' || $prop === 'background-color' || $prop === 'border-color') {
			return self::isColor($value);
		}
		if ($prop === 'grid-template-areas') {
			// Quoted row names, which nothing else in a style value uses
			return preg_match('/^(?:"[a-zA-Z0-9_. -]{0,60}"\s*)+$/', $value) === 1;
		}

		// Everything else: colors, lengths, keywords and the allowed
		// functions, in any order. Commas separate arguments and list items
		// and carry no meaning of their own here.
		foreach (self::styleTokens($value) as $token) {
			$token = rtrim($token, ',');
			if ($token === '')
				continue;
			if (self::isColor($token))
				continue;
			if (self::isLength($token))
				continue;
			if (self::isStyleFunction($token))
				continue;
			if (preg_match('/^[a-z][a-z0-9-]{0,29}$/i', $token))
				continue; // keyword: bold, solid, center, ease-in-out...
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Split on whitespace, but keep a function call together: `rgba(0, 0, 0,
	 * .5)` is one token even though it has spaces in it. Unbalanced
	 * parentheses end up in a token that no check accepts, so the declaration
	 * is dropped.
	 */
	private static function styleTokens($value) {
		$tokens = array();
		$current = '';
		$depth = 0;
		$n = strlen($value);

		for ($i = 0; $i < $n; $i++) {
			$ch = $value[$i];
			if ($ch === '(') $depth++;
			if ($ch === ')') $depth--;
			if ($depth < 0)
				return array($value);   // ')' with nothing open: refuse it whole

			if ($depth === 0 && ($ch === ' ' || $ch === "\t" || $ch === "\n")) {
				if ($current !== '') {
					$tokens[] = $current;
					$current = '';
				}
				continue;
			}
			$current .= $ch;
		}
		if ($depth !== 0)
			return array($value);
		if ($current !== '')
			$tokens[] = $current;
		return $tokens;
	}

	/**
	 * A call to one of the allowed functions, with only numbers, lengths,
	 * percentages, keywords and nested allowed calls inside it.
	 */
	private static function isStyleFunction($token, $depth = 0) {
		if ($depth > 3)
			return FALSE;
		if (!preg_match('/^([a-z][a-z0-9-]{0,19})\((.*)\)$/is', $token, $m))
			return FALSE;
		if (!in_array(strtolower($m[1]), self::$allowedStyleFunctions, TRUE))
			return FALSE;

		foreach (self::splitArguments($m[2]) as $arg) {
			$arg = trim($arg);
			if ($arg === '')
				continue;
			if (self::isLength($arg) || self::isColor($arg))
				continue;
			if (self::isStyleFunction($arg, $depth + 1))
				continue;
			if (preg_match('/^[a-z][a-z0-9-]{0,29}$/i', $arg))
				continue;
			// `repeat(3, 1fr)` and `steps(4, end)` put two values in one
			// argument only when written with spaces, so try those too
			$parts = preg_split('/\s+/', $arg);
			if (count($parts) < 2)
				return FALSE;
			foreach ($parts as $part) {
				if (!self::isLength($part) && !self::isColor($part) &&
					!self::isStyleFunction($part, $depth + 1) &&
					!preg_match('/^[a-z][a-z0-9-]{0,29}$/i', $part)) {
					return FALSE;
				}
			}
		}
		return TRUE;
	}

	/** Top level commas only, so a nested call keeps its own arguments. */
	private static function splitArguments($text) {
		$args = array();
		$current = '';
		$depth = 0;
		$n = strlen($text);

		for ($i = 0; $i < $n; $i++) {
			$ch = $text[$i];
			if ($ch === '(') $depth++;
			if ($ch === ')') $depth--;
			if ($ch === ',' && $depth === 0) {
				$args[] = $current;
				$current = '';
				continue;
			}
			$current .= $ch;
		}
		$args[] = $current;
		return $args;
	}

	private static function isColor($value) {
		$v = trim($value);
		if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v))
			return TRUE;
		if (preg_match('/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/i', $v))
			return TRUE;
		if (preg_match('/^rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(0|1|0?\.\d{1,3})\s*\)$/i', $v))
			return TRUE;
		if (preg_match('/^[a-z]{3,20}$/i', $v))
			return TRUE; // named color / keyword
		return FALSE;
	}

	// Lengths, angles, times and the grid's fraction. No viewport units: a
	// script sizing itself against the window is how an overlay is built.
	private static function isLength($value) {
		if (!preg_match('/^[+-]?\d{0,6}(\.\d{1,4})?' .
				'(px|em|rem|ex|ch|%|pt|pc|cm|mm|in|fr|deg|grad|rad|turn|ms|s)?$/i',
				$value)) {
			return FALSE;
		}
		if (!preg_match('/\d/', $value))
			return FALSE;
		// Reject absurd values used to break the layout
		$n = (float) $value;
		return $n > -10000 && $n < 10000;
	}
}

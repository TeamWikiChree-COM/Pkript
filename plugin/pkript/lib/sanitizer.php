<?php
// $Id: sanitizer.php,v 0.2 2026/08/31 11:06:32 WikiChree.COM Team Exp $

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

class Pkript_Sanitizer
{
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
	);

	private static $allowedDisplay = array(
		'block',
		'inline',
		'inline-block',
		'none',
		'flex',
	);

	// Token that stands in for trusted HTML while the script's output is being
	// cleaned. Plain text, so it survives parsing untouched wherever it lands.
	const FRAGMENT_PREFIX = '@@PKRIPT-FRAGMENT-';
	const FRAGMENT_SUFFIX = '@@';

	/**
	 * Park trusted HTML and return the token that stands in for it.
	 * @param array $fragments passed by reference; the caller owns the table
	 */
	public static function addFragment(&$fragments, $html)
	{
		$index = count($fragments);
		$fragments[$index] = $html;
		return self::FRAGMENT_PREFIX . $index . self::FRAGMENT_SUFFIX;
	}

	/**
	 * @param string $html      the script's return value
	 * @param array  $fragments trusted HTML from wiki.convert(), by index
	 */
	public static function sanitize($html, $fragments = array())
	{
		// JSX marks which runs of a string are HTML already. They have done
		// their work by now, and are not meant to reach the page.
		$html = Pkript_Interpreter::stripHtmlMarks($html);
		if ($html === '')
			return '';

		// Without the DOM extension we cannot parse reliably, so escape everything.
		if (!extension_loaded('dom'))
			return htmlsc($html);

		$doc = new DOMDocument();
		$prev = libxml_use_internal_errors(TRUE);
		$wrapped = '<?xml encoding="UTF-8"?><div id="pkript-root">' . $html . '</div>';
		$ok = $doc->loadHTML($wrapped, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);

		if (!$ok)
			return htmlsc($html);

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
			return htmlsc($html);

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
	private static function restoreFragments($root, $fragments)
	{
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
	private static function importHtml($doc, $html)
	{
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
	private static function cleanNode($node)
	{
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

	private static function cleanAttributes($el, $tag)
	{
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
	private static function setOrRemove($el, $attr, $safe)
	{
		if ($safe === NULL || $safe === '') {
			$el->removeAttribute($attr->nodeName);
		} else {
			$el->setAttribute(strtolower($attr->nodeName), $safe);
		}
	}

	/** This wiki's own entry point, for a form with no action of its own. */
	private static function selfUri()
	{
		if (function_exists('get_base_uri'))
			return get_base_uri();
		if (function_exists('get_script_uri'))
			return get_script_uri();
		return './';
	}

	/** @return string|NULL the URL, or NULL when it must be dropped */
	private static function filterUrl($url)
	{
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

	private static function filterClassOrId($value)
	{
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
	private static function filterStyle($style)
	{
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
			if ($value === '' || strlen($value) > 100)
				continue;
			if (!self::isValidStyleValue($prop, $value))
				continue;

			$out[] = $prop . ':' . $value;
		}
		return implode('; ', $out);
	}

	private static function isValidStyleValue($prop, $value)
	{
		if ($prop === 'display') {
			return in_array(strtolower($value), self::$allowedDisplay, TRUE);
		}
		if ($prop === 'opacity') {
			return preg_match('/^(0|1|0?\.[0-9]{1,3})$/', $value) === 1;
		}
		if ($prop === 'color' || $prop === 'background-color' || $prop === 'border-color') {
			return self::isColor($value);
		}

		// Everything else: a space-separated list of colors, lengths and keywords
		foreach (preg_split('/\s+/', $value) as $token) {
			if ($token === '')
				continue;
			if (self::isColor($token))
				continue;
			if (self::isLength($token))
				continue;
			if (preg_match('/^[a-z-]{1,20}$/i', $token))
				continue; // keyword: bold, solid, center...
			return FALSE;
		}
		return TRUE;
	}

	private static function isColor($value)
	{
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

	private static function isLength($value)
	{
		if (!preg_match('/^-?\d{1,6}(\.\d{1,3})?(px|em|rem|%|pt)?$/i', $value))
			return FALSE;
		// Reject absurd values used to break the layout
		$n = (float) $value;
		return $n > -10000 && $n < 10000;
	}
}

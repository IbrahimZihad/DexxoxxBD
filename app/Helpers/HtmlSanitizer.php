<?php

declare(strict_types=1);

namespace App\Helpers;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Whitelist HTML sanitiser for CMS-authored content.
 *
 * Admins paste pages, banners and product descriptions from documents and other
 * sites, so that text is untrusted by the time it reaches a shopper's browser.
 * Rather than a regex (which can be broken by nesting or encodings), the markup
 * is parsed and rebuilt: unknown tags are unwrapped, script-bearing tags are
 * removed with their children, every event handler is dropped and only a small
 * set of attributes survives. URL attributes lose any scheme that can execute.
 */
final class HtmlSanitizer
{
    /** @var list<string> tags that are kept, with their children */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'span', 'div', 'strong', 'em', 'b', 'i', 'u', 's', 'small', 'mark',
        'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'col', 'colgroup',
        'img', 'figure', 'figcaption', 'dl', 'dt', 'dd', 'abbr', 'sub', 'sup',
    ];

    /** @var list<string> tags removed together with everything inside them */
    private const REMOVED_TAGS = [
        'script', 'style', 'noscript', 'iframe', 'frame', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math',
    ];

    /** @var list<string> attributes allowed on any kept element */
    private const GLOBAL_ATTRS = ['class', 'id', 'title', 'dir', 'lang'];

    /** @var array<string, list<string>> per-element extra attributes */
    private const TAG_ATTRS = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'decoding'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'ol' => ['start', 'type'],
        'li' => ['value'],
        'col' => ['span'],
        'colgroup' => ['span'],
    ];

    /** @var list<string> attributes that must never survive anywhere */
    private const URL_ATTRS = ['href', 'src'];

    private function __construct()
    {
    }

    /**
     * Reduce arbitrary HTML to the safe subset above.
     *
     * @param string|null $html raw markup, typically from a CMS column
     * @param bool $allowParagraphs when false the result is plain text
     */
    public static function sanitize(?string $html, bool $allowParagraphs = true): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        // The wrapper keeps loadHTML from inventing html/body around a fragment
        // and gives a single node to serialise back from.
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="dx-sanitize-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return self::escapeAll($html);
        }

        $root = $document->getElementById('dx-sanitize-root');
        if ($root === null) {
            return self::escapeAll($html);
        }

        self::walk($root);

        if (!$allowParagraphs) {
            return trim($root->textContent ?? '');
        }

        $markup = '';
        foreach ($root->childNodes as $child) {
            $markup .= (string) $document->saveHTML($child);
        }

        return trim($markup);
    }

    /**
     * Text-only version used where a snippet must not carry any markup at all.
     */
    public static function text(?string $html, int $limit = 0): string
    {
        $text = preg_replace('/\s+/u', ' ', self::sanitize($html, false)) ?? '';
        $text = trim($text);
        if ($limit > 0 && mb_strlen($text) > $limit) {
            return rtrim(mb_substr($text, 0, $limit)) . '...';
        }
        return $text;
    }

    /**
     * Depth-first: prune dangerous subtrees, unwrap unknown tags, filter attrs.
     */
    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                // Comments and processing instructions carry nothing a page needs.
                $node->removeChild($child);
                continue;
            }

            $name = strtolower($child->nodeName);

            if (in_array($name, self::REMOVED_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($name, self::ALLOWED_TAGS, true)) {
                self::walk($child);
                self::unwrap($node, $child);
                continue;
            }

            self::walk($child);
            self::filterAttributes($child, $name);
        }
    }

    private static function filterAttributes(DOMElement $element, string $name): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRS, self::TAG_ATTRS[$name] ?? []);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $key = strtolower($attribute->nodeName);

            // on* is an event handler, style is a vector for exfiltration and
            // srcset/formaction do not belong in resold-catalogue copy.
            if (str_starts_with($key, 'on') || in_array($key, ['style', 'formaction', 'srcset'], true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            if (!in_array($key, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            if (in_array($key, self::URL_ATTRS, true)) {
                $safe = self::safeUrl($attribute->nodeValue ?? '');
                if ($safe === null) {
                    $element->removeAttribute($attribute->nodeName);
                    continue;
                }
                $element->setAttribute($key, $safe);
            }
        }

        // Third-party targets need rel to keep the referrer and the tab isolated.
        if ($name === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * @return string|null the URL to keep, or null when the attribute must go
     */
    private static function safeUrl(?string $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }

        // Collapse control characters and whitespace so "java\tscript:" cannot
        // slip past the scheme test.
        $probe = strtolower(preg_replace('/[\s\x00-\x1F]+/', '', $url) ?? $url);

        foreach (['javascript:', 'vbscript:', 'file:', 'about:', 'data:'] as $blocked) {
            if (str_starts_with($probe, $blocked)) {
                // A data: image is the one data payload this site legitimately embeds.
                if ($blocked === 'data:' && str_starts_with($probe, 'data:image/')) {
                    return $url;
                }
                return null;
            }
        }

        return $url;
    }

    /**
     * Replace an element with its children, preserving inline flow.
     */
    private static function unwrap(DOMNode $parent, DOMElement $element): void
    {
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private static function escapeAll(string $html): string
    {
        return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

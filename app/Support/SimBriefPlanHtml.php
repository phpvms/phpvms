<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Splits SimBrief's `plan_html` into its own top-level sections and sanitizes
 * each one for rendering.
 *
 * SimBrief embeds bookmark markers in the OFP body — `<!--BKMK///Title///0-->`
 * for a top-level section and `///1` for a subsection of the one above it.
 * Those markers are the provider's own table of contents, so the tab titles are
 * SimBrief's rather than a list we would have to keep in sync by hand.
 *
 * Sanitizing is not optional here. `plan_html` is third-party HTML fetched from
 * the SimBrief API, and the page renders it with `v-html`; escaping it (the old
 * `<pre>{{ textOfp }}</pre>`) was safe but showed raw tags. The allowlist below
 * is deliberately narrow — the observed document uses only div/pre/b/h2/a/img —
 * and everything outside it is unwrapped rather than dropped, so unexpected
 * markup loses its behaviour without losing the flight-planning text inside it.
 */
final class SimBriefPlanHtml
{
    /** `<!--BKMK///Section title///0-->` — level 0 opens a section, 1 a subsection. */
    private const string MARKER = '~<!--BKMK///(?P<title>.*?)///(?P<level>\d+)-->~';

    /** Elements kept. Anything else is unwrapped, preserving its text. */
    private const array ALLOWED_TAGS = [
        'a', 'b', 'br', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'i',
        'p', 'pre', 'span', 'strong', 'sub', 'sup', 'table', 'tbody',
        'td', 'th', 'thead', 'tr', 'u',
    ];

    /**
     * Elements removed WITH their contents, rather than unwrapped.
     *
     * Unwrapping keeps an element's children, and a `<script>`'s child is the
     * text node holding its code — so unwrapping leaked `alert(1)` back out as
     * visible OFP text. These carry no readable flight-planning content, so
     * dropping the subtree loses nothing.
     */
    private const array DROPPED_SUBTREES = [
        'script', 'style', 'iframe', 'object', 'embed', 'noscript',
        'template', 'link', 'meta', 'base', 'form', 'input', 'button',
        'select', 'textarea', 'svg', 'math',
    ];

    /** Attributes kept, per element. Everything else is stripped. */
    private const array ALLOWED_ATTRIBUTES = [
        'a'   => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
        '*'   => ['class'],
    ];

    /**
     * Top-level sections, in document order.
     *
     * @return list<array{title: string, html: string}>
     */
    public static function sections(string $planHtml): array
    {
        $body = self::body($planHtml);

        if (!self::hasContent($body)) {
            return [];
        }

        preg_match_all(self::MARKER, $body, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        /** @var list<array{title: string, start: int}> $starts */
        $starts = [];
        foreach ($matches as $match) {
            if ($match['level'][0] !== '0') {
                continue;
            }

            $starts[] = [
                'title' => trim($match['title'][0]),
                // Content begins after the marker itself.
                'start' => $match[0][1] + strlen($match[0][0]),
            ];
        }

        // No markers at all (an older or partial OFP) — one section, not zero.
        if ($starts === []) {
            $html = self::sanitize($body);

            return $html === '' ? [] : [['title' => 'Text OFP', 'html' => $html]];
        }

        $sections = [];
        foreach ($starts as $i => $section) {
            $end = $starts[$i + 1]['start'] ?? null;
            $raw = $end === null
                ? substr($body, $section['start'])
                : substr($body, $section['start'], $end - $section['start']);

            $html = self::sanitize($raw);
            if ($html === '') {
                continue;
            }

            $sections[] = [
                'title' => $section['title'] === '' ? 'Section '.($i + 1) : $section['title'],
                'html'  => $html,
            ];
        }

        return $sections;
    }

    /**
     * Whether a fragment holds anything worth showing.
     *
     * Text alone is not the test: a section can be nothing but the SIGWX and
     * route chart `<img>`s, which `strip_tags()` reports as empty and which an
     * earlier version of this check silently discarded.
     */
    private static function hasContent(string $html): bool
    {
        return trim(strip_tags($html)) !== '' || preg_match('~<img\b~i', $html) === 1;
    }

    /**
     * The inner content of the document's `<pre>` wrapper.
     *
     * The whole OFP arrives inside a single `<div><pre>…</pre></div>`. Splitting
     * that into sections would leave each fragment holding an unbalanced `<pre>`,
     * so the wrapper is removed here and re-applied per section by the page.
     */
    private static function body(string $planHtml): string
    {
        if (preg_match('~<pre\b[^>]*>(?P<inner>.*)</pre>~s', $planHtml, $m) === 1) {
            return $m['inner'];
        }

        return $planHtml;
    }

    /** Strip every element and attribute outside the allowlist. */
    private static function sanitize(string $html): string
    {
        $html = preg_replace(self::MARKER, '', $html) ?? $html;

        if (!self::hasContent($html)) {
            return '';
        }

        $document = new DOMDocument();
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="pv-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (!$loaded) {
            // Unparseable: fall back to text, never to the raw string.
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        $root = $document->getElementById('pv-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::clean($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /** Depth-first clean of one element's children. */
    private static function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::cleanElement($child);

                continue;
            }

            // Comments carry no display value and can hide conditional markup.
            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private static function cleanElement(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);

        if (in_array($tag, self::DROPPED_SUBTREES, true)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        // `img` is allowlisted for attributes but only kept when its src is safe.
        $keep = in_array($tag, self::ALLOWED_TAGS, true)
            || ($tag === 'img' && self::isSafeUrl($element->getAttribute('src')));

        if (!$keep) {
            self::unwrap($element);

            return;
        }

        // Snapshot first: removing an attribute mutates the live collection.
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $allowed = array_merge(
                self::ALLOWED_ATTRIBUTES[$tag] ?? [],
                self::ALLOWED_ATTRIBUTES['*'],
            );

            if (!in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && !self::isSafeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        // Provider links leave the app; never hand them the opener.
        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        self::clean($element);
    }

    /** Replace an element with its children, keeping the text it wrapped. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }

        self::clean($element);

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }

    /** Only absolute http(s) and protocol-relative URLs — blocks javascript:/data:. */
    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        return preg_match('~^(https?://|//)~i', $url) === 1;
    }
}

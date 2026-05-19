<?php

namespace App\Support;

class HtmlSanitizer
{
    private const PROFILES = array(
        'minimal' => array(
            'tags' => array('p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'a'),
        ),
        'basic' => array(
            'tags' => array('p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'a', 'blockquote', 'code', 'pre', 'h2', 'h3', 'span', 'div'),
        ),
        'full' => array(
            'tags' => array('p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'a', 'blockquote', 'code', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div'),
        ),
    );

    public static function clean($html, $profile = 'basic')
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        $profile = isset(self::PROFILES[$profile]) ? (string) $profile : 'basic';
        $allowedTags = self::PROFILES[$profile]['tags'];

        if (!class_exists(\DOMDocument::class)) {
            return strip_tags($html, '<' . implode('><', $allowedTags) . '>');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
        libxml_use_internal_errors(true);
        $dom->loadHTML($wrapped, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $bodyList = $dom->getElementsByTagName('body');
        $body = $bodyList->length ? $bodyList->item(0) : null;
        if (!$body) {
            return strip_tags($html, '<' . implode('><', $allowedTags) . '>');
        }

        self::sanitizeNode($body, $allowedTags);

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    private static function sanitizeNode(\DOMNode $node, array $allowedTags)
    {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower((string) $node->nodeName);
            if (!in_array($tag, $allowedTags, true)) {
                self::unwrapNode($node);
                return;
            }

            self::sanitizeAttributes($node, $tag);
        }

        if (!$node->hasChildNodes()) {
            return;
        }

        $children = array();
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            self::sanitizeNode($child, $allowedTags);
        }
    }

    private static function sanitizeAttributes(\DOMNode $node, $tag)
    {
        if (!$node->attributes) {
            return;
        }

        $toRemove = array();
        foreach ($node->attributes as $attr) {
            $name = strtolower((string) $attr->nodeName);
            if (strpos($name, 'on') === 0) {
                $toRemove[] = $name;
                continue;
            }

            if ($name === 'style') {
                $safeStyle = self::filterStyle((string) $attr->nodeValue);
                if ($safeStyle === '') {
                    $toRemove[] = $name;
                } else {
                    $node->setAttribute('style', $safeStyle);
                }
                continue;
            }

            if ($tag === 'a') {
                if (!in_array($name, array('href', 'target', 'rel', 'title'), true)) {
                    $toRemove[] = $name;
                    continue;
                }

                if ($name === 'href') {
                    $href = trim((string) $attr->nodeValue);
                    if ($href === '' || !self::isSafeUrl($href)) {
                        $toRemove[] = $name;
                        continue;
                    }
                    $node->setAttribute('href', $href);
                }

                if ($name === 'target') {
                    $target = (string) $attr->nodeValue;
                    if ($target !== '_blank') {
                        $node->removeAttribute('target');
                    }
                }

                continue;
            }

            $toRemove[] = $name;
        }

        foreach ($toRemove as $name) {
            $node->attributes->removeNamedItem($name);
        }

        if ($tag === 'a' && $node->hasAttribute('target') && (string) $node->getAttribute('target') === '_blank') {
            $rel = trim((string) $node->getAttribute('rel'));
            $tokens = $rel !== '' ? preg_split('/\s+/', $rel) : array();
            $tokens = is_array($tokens) ? $tokens : array();
            if (!in_array('noopener', $tokens, true)) {
                $tokens[] = 'noopener';
            }
            if (!in_array('noreferrer', $tokens, true)) {
                $tokens[] = 'noreferrer';
            }
            $node->setAttribute('rel', trim(implode(' ', $tokens)));
        }
    }

    private static function filterStyle($style)
    {
        $style = (string) $style;
        if ($style === '') {
            return '';
        }

        $safe = array();
        $rules = preg_split('/;/', $style);
        foreach ($rules as $rule) {
            $rule = trim((string) $rule);
            if ($rule === '' || strpos($rule, ':') === false) {
                continue;
            }

            list($prop, $value) = array_map('trim', explode(':', $rule, 2));
            $prop = strtolower((string) $prop);
            $value = strtolower((string) $value);

            if ($prop === 'text-align' && in_array($value, array('left', 'right', 'center', 'justify'), true)) {
                $safe[] = 'text-align:' . $value;
            }
        }

        return implode(';', $safe);
    }

    private static function isSafeUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        if (stripos($url, 'javascript:') === 0) {
            return false;
        }

        if (stripos($url, 'data:') === 0) {
            return false;
        }

        if (strpos($url, '#') === 0) {
            return true;
        }

        $parsed = @parse_url($url);
        if (!$parsed) {
            return false;
        }

        if (empty($parsed['scheme'])) {
            return true;
        }

        $scheme = strtolower((string) $parsed['scheme']);
        return in_array($scheme, array('http', 'https', 'mailto'), true);
    }

    private static function unwrapNode(\DOMNode $node)
    {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
}


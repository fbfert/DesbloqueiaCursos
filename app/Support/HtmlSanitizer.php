<?php

namespace App\Support;

class HtmlSanitizer
{
    private const DISALLOWED_CONTENT_TAGS = array('script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'canvas');

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
            return self::sanitizeWithoutDom($html, $allowedTags);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
        libxml_use_internal_errors(true);
        $dom->loadHTML($wrapped);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $bodyList = $dom->getElementsByTagName('body');
        $body = $bodyList->length ? $bodyList->item(0) : null;
        if (!$body) {
            return self::sanitizeWithoutDom($html, $allowedTags);
        }

        $children = array();
        foreach ($body->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            self::sanitizeNode($child, $allowedTags);
        }

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        if (trim($out) === '') {
            $texto = trim(strip_tags($html));
            if ($texto === '') {
                return '';
            }
            return nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'));
        }

        return $out;
    }

    private static function sanitizeNode(\DOMNode $node, array $allowedTags)
    {
        if ($node->nodeType === \XML_ELEMENT_NODE) {
            $tag = strtolower((string) $node->nodeName);
            if (!in_array($tag, $allowedTags, true)) {
                if (self::isDisallowedContentTag($tag)) {
                    if ($node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                    return;
                }
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
        $alignment = null;
        foreach ($node->attributes as $attr) {
            $name = strtolower((string) $attr->nodeName);
            if (strpos($name, 'on') === 0) {
                $toRemove[] = $name;
                continue;
            }

            if ($name === 'class') {
                $alignment = self::extractAlignmentFromClass((string) $attr->nodeValue);
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
            if ($node->hasAttribute($name)) {
                $node->removeAttribute($name);
            }
        }

        if ($alignment !== null && self::tagSupportsAlignment($tag)) {
            self::applyTextAlign($node, $alignment);
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

    private static function sanitizeWithoutDom($html, array $allowedTags)
    {
        $html = (string) $html;
        $tokens = preg_split('/(<\/?[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($tokens)) {
            return '';
        }

        $output = '';
        $skipStack = array();
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if ($token[0] !== '<') {
                if (empty($skipStack)) {
                    $output .= $token;
                }
                continue;
            }

            if (preg_match('/^<\s*\/\s*([a-zA-Z0-9]+)\s*>$/', $token, $matches)) {
                $tag = strtolower((string) $matches[1]);
                if (!empty($skipStack)) {
                    $top = end($skipStack);
                    if ($top === $tag) {
                        array_pop($skipStack);
                    }
                    continue;
                }

                if (in_array($tag, $allowedTags, true)) {
                    $output .= '</' . $tag . '>';
                }
                continue;
            }

            if (!preg_match('/^<\s*([a-zA-Z0-9]+)\b([^>]*)>$/', $token, $matches)) {
                continue;
            }

            $tag = strtolower((string) $matches[1]);
            $attrSource = isset($matches[2]) ? (string) $matches[2] : '';

            if (self::isDisallowedContentTag($tag)) {
                $skipStack[] = $tag;
                continue;
            }

            if (!in_array($tag, $allowedTags, true)) {
                continue;
            }

            $attrs = self::sanitizeAttributesFallback($attrSource, $tag);
            $output .= '<' . $tag;
            if ($attrs !== '') {
                $output .= ' ' . $attrs;
            }
            $output .= in_array($tag, array('br'), true) ? '>' : '>';
        }

        if (trim($output) === '') {
            $texto = trim(strip_tags($html));
            if ($texto === '') {
                return '';
            }
            return nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'));
        }

        return $output;
    }

    private static function sanitizeAttributesFallback($attrSource, $tag)
    {
        $attrSource = (string) $attrSource;
        if (trim($attrSource) === '') {
            return '';
        }

        $attrs = array();
        $alignment = null;
        if (preg_match_all('/([a-zA-Z_:][a-zA-Z0-9:._-]*)(?:\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/', $attrSource, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower((string) $match[1]);
                $value = '';
                if (isset($match[3]) && $match[3] !== '') {
                    $value = (string) $match[3];
                } elseif (isset($match[4]) && $match[4] !== '') {
                    $value = (string) $match[4];
                } elseif (isset($match[5]) && $match[5] !== '') {
                    $value = (string) $match[5];
                }

                $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

                if (strpos($name, 'on') === 0) {
                    continue;
                }

                if ($name === 'class') {
                    $alignment = self::extractAlignmentFromClass($value);
                    continue;
                }

                if ($name === 'style') {
                    $safeStyle = self::filterStyle($value);
                    if ($safeStyle !== '') {
                        $attrs['style'] = $safeStyle;
                    }
                    continue;
                }

                if ($tag === 'a') {
                    if (!in_array($name, array('href', 'target', 'rel', 'title'), true)) {
                        continue;
                    }

                    if ($name === 'href') {
                        $href = trim($value);
                        if ($href === '' || !self::isSafeUrl($href)) {
                            continue;
                        }
                        $attrs['href'] = $href;
                        continue;
                    }

                    if ($name === 'target') {
                        if ($value === '_blank') {
                            $attrs['target'] = '_blank';
                        }
                        continue;
                    }

                    if ($name === 'rel') {
                        $attrs['rel'] = $value;
                        continue;
                    }

                    if ($name === 'title') {
                        $attrs['title'] = $value;
                        continue;
                    }
                }
            }
        }

        if ($alignment !== null && self::tagSupportsAlignment($tag)) {
            $existingStyle = isset($attrs['style']) ? (string) $attrs['style'] : '';
            $safeStyle = self::filterStyle($existingStyle . ';text-align:' . $alignment);
            if ($safeStyle !== '') {
                $attrs['style'] = $safeStyle;
            }
        }

        if ($tag === 'a' && !empty($attrs['target']) && $attrs['target'] === '_blank') {
            $rel = isset($attrs['rel']) ? trim((string) $attrs['rel']) : '';
            $tokens = $rel !== '' ? preg_split('/\s+/', $rel) : array();
            $tokens = is_array($tokens) ? $tokens : array();
            if (!in_array('noopener', $tokens, true)) {
                $tokens[] = 'noopener';
            }
            if (!in_array('noreferrer', $tokens, true)) {
                $tokens[] = 'noreferrer';
            }
            $attrs['rel'] = trim(implode(' ', $tokens));
        }

        $parts = array();
        foreach ($attrs as $name => $value) {
            $parts[] = $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return implode(' ', $parts);
    }

    private static function tagSupportsAlignment($tag)
    {
        return in_array($tag, array('p', 'div', 'blockquote', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true);
    }

    private static function extractAlignmentFromClass($className)
    {
        $className = trim((string) $className);
        if ($className === '') {
            return null;
        }

        if (preg_match('/\bql-align-(left|center|right|justify)\b/i', $className, $matches)) {
            return strtolower((string) $matches[1]);
        }

        return null;
    }

    private static function applyTextAlign(\DOMNode $node, $alignment)
    {
        $alignment = strtolower(trim((string) $alignment));
        if (!in_array($alignment, array('left', 'center', 'right', 'justify'), true)) {
            return;
        }

        $existingStyle = '';
        if ($node->attributes && $node->attributes->getNamedItem('style')) {
            $existingStyle = (string) $node->getAttribute('style');
        }

        $safeStyle = self::filterStyle($existingStyle . ';text-align:' . $alignment);
        if ($safeStyle === '') {
            if ($node->hasAttribute('style')) {
                $node->removeAttribute('style');
            }
            return;
        }

        $node->setAttribute('style', $safeStyle);
    }

    private static function isDisallowedContentTag($tag)
    {
        return in_array(strtolower((string) $tag), self::DISALLOWED_CONTENT_TAGS, true);
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

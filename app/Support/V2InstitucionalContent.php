<?php

namespace App\Support;

use App\Core\Helpers;

/**
 * Preparação segura do conteúdo institucional para o ambiente V2.
 *
 * As páginas institucionais têm o conteúdo real gerenciado no backend
 * (tabela `paginas`, editada em /admin/paginas). Aqui o HTML é normalizado,
 * sanitizado e preparado para renderização no shell V2.
 *
 * O sanitizador continua bloqueando script/style/iframe/object/embed/form,
 * atributos on*, URLs `javascript:`/`data:` e conteúdo executável. Quando a
 * página contém um documento HTML completo, a extração isola o conteúdo do
 * <body> antes da sanitização para evitar que texto de <head>/<title> vaze para
 * a interface.
 */
class V2InstitucionalContent
{
    /** Domínios confiáveis para embed de mapa (whitelist explícita). */
    private const MAP_HOSTS = array('google.com', 'www.google.com', 'maps.google.com');

    /**
     * @return array{html:string, mapEmbedUrl:?string, mapLinkUrl:?string}
     */
    public static function build($rawHtml)
    {
        $raw = (string) $rawHtml;
        $source = self::extractVisibleHtml($raw);

        // O título canônico da página vem da coluna `titulo` e é exibido pela
        // view como um único <h1>. Se o editor repetir esse título no conteúdo,
        // removemos apenas a primeira ocorrência para não duplicar a manchete.
        $html = self::stripFirstH1(Helpers::renderSafeHtml($source, 'full'));

        $mapEmbedUrl = self::extractMapEmbed($source);

        return array(
            'html' => $html,
            'mapEmbedUrl' => $mapEmbedUrl,
            'mapLinkUrl' => self::buildMapLinkUrl($mapEmbedUrl),
        );
    }

    private static function extractVisibleHtml($rawHtml)
    {
        $raw = (string) $rawHtml;
        if ($raw === '') {
            return '';
        }

        if (stripos($raw, '<html') === false && stripos($raw, '<body') === false && stripos($raw, '<head') === false) {
            return $raw;
        }

        $body = self::extractBodyInnerHtml($raw);
        return $body !== '' ? $body : $raw;
    }

    private static function extractBodyInnerHtml($rawHtml)
    {
        $raw = (string) $rawHtml;
        if (!class_exists(\DOMDocument::class)) {
            if (preg_match('#<body\b[^>]*>(.*)</body>#is', $raw, $matches)) {
                return (string) $matches[1];
            }

            return $raw;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        $wrapped = '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $raw . '</body></html>';
        libxml_use_internal_errors(true);
        $dom->loadHTML($wrapped);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $bodyList = $dom->getElementsByTagName('body');
        $body = $bodyList->length ? $bodyList->item(0) : null;
        if (!$body) {
            return $raw;
        }

        $html = '';
        foreach ($body->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return trim($html) !== '' ? $html : $raw;
    }

    private static function stripFirstH1($html)
    {
        return preg_replace('#\s*<h1\b[^>]*>.*?</h1>#is', '', (string) $html, 1);
    }

    /**
     * Extrai a primeira URL de embed de mapa cujo host esteja na whitelist e o
     * esquema seja HTTPS. Qualquer outra origem retorna null (sem embed).
     */
    private static function extractMapEmbed($rawHtml)
    {
        $raw = (string) $rawHtml;
        if (stripos($raw, '<iframe') === false) {
            return null;
        }

        if (!preg_match_all('#<iframe\b[^>]*\bsrc\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $raw, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $src = isset($match[2]) && $match[2] !== '' ? $match[2] : (isset($match[3]) ? $match[3] : '');
            $url = trim(html_entity_decode((string) $src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($url === '') {
                continue;
            }

            $parsed = @parse_url($url);
            if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
                continue;
            }

            if (strtolower((string) $parsed['scheme']) !== 'https') {
                continue;
            }

            $host = strtolower((string) $parsed['host']);
            if (!in_array($host, self::MAP_HOSTS, true)) {
                continue;
            }

            $path = strtolower((string) (isset($parsed['path']) ? $parsed['path'] : '/'));
            if (strpos($path, '/maps') !== 0) {
                continue;
            }

            return $url;
        }

        return null;
    }

    private static function buildMapLinkUrl($embedUrl)
    {
        $embedUrl = trim((string) $embedUrl);
        if ($embedUrl === '') {
            return null;
        }

        $parsed = @parse_url($embedUrl);
        if (!$parsed || empty($parsed['host'])) {
            return null;
        }

        $scheme = !empty($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : 'https';
        if ($scheme !== 'https') {
            $scheme = 'https';
        }

        $path = isset($parsed['path']) && $parsed['path'] !== '' ? (string) $parsed['path'] : '/maps';
        if (strpos($path, '/maps/embed') === 0) {
            $path = '/maps';
        }

        $params = array();
        if (!empty($parsed['query'])) {
            parse_str((string) $parsed['query'], $params);
            if (!is_array($params)) {
                $params = array();
            }
        }

        unset($params['output']);

        $query = http_build_query($params);
        $link = $scheme . '://' . strtolower((string) $parsed['host']) . $path;
        if ($query !== '') {
            $link .= '?' . $query;
        }

        return $link;
    }
}

<?php

namespace App\Support;

/**
 * Utilitários para editar modelos de e-mail que são documentos HTML completos
 * (com <!doctype>/<html>/<head>/<body>) sem destruir o "shell" do documento.
 *
 * A estratégia é separar o documento em três partes por manipulação de string
 * (nunca reserializando via DOM): tudo até o fim da tag <body ...> (prefixo),
 * o conteúdo interno do corpo (editável) e o fechamento </body>...</html>
 * (sufixo). Na gravação, o documento é reconstruído por concatenação simples,
 * preservando byte a byte doctype, head, meta charset, title, estilos e atributos.
 */
class EmailHtmlDocument
{
    /** Detecta se o HTML é um documento completo (possui shell de documento). */
    public static function isFullDocument($html)
    {
        return (bool) preg_match('/<\s*(!doctype|html|head|body)\b/i', (string) $html);
    }

    /**
     * Separa o documento em prefixo, conteúdo interno do body e sufixo.
     *
     * @return array{prefix:string,inner:string,suffix:string,is_full:bool}
     */
    public static function split($html)
    {
        $html = (string) $html;

        if (!preg_match('/<body\b[^>]*>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            // Sem <body>: trata todo o conteúdo como corpo editável (fragmento).
            return array('prefix' => '', 'inner' => $html, 'suffix' => '', 'is_full' => false);
        }

        $openTag = $match[0][0];
        $openStart = (int) $match[0][1];
        $innerStart = $openStart + strlen($openTag);

        $closeStart = stripos($html, '</body', $innerStart);
        if ($closeStart === false) {
            // Sem </body>: preserva o que houver antes do corpo e edita o restante.
            return array(
                'prefix' => substr($html, 0, $innerStart),
                'inner' => substr($html, $innerStart),
                'suffix' => '',
                'is_full' => true,
            );
        }

        return array(
            'prefix' => substr($html, 0, $innerStart),
            'inner' => substr($html, $innerStart, $closeStart - $innerStart),
            'suffix' => substr($html, $closeStart),
            'is_full' => true,
        );
    }

    /**
     * Sanitização mínima e NÃO destrutiva para HTML de e-mail.
     *
     * Remove apenas construções perigosas (script/iframe/object/embed, handlers
     * on*, URLs javascript:) e PRESERVA integralmente doctype, head, meta, title,
     * style, tabelas, atributos inline (style, width, height, align, border,
     * cellpadding, cellspacing, cores), links, imagens e placeholders {..}/{{..}}.
     * Não reparseia nem reformata o documento.
     */
    public static function sanitize($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        // Remove tags perigosas com seu conteúdo.
        $html = preg_replace('#<\s*(script|iframe|object|embed|noscript)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
        // Remove versões sem fechamento/self-closing das mesmas tags.
        $html = preg_replace('#<\s*(script|iframe|object|embed|noscript)\b[^>]*/?>#is', '', $html);

        // Remove atributos de evento inline (onclick, onload, onerror, ...).
        $html = preg_replace('#\son[a-z0-9_-]+\s*=\s*"[^"]*"#i', '', $html);
        $html = preg_replace("#\son[a-z0-9_-]+\s*=\s*'[^']*'#i", '', $html);
        $html = preg_replace('#\son[a-z0-9_-]+\s*=\s*[^\s>]+#i', '', $html);

        // Neutraliza URLs javascript: em href/src (aspas duplas, simples ou sem aspas).
        $html = preg_replace('#\b(href|src)\s*=\s*"\s*javascript:[^"]*"#i', '$1="#"', $html);
        $html = preg_replace("#\b(href|src)\s*=\s*'\s*javascript:[^']*'#i", '$1="#"', $html);
        $html = preg_replace('#\b(href|src)\s*=\s*javascript:[^\s>]+#i', '$1="#"', $html);

        return $html;
    }
}

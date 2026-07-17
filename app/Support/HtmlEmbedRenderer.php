<?php

namespace App\Support;

/**
 * Prepara o HTML bruto do tipo de conteudo "html" para ser exibido dentro de
 * um iframe sandboxed no aluno. Nao sanitiza o HTML (isso e feito de forma
 * deliberada para o tipo "html" - ver App\Services\ConteudoCursoService) -
 * apenas injeta um script minimo que avisa o documento pai (via postMessage)
 * da altura real do conteudo, para o iframe se redimensionar sem scrollbar.
 */
class HtmlEmbedRenderer
{
    public static function wrap($html, $frameId)
    {
        $html = (string) $html;
        $frameId = (string) $frameId;

        $resizeScript = '<script>(function(){'
            . 'function postHeight(){'
            . 'try{'
            . 'var height = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);'
            . 'parent.postMessage({source:"desbloqueia-html-embed",frameId:' . json_encode($frameId) . ',height:height}, "*");'
            . '}catch(e){}'
            . '}'
            . 'if (window.ResizeObserver) { new ResizeObserver(postHeight).observe(document.documentElement); }'
            . 'window.addEventListener("load", postHeight);'
            . 'document.addEventListener("DOMContentLoaded", postHeight);'
            . 'setTimeout(postHeight, 300);'
            . 'setTimeout(postHeight, 1000);'
            . '})();</script>';

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $resizeScript . '</body>', $html, 1);
        }

        return $html . $resizeScript;
    }
}

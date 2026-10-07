<?php
/**
 * Tabelas do conteúdo rico roláveis na horizontal sem perder a semântica de
 * tabela: envolve cada <table> já sanitizado num <div class="aula-tabela">
 * (overflow-x no wrapper, a tabela continua display:table). Só acrescenta
 * marcação fixa nossa; o HTML de entrada já passou por Helpers::renderSafeHtml.
 */

if (!function_exists('caderno_tabelas_rolaveis')) {
    function caderno_tabelas_rolaveis(string $html): string
    {
        if (stripos($html, '<table') === false) {
            return $html;
        }
        $html = (string) preg_replace('/<table\b/i', '<div class="aula-tabela"><table', $html);

        return (string) preg_replace('/<\/table\s*>/i', '</table></div>', $html);
    }
}

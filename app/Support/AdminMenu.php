<?php

namespace App\Support;

/**
 * Regras do menu lateral do admin.
 *
 * Aqui mora só a decisão de qual item fica aceso. A lista de grupos continua em
 * resources/views/admin/_shell.php, que é onde se lê o menu inteiro de uma vez —
 * espalhá-la entre arquivo de configuração e template tornaria mais difícil
 * responder "o que aparece no menu?".
 *
 * O que está aqui está aqui por ser TESTÁVEL: dentro da view, esta regra só
 * podia ser verificada renderizando a página inteira, com sessão, RBAC e banco.
 */
class AdminMenu
{
    /**
     * Qual destino corresponde ao caminho atual.
     *
     * Até 23/08/2026 a view decidia com `strpos($caminho, $href) === 0`, e isso
     * errava de duas maneiras:
     *
     *   1. Em /admin/certificados/templates acendiam DOIS itens, porque
     *      /admin/certificados também é prefixo. Valia igual para os quatro
     *      itens da Norminha, para E-mails, Financeiro e Frontend.
     *   2. /admin/cursos ficaria aceso em /admin/cursos-antigos: prefixo de
     *      texto não respeita fronteira de caminho.
     *
     * A regra correta tem duas partes. Casa quem for igual ao caminho ou for
     * pasta dele — a barra é o que separa "/admin/cursos" de
     * "/admin/cursos-antigos". E entre os que casam vence o mais longo, que é
     * sempre o filho, nunca o pai.
     *
     * @param  string   $caminho  caminho da requisição, ex.: /admin/emails/modelos
     * @param  string[] $hrefs    todos os destinos do menu
     * @return string   o href aceso, ou '' quando nenhum corresponde
     */
    public static function hrefAtivo($caminho, array $hrefs)
    {
        $caminho = rtrim((string) $caminho, '/');
        if ($caminho === '') {
            $caminho = '/';
        }

        $ativo = '';
        foreach ($hrefs as $href) {
            $href = rtrim((string) $href, '/');
            if ($href === '') {
                continue;
            }

            $casa = $caminho === $href || strpos($caminho, $href . '/') === 0;
            if ($casa && strlen($href) > strlen($ativo)) {
                $ativo = $href;
            }
        }

        return $ativo;
    }
}

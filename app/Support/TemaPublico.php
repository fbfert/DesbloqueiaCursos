<?php

namespace App\Support;

/**
 * Tema visual das páginas públicas e da área do aluno.
 *
 * O caderno é o único tema (mudança OpenSpec tema-caderno-unico): TEMA_PUBLICO e a
 * prévia ?tema= não têm mais efeito. Toda view V2 tem par no caderno; a queda para
 * `v2/<nome>` em caminhoView() fica só como rede de segurança.
 */
class TemaPublico
{
    const TEMA = 'caderno';

    public static function caminhoView(string $tema, string $nome, string $baseViews): string
    {
        if ($tema === 'caderno' && is_file($baseViews . '/caderno/' . $nome . '.php')) {
            return 'caderno/' . $nome;
        }

        return 'v2/' . $nome;
    }

    public static function ativo(): string
    {
        return self::TEMA;
    }

    public static function view(string $nome): string
    {
        return self::caminhoView(self::ativo(), $nome, BASE_PATH . '/resources/views');
    }

    /** Sem alternância de tema não há prévia; mantido para o aviso do layout. */
    public static function emPrevia(): bool
    {
        return false;
    }
}

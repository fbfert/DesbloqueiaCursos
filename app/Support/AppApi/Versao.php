<?php

namespace App\Support\AppApi;

use App\Core\Env;

/**
 * Versão do app: o cabeçalho `X-App-Version: 1.0.0 (1)` traz o nome e, entre
 * parênteses, o número de build (versionCode do Android). A versão mínima é
 * comparada pelo build.
 */
class Versao
{
    /** Build informado no cabeçalho, ou null quando ausente/ilegível. */
    public static function build($cabecalho)
    {
        $cabecalho = trim((string) $cabecalho);
        if ($cabecalho === '') {
            return null;
        }
        if (preg_match('/\((\d{1,9})\)\s*$/', $cabecalho, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\d{1,9}$/', $cabecalho)) {
            return (int) $cabecalho;
        }

        return null;
    }

    public static function minimaAndroid()
    {
        return max(0, (int) Env::get('APP_MOBILE_VERSAO_MINIMA_ANDROID', '1'));
    }

    public static function atualAndroid()
    {
        return max(self::minimaAndroid(), (int) Env::get('APP_MOBILE_VERSAO_ATUAL_ANDROID', '1'));
    }

    /**
     * Precisa atualizar? Sem cabeçalho legível não bloqueia: quem não se
     * identifica (ferramenta, teste manual) não é o app desatualizado.
     */
    public static function exigeAtualizacao($cabecalho)
    {
        $build = self::build($cabecalho);
        return $build !== null && $build < self::minimaAndroid();
    }
}

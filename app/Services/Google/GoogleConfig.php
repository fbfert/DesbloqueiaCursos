<?php

namespace App\Services\Google;

/**
 * Configuração do login com Google (config/auth.php, seção `google`).
 * Lida a cada chamada: barato, e os testes podem trocar as variáveis de ambiente.
 */
class GoogleConfig
{
    public static function todas()
    {
        $config = require BASE_PATH . '/config/auth.php';

        return isset($config['google']) && is_array($config['google']) ? $config['google'] : array();
    }

    public static function get($chave, $padrao = null)
    {
        $config = self::todas();

        return array_key_exists($chave, $config) ? $config[$chave] : $padrao;
    }

    /** Login pelo Google no site: exige client ID e client secret. */
    public static function siteAtivo()
    {
        return (bool) self::get('site_ativo', false);
    }

    /** Login pelo Google no app: exige ao menos um client ID do app. */
    public static function appAtivo()
    {
        return (bool) self::get('app_ativo', false);
    }
}

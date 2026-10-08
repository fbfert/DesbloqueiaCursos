<?php

namespace App\Support;

/**
 * Identidade da requisição autenticada do app (/api/app/*).
 *
 * O app não usa sessão PHP: o middleware `auth.app` valida o Bearer e grava
 * aqui, só em memória e só durante esta requisição, quem é o usuário. Controllers
 * da API leem daqui e passam o id EXPLICITAMENTE para os Services do site —
 * nenhum deles depende de Session::get('usuario_id') na API.
 */
class AppAuth
{
    private static $usuarioId = null;
    private static $token = null;

    public static function definir($usuarioId, array $token)
    {
        self::$usuarioId = (int) $usuarioId;
        self::$token = $token;
    }

    public static function limpar()
    {
        self::$usuarioId = null;
        self::$token = null;
    }

    public static function usuarioId()
    {
        return self::$usuarioId;
    }

    public static function autenticado()
    {
        return self::$usuarioId !== null && self::$usuarioId > 0;
    }

    public static function deviceId()
    {
        return is_array(self::$token) && isset(self::$token['device_id']) ? (string) self::$token['device_id'] : null;
    }

    public static function tokenId()
    {
        return is_array(self::$token) && isset(self::$token['id']) ? (int) self::$token['id'] : null;
    }

    public static function familia()
    {
        return is_array(self::$token) && isset(self::$token['familia']) ? (string) self::$token['familia'] : null;
    }
}

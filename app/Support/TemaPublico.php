<?php

namespace App\Support;

use App\Core\Session;

/**
 * Seleciona o tema visual das páginas públicas ("caderno" ou "v2").
 *
 * A chave TEMA_PUBLICO define o tema padrão; um administrador com permissão
 * pode forçar uma prévia na própria sessão via ?tema=caderno|v2.
 * Página sem view no tema cai na V2 (entrega por etapas).
 */
class TemaPublico
{
    const SESSAO_PREVIA = 'tema_previa';

    public static function decidir(string $config, ?string $previa): string
    {
        if ($previa === 'caderno' || $previa === 'v2') {
            return $previa;
        }

        return $config === 'caderno' ? 'caderno' : 'v2';
    }

    public static function caminhoView(string $tema, string $nome, string $baseViews): string
    {
        if ($tema === 'caderno' && is_file($baseViews . '/caderno/' . $nome . '.php')) {
            return 'caderno/' . $nome;
        }

        return 'v2/' . $nome;
    }

    public static function ativo(): string
    {
        static $config = null;
        if ($config === null) {
            $app = require BASE_PATH . '/config/app.php';
            $config = isset($app['tema_publico']) ? (string) $app['tema_publico'] : 'v2';
        }

        $previa = Session::get(self::SESSAO_PREVIA);

        return self::decidir($config, is_string($previa) ? $previa : null);
    }

    public static function view(string $nome): string
    {
        return self::caminhoView(self::ativo(), $nome, BASE_PATH . '/resources/views');
    }

    public static function emPrevia(): bool
    {
        return Session::get(self::SESSAO_PREVIA) === 'caderno';
    }

    /**
     * @param mixed    $usuarioId
     * @param callable $temPermissao recebe o id do usuário e devolve bool
     */
    public static function aplicarPrevia(?string $param, $usuarioId, callable $temPermissao): void
    {
        if (!$temPermissao($usuarioId)) {
            Session::forget(self::SESSAO_PREVIA);
            return;
        }

        if ($param === 'caderno') {
            Session::put(self::SESSAO_PREVIA, 'caderno');
        } elseif ($param === 'v2') {
            Session::forget(self::SESSAO_PREVIA);
        }
    }
}

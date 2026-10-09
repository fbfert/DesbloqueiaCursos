<?php

namespace App\Support;

/**
 * Destinos do login V2 por origem em lista branca — usado pelo login por senha
 * (AuthController) e pelo login com Google (GoogleAuthController).
 *
 * Segurança: NÃO aceita URL do usuário. Só a flag `origem` (comparada a tokens
 * fixos) e um `redirect` validado por SafeRedirect::v2Path (caminho interno /v2/...).
 */
class LoginDestino
{
    /** Para onde voltar depois de um login recusado. */
    public static function erro($origem, $redirect)
    {
        $origem = trim((string) $origem);

        // Retorno V2 seguro (Fase 2.13): preserva ?redirect= para que o reenvio do
        // login mantenha o destino.
        $retorno = SafeRedirect::v2Path($redirect);
        if ($retorno !== null && ($origem === 'v2' || $origem === 'v2_aluno')) {
            return '/v2/login?redirect=' . rawurlencode($retorno);
        }

        $permitidos = array(
            'v2' => '/v2/login',
            // Veio da Área do Aluno V2: preserva a intenção para o retorno pós-login.
            'v2_aluno' => '/v2/login?origem=v2_aluno',
        );

        return isset($permitidos[$origem]) ? $permitidos[$origem] : '/login';
    }

    /**
     * Destino V2 depois de um login bem-sucedido, ou null para o destino padrão
     * do sistema (por permissões).
     *
     *  1) `redirect` válido e interno (/v2/...) → destino solicitado;
     *  2) `origem=v2_aluno` → `/v2/aluno`;
     *  3) `origem=v2` sem redirect válido → tela V2 de escolha `/v2/pos-login`;
     *  4) demais origens → null.
     */
    public static function sucesso($origem, $redirect)
    {
        $origem = trim((string) $origem);
        if ($origem !== 'v2' && $origem !== 'v2_aluno') {
            return null;
        }

        $retorno = SafeRedirect::v2Path($redirect);
        if ($retorno !== null) {
            return $retorno;
        }

        if ($origem === 'v2_aluno') {
            return '/v2/aluno';
        }

        return '/v2/pos-login';
    }
}

<?php

namespace App\Support;

/**
 * Destinos do login do tema — usado pelo login por senha (AuthController) e pelo
 * login com Google (GoogleAuthController).
 *
 * Todo login passa pelo tema (mudança tema-caderno-unico): `/login` redireciona
 * para `/v2/login`. Segurança: NÃO aceita URL do usuário. Só a flag `origem`
 * (comparada a tokens fixos) e um `redirect` validado por
 * SafeRedirect::caminhoInterno (tema, backoffice ou rota legada do aluno).
 */
class LoginDestino
{
    /** Para onde voltar depois de um login recusado. */
    public static function erro($origem, $redirect)
    {
        $retorno = SafeRedirect::caminhoInterno($redirect);
        if ($retorno !== null) {
            return '/v2/login?redirect=' . rawurlencode($retorno);
        }

        // Veio da Área do Aluno: preserva a intenção para o retorno pós-login.
        if (trim((string) $origem) === 'v2_aluno') {
            return '/v2/login?origem=v2_aluno';
        }

        return '/v2/login';
    }

    /**
     * Destino depois de um login bem-sucedido.
     *
     *  1) `redirect` interno válido → destino solicitado;
     *  2) área do perfil (`$areaPerfil`: painel do admin, do professor ou do revisor);
     *  3) `origem=v2_aluno` → `/v2/aluno`;
     *  4) demais → tela de escolha do tema `/v2/pos-login`.
     *
     * @param string|null $areaPerfil destino do backoffice pelas permissões, ou null
     *                                para quem é só aluno
     */
    public static function sucesso($origem, $redirect, ?string $areaPerfil = null)
    {
        $retorno = SafeRedirect::caminhoInterno($redirect);
        if ($retorno !== null) {
            return $retorno;
        }

        if ($areaPerfil !== null && $areaPerfil !== '' && $areaPerfil !== '/') {
            return $areaPerfil;
        }

        if (trim((string) $origem) === 'v2_aluno') {
            return '/v2/aluno';
        }

        return '/v2/pos-login';
    }
}

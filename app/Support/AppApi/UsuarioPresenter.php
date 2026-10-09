<?php

namespace App\Support\AppApi;

/**
 * Objeto `usuario` do contrato (login e `/me`). Nunca expõe hash de senha,
 * token de recuperação ou CPF completo.
 */
class UsuarioPresenter
{
    public static function usuario(array $usuario)
    {
        return array(
            'id' => (int) $usuario['id'],
            'nome' => (string) ($usuario['nome'] ?? ''),
            'email' => (string) ($usuario['email'] ?? ''),
            'cpf' => Formato::cpfMascarado($usuario['cpf'] ?? ''),
            'telefone' => Formato::texto($usuario['telefone'] ?? null),
            'cidade' => Formato::texto($usuario['cidade'] ?? null),
            'estado' => Formato::texto($usuario['estado'] ?? null),
            // Dados que o usuário ainda precisa informar (login-google): 'cpf' enquanto
            // a conta não tiver CPF — sem ele os certificados ficam retidos.
            'pendencias' => \App\Models\Usuario::semCpf($usuario) ? array('cpf') : array(),
        );
    }

    /** Par de tokens (login e refresh). */
    public static function tokens(array $par)
    {
        return array(
            'access_token' => (string) $par['access_token'],
            'access_expira_em' => Tempo::isoDeTimestamp((int) $par['access_expira_em']),
            'refresh_token' => (string) $par['refresh_token'],
            'refresh_expira_em' => Tempo::isoDeTimestamp((int) $par['refresh_expira_em']),
        );
    }
}

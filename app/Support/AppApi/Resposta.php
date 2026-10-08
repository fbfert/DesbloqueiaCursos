<?php

namespace App\Support\AppApi;

use App\Core\Response;

/**
 * Respostas JSON da API do app, no formato do contrato:
 *   sucesso: {"data": ..., "meta": {...}}
 *   erro:    {"erro": {"codigo": "...", "mensagem": "...", "campos": {...}}}
 */
class Resposta
{
    const MENSAGENS = array(
        'nao_autenticado' => 'Faça login para continuar.',
        'token_expirado' => 'Sua sessão expirou. Renove o acesso.',
        'sessao_revogada' => 'Sua sessão foi encerrada. Entre novamente.',
        'sem_acesso' => 'Você não tem acesso a este conteúdo.',
        'nao_encontrado' => 'Não encontrado.',
        'validacao' => 'Verifique os dados informados.',
        'muitas_tentativas' => 'Muitas tentativas. Aguarde um pouco e tente novamente.',
        'atualizacao_obrigatoria' => 'Há uma nova versão do app. Atualize para continuar.',
        'erro_interno' => 'Não foi possível concluir a solicitação. Tente novamente em instantes.',
    );

    public static function ok($dados, $status = 200, ?array $meta = null)
    {
        $corpo = array('data' => $dados);
        if ($meta !== null) {
            $corpo['meta'] = $meta;
        }
        return self::json($corpo, $status);
    }

    public static function erro($codigo, $mensagem = null, $status = 400, ?array $campos = null, array $cabecalhos = array())
    {
        $codigo = (string) $codigo;
        if ($mensagem === null || $mensagem === '') {
            $mensagem = isset(self::MENSAGENS[$codigo]) ? self::MENSAGENS[$codigo] : 'Não foi possível concluir a solicitação.';
        }
        $erro = array('codigo' => $codigo, 'mensagem' => (string) $mensagem);
        if ($campos !== null) {
            $erro['campos'] = empty($campos) ? new \stdClass() : $campos;
        }
        return self::json(array('erro' => $erro), $status, $cabecalhos);
    }

    public static function validacao(array $campos, $mensagem = null)
    {
        return self::erro('validacao', $mensagem, 422, $campos);
    }

    public static function json(array $corpo, $status = 200, array $cabecalhos = array())
    {
        $json = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            $json = '{"erro":{"codigo":"erro_interno","mensagem":"Não foi possível concluir a solicitação."}}';
            $status = 500;
        }

        return new Response($json, $status, array_merge(array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ), $cabecalhos));
    }

    /** Envia direto, fora do Router (tratador de erro do PHP). */
    public static function enviarErroInterno()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header_remove('Set-Cookie');
        }
        self::erro('erro_interno', null, 500)->send();
    }
}

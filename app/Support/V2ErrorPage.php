<?php

namespace App\Support;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Páginas de erro V2 (Fase 2.13).
 *
 * Quando a requisição está no ambiente V2 (`/v2/...`), rende a casca de erro V2
 * (layout V2, sem expor IDs internos, caminhos de arquivo, SQL ou stack trace),
 * com saídas seguras (catálogo V2 / Minha Área V2). Fora do V2, mantém a página
 * de erro legada intacta.
 */
class V2ErrorPage
{
    public static function isV2Path($path)
    {
        $path = (string) $path;
        return $path === '/v2' || strpos($path, '/v2/') === 0;
    }

    public static function notFound($path, $titulo = null, $mensagem = null)
    {
        return self::render($path, 404, 'errors/404', array(
            'erroTitulo' => $titulo !== null ? $titulo : 'Página não encontrada',
            'erroMensagem' => $mensagem !== null ? $mensagem : 'O conteúdo que você procura não está disponível ou foi movido.',
            'erroIcone' => 'ti-error-404',
        ));
    }

    public static function forbidden($path, $titulo = null, $mensagem = null)
    {
        return self::render($path, 403, 'errors/403', array(
            'erroTitulo' => $titulo !== null ? $titulo : 'Acesso negado',
            'erroMensagem' => $mensagem !== null ? $mensagem : 'Você não tem permissão para acessar este conteúdo.',
            'erroIcone' => 'ti-lock',
        ));
    }

    private static function render($path, $status, $legacyView, array $v2Data)
    {
        if (!self::isV2Path($path)) {
            return new Response(View::render($legacyView, array(
                'title' => isset($v2Data['erroTitulo']) ? $v2Data['erroTitulo'] : 'Erro',
            )), $status);
        }

        $usuarioId = (int) Session::get('usuario_id', 0);
        $usuarioNome = trim((string) Session::get('usuario_nome', ''));
        $primeiro = '';
        if ($usuarioNome !== '') {
            $partes = preg_split('/\s+/', $usuarioNome);
            $primeiro = ($partes && !empty($partes[0])) ? (string) $partes[0] : $usuarioNome;
        }

        $sessionPerfis = Session::get('usuario_perfis', array());
        $hasAdmin = (bool) Session::get('usuario_admin') || (bool) Session::get('is_admin') || in_array('admin', $sessionPerfis, true);
        $hasProf = (bool) Session::get('usuario_professor') || (bool) Session::get('is_professor') || in_array('professor', $sessionPerfis, true);
        $areaHref = $hasAdmin ? '/admin' : ($hasProf ? '/professor/dashboard' : V2Nav::ALUNO);

        $data = array_merge(array(
            'pageTitle' => (isset($v2Data['erroTitulo']) ? $v2Data['erroTitulo'] : 'Erro') . ' — Desbloqueia Cursos',
            'loggedIn' => $usuarioId > 0,
            'usuarioPrimeiroNome' => $primeiro,
            'areaHref' => $areaHref,
        ), $v2Data);

        return new Response(View::render('v2/erro', $data, false), $status);
    }
}

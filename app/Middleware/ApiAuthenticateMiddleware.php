<?php

namespace App\Middleware;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;

/**
 * Autenticação para rotas de API que respondem JSON.
 *
 * POR QUE ISTO EXISTE, e não se reaproveita o `auth`:
 *
 * AuthenticateMiddleware devolve 302 para /login e V2AuthenticateMiddleware
 * devolve 302 para /v2/login. Num navegador isso é o comportamento certo. Num
 * fetch() é uma armadilha: o navegador SEGUE o redirect, recebe 200 com o HTML
 * da tela de login, e o JavaScript conclui que a requisição deu certo — depois
 * quebra ao tentar ler JSON de uma página HTML, ou pior, trata a ausência de
 * erro como sucesso silencioso.
 *
 * Aqui a resposta é 401 com corpo JSON, que é o que o contrato da API promete
 * e o que o cliente consegue tratar.
 *
 * A identidade continua vindo EXCLUSIVAMENTE de Session::get('usuario_id') —
 * mesma fonte dos middlewares web. Nenhuma rota de API aceita usuário por
 * corpo, query ou cabeçalho.
 */
class ApiAuthenticateMiddleware implements MiddlewareInterface
{
    private $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    public function handle(Request $request, callable $next)
    {
        if (Session::get('usuario_id')) {
            return $next();
        }

        $context = array(
            'path' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        );

        $this->auditService->record(
            'seguranca.autenticacao.ausente_api',
            'request',
            null,
            $context,
            null,
            $request->ip(),
            $request->userAgent()
        );
        Logger::error('seguranca.autenticacao.ausente_api', $context);

        return Response::json(array(
            'ok' => false,
            'erro' => 'nao_autenticado',
            'mensagem' => 'Faça login para continuar.',
        ), 401);
    }
}

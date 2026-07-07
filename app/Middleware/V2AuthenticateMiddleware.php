<?php

namespace App\Middleware;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Support\SafeRedirect;

/**
 * Autenticação para rotas protegidas do ambiente V2 (Fase 2.13).
 *
 * Diferença essencial em relação ao AuthenticateMiddleware legado: visitante não
 * autenticado é enviado para `/v2/login` (nunca para `/login` do V1),
 * preservando o destino V2 original em `?redirect=` (validado por
 * SafeRedirect::v2Path — apenas caminhos internos `/v2/...`).
 *
 * Segurança:
 * - o retorno embutido NUNCA vem de `redirect/next/return` do usuário; é montado
 *   a partir do próprio caminho+query da requisição atual (que já é interno);
 * - só é embutido em GET. Em métodos não-GET (POST) não se recompõe um destino
 *   (o corpo não pode ser reenviado por um GET), evitando redirecionamento
 *   silencioso/inseguro — o usuário volta ao login e reinicia a ação com segurança;
 * - nunca altera o comportamento do admin, APIs, webhooks ou rotas legadas.
 */
class V2AuthenticateMiddleware implements MiddlewareInterface
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
            'seguranca.autenticacao.ausente_v2',
            'request',
            null,
            $context,
            null,
            $request->ip(),
            $request->userAgent()
        );
        Logger::error('seguranca.autenticacao.ausente_v2', $context);

        Session::flash('errors', array('auth' => 'Faça login para continuar.'));

        // Retorno seguro só para GET; o alvo é o próprio caminho+query interno.
        $loginUrl = '/v2/login';
        if ($request->method() === 'GET') {
            $path = (string) $request->path();
            $query = $request->queryAll();
            if (is_array($query) && !empty($query)) {
                $path .= '?' . http_build_query($query);
            }
            $loginUrl = SafeRedirect::v2LoginWithReturn($path);
        }

        return Response::redirect($loginUrl);
    }
}

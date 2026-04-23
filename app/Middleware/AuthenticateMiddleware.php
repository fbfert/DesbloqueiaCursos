<?php

namespace App\Middleware;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;

class AuthenticateMiddleware implements MiddlewareInterface
{
    private $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    public function handle(Request $request, callable $next)
    {
        if (!Session::get('usuario_id')) {
            $context = array(
                'path' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            );

            $this->auditService->record(
                'seguranca.autenticacao.ausente',
                'request',
                null,
                $context,
                null,
                $request->ip(),
                $request->userAgent()
            );

            Logger::error('seguranca.autenticacao.ausente', $context);
            Session::flash('errors', array('auth' => 'Faça login para continuar.'));
            return Response::redirect('/login');
        }

        return $next();
    }
}

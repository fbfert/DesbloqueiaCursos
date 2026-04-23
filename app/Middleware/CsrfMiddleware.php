<?php

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;

class CsrfMiddleware implements MiddlewareInterface
{
    private $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    public function handle(Request $request, callable $next)
    {
        if ($request->method() !== 'POST') {
            return $next();
        }

        if (Csrf::validate($request->input('_token'))) {
            return $next();
        }

        $usuarioId = Session::get('usuario_id');
        $context = array(
            'path' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        );

        $this->auditService->record(
            'seguranca.csrf.rejeitado',
            'request',
            null,
            $context,
            $usuarioId,
            $request->ip(),
            $request->userAgent()
        );

        Logger::error('seguranca.csrf.rejeitado', array_merge($context, array('usuario_id' => $usuarioId)));
        Session::flash('errors', array('csrf' => 'Sua sessao expirou. Recarregue a pagina e tente novamente.'));

        $referer = isset($_SERVER['HTTP_REFERER']) ? trim((string) $_SERVER['HTTP_REFERER']) : '';
        $redirectTo = $this->sanitizeRedirect($referer, '/');

        return Response::redirect($redirectTo);
    }

    private function sanitizeRedirect($url, $fallback)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return $fallback;
        }

        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['path'])) {
            return $fallback;
        }

        $path = $parsed['path'];
        if (!empty($parsed['query'])) {
            $path .= '?' . $parsed['query'];
        }

        return $path;
    }
}

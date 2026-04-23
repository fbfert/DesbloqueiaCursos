<?php

namespace App\Middleware;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\RbacService;
use App\Services\AuditService;

class PermissionMiddleware implements MiddlewareInterface
{
    private $permissionSlug;
    private $rbacService;
    private $auditService;

    public function __construct($permissionSlug)
    {
        $this->permissionSlug = $permissionSlug;
        $this->rbacService = new RbacService();
        $this->auditService = new AuditService();
    }

    public function handle(Request $request, callable $next)
    {
        $usuarioId = Session::get('usuario_id');

        if (!$usuarioId) {
            Logger::error('seguranca.permissao.sem_autenticacao', array(
                'permission' => $this->permissionSlug,
                'path' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
            ));
            Session::flash('errors', array('auth' => 'Faça login para continuar.'));
            return Response::redirect('/login');
        }

        if (!$this->rbacService->userHasPermission($usuarioId, $this->permissionSlug)) {
            $context = array(
                'permission' => $this->permissionSlug,
                'path' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            );

            $this->auditService->record(
                'seguranca.permissao.negada',
                'request',
                null,
                $context,
                $usuarioId,
                $request->ip(),
                $request->userAgent()
            );

            Logger::error('seguranca.permissao.negada', array_merge($context, array('usuario_id' => $usuarioId)));
            return new Response(View::render('errors/403', array(
                'title' => 'Acesso negado',
            )), 403);
        }

        return $next();
    }
}

<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\RbacService;

class PermissionMiddleware implements MiddlewareInterface
{
    private $permissionSlug;
    private $rbacService;

    public function __construct($permissionSlug)
    {
        $this->permissionSlug = $permissionSlug;
        $this->rbacService = new RbacService();
    }

    public function handle(Request $request, callable $next)
    {
        $usuarioId = Session::get('usuario_id');

        if (!$usuarioId) {
            Session::flash('errors', array('auth' => 'Faça login para continuar.'));
            return Response::redirect('/login');
        }

        if (!$this->rbacService->userHasPermission($usuarioId, $this->permissionSlug)) {
            return new Response(View::render('errors/403', array(
                'title' => 'Acesso negado',
            )), 403);
        }

        return $next();
    }
}

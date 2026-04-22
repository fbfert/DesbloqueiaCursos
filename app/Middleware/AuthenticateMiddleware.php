<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AuthenticateMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next)
    {
        if (!Session::get('usuario_id')) {
            Session::flash('errors', array('auth' => 'Faça login para continuar.'));
            return Response::redirect('/login');
        }

        return $next();
    }
}

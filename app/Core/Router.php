<?php

namespace App\Core;

class Router
{
    private $routes = array();

    public function get($path, $handler)
    {
        $this->add('GET', $path, $handler);
    }

    public function post($path, $handler)
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request)
    {
        $key = $request->method() . ' ' . $request->path();

        if (!isset($this->routes[$key])) {
            return new Response(View::render('errors/404', array(
                'title' => 'Pagina nao encontrada',
            )), 404);
        }

        $handler = $this->routes[$key];

        if (is_array($handler)) {
            $controller = new $handler[0]();
            return call_user_func(array($controller, $handler[1]), $request);
        }

        return call_user_func($handler, $request);
    }

    private function add($method, $path, $handler)
    {
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            $path = '/';
        }

        $this->routes[$method . ' ' . $path] = $handler;
    }
}

<?php

namespace App\Core;

use App\Controllers\PaginasController;

class Router
{
    private $routes = array();
    private $patternRoutes = array();

    public function get($path, $handler, array $middleware = array())
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, array $middleware = array())
    {
        if (!in_array('csrf', $middleware, true)) {
            array_unshift($middleware, 'csrf');
        }
        $this->add('POST', $path, $handler, $middleware);
    }

    public function postWithoutCsrf($path, $handler, array $middleware = array())
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function dispatch(Request $request)
    {
        if ($request->method() === 'GET') {
            $cupom = trim((string) $request->query('cupom', ''));
            if ($cupom !== '') {
                return Response::redirect('/cupom?codigo=' . urlencode($cupom));
            }
        }

        $key = $request->method() . ' ' . $request->path();

        if (!isset($this->routes[$key])) {
            $matchedPatternRoute = $this->matchPatternRoute($request);
            if ($matchedPatternRoute) {
                $route = $matchedPatternRoute['route'];
                $routeParams = $matchedPatternRoute['params'];
                $request = new Request(
                    $request->method(),
                    $request->path(),
                    $request->queryAll(),
                    $request->all(),
                    $request->server(),
                    $routeParams,
                    $request->rawBody(),
                    $request->contentType()
                );
                $handler = $route['handler'];
                $middleware = $this->buildMiddlewareStack(isset($route['middleware']) ? $route['middleware'] : array());

                $runner = function () use ($handler, $request) {
                    if (is_array($handler)) {
                        $controller = new $handler[0]();
                        return call_user_func(array($controller, $handler[1]), $request);
                    }

                    return call_user_func($handler, $request);
                };

                try {
                    return $this->executeMiddlewareStack($middleware, $request, $runner);
                } catch (\Throwable $exception) {
                    return $this->handleAdminException($request, $exception);
                }
            }

            if ($request->method() === 'GET') {
                $paginasController = new PaginasController();
                $paginaResponse = $paginasController->showByRoute($request);
                if ($paginaResponse instanceof Response) {
                    return $paginaResponse;
                }
            }

            // Fase 2.13 — 404 no ambiente V2 mantém o visual V2; fora do V2,
            // permanece a página legada.
            return \App\Support\V2ErrorPage::notFound($request->path());
        }

        $route = $this->routes[$key];
        $handler = $route['handler'];
        $middleware = $this->buildMiddlewareStack(isset($route['middleware']) ? $route['middleware'] : array());

        $runner = function () use ($handler, $request) {
            if (is_array($handler)) {
                $controller = new $handler[0]();
                return call_user_func(array($controller, $handler[1]), $request);
            }

            return call_user_func($handler, $request);
        };

        try {
            return $this->executeMiddlewareStack($middleware, $request, $runner);
        } catch (\Throwable $exception) {
            return $this->handleAdminException($request, $exception);
        }
    }

    private function handleAdminException(Request $request, \Throwable $exception)
    {
        $context = array(
            'path' => $request->path(),
            'method' => $request->method(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        );

        Logger::error('admin.route.error', $context);

        if (strpos($request->path(), '/admin') !== 0) {
            throw $exception;
        }

        Session::flash('errors', array(
            'Ocorreu um erro ao processar a solicitação. Verifique os dados e tente novamente.',
        ));

        if ($request->method() === 'POST') {
            $old = $request->all();
            unset($old['_token'], $old['csrf_token']);
            Session::flash('old_input', $old);

            $referer = isset($_SERVER['HTTP_REFERER']) ? trim((string) $_SERVER['HTTP_REFERER']) : '';
            $redirectTo = $this->sanitizeInternalRedirect($referer, '');

            if ($redirectTo !== '') {
                return Response::redirect($redirectTo);
            }
        }

        return new Response(View::render('errors/500', array(
            'title' => 'Erro interno',
            'debug' => false,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        )), 500);
    }

    private function sanitizeInternalRedirect($url, $fallback)
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

        if (strpos($path, '/admin') !== 0) {
            return $fallback;
        }

        if (!empty($parsed['query'])) {
            $path .= '?' . $parsed['query'];
        }

        return $path;
    }

    private function add($method, $path, $handler, array $middleware = array())
    {
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            $path = '/';
        }

        if (strpos($path, '{') !== false && strpos($path, '}') !== false) {
            $this->patternRoutes[] = array(
                'method' => $method,
                'path' => $path,
                'regex' => $this->compilePattern($path),
                'handler' => $handler,
                'middleware' => $middleware,
            );
            return;
        }

        $this->routes[$method . ' ' . $path] = array(
            'handler' => $handler,
            'middleware' => $middleware,
        );
    }

    private function matchPatternRoute(Request $request)
    {
        $path = $request->path();
        foreach ($this->patternRoutes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = array();
            foreach ($matches as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                $params[$key] = $value;
            }

            return array('route' => $route, 'params' => $params);
        }

        return null;
    }

    private function compilePattern($path)
    {
        $escaped = preg_quote($path, '#');
        $escaped = preg_replace('#\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}#', '(?P<$1>[^/]+)', $escaped);
        return '#^' . $escaped . '$#';
    }

    private function buildMiddlewareStack(array $middlewareDefinitions)
    {
        $stack = array();

        foreach ($middlewareDefinitions as $definition) {
            $stack[] = $this->resolveMiddleware($definition);
        }

        return $stack;
    }

    private function resolveMiddleware($definition)
    {
        if (is_object($definition)) {
            return $definition;
        }

        $class = null;
        $arguments = array();

        if (is_string($definition)) {
            $parts = explode(':', $definition, 2);
            $alias = $parts[0];
            $arguments = isset($parts[1]) && $parts[1] !== '' ? explode(',', $parts[1]) : array();

            $map = array(
                'auth' => '\\App\\Middleware\\AuthenticateMiddleware',
                'auth.v2' => '\\App\\Middleware\\V2AuthenticateMiddleware',
                'csrf' => '\\App\\Middleware\\CsrfMiddleware',
                'permission' => '\\App\\Middleware\\PermissionMiddleware',
            );

            $class = isset($map[$alias]) ? $map[$alias] : $alias;
        } elseif (is_array($definition) && isset($definition[0])) {
            $class = $definition[0];
            $arguments = array_slice($definition, 1);
        }

        if (!$class || !class_exists($class)) {
            throw new \InvalidArgumentException('Middleware invalido: ' . (is_string($definition) ? $definition : 'objeto'));
        }

        $reflection = new \ReflectionClass($class);
        return $reflection->newInstanceArgs($arguments);
    }

    private function executeMiddlewareStack(array $stack, Request $request, callable $runner)
    {
        $dispatcher = function ($index) use (&$dispatcher, $stack, $request, $runner) {
            if (!isset($stack[$index])) {
                return call_user_func($runner);
            }

            $middleware = $stack[$index];

            return $middleware->handle($request, function () use (&$dispatcher, $index) {
                return $dispatcher($index + 1);
            });
        };

        return $dispatcher(0);
    }
}

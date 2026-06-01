<?php

namespace App\Core;

class App
{
    private $basePath;
    private $router;
    private static $instance;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        $this->router = new Router();
        self::$instance = $this;
    }

    public function get($path, $handler, array $middleware = array())
    {
        $this->router->get($path, $handler, $middleware);
    }

    public function post($path, $handler, array $middleware = array())
    {
        $this->router->post($path, $handler, $middleware);
    }

    public function postWithoutCsrf($path, $handler, array $middleware = array())
    {
        $this->router->postWithoutCsrf($path, $handler, $middleware);
    }

    public function run()
    {
        $request = Request::capture();
        $response = $this->router->dispatch($request);
        $response->send();
    }

    public function basePath()
    {
        return $this->basePath;
    }

    public static function instance()
    {
        return self::$instance;
    }
}

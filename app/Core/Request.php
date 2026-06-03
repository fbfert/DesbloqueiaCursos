<?php

namespace App\Core;

class Request
{
    private $method;
    private $path;
    private $query;
    private $body;
    private $server;
    private $routeParams;

    public function __construct($method, $path, array $query, array $body, array $server = array(), array $routeParams = array())
    {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->routeParams = $routeParams;

        if ($this->path === '/') {
            $this->path = '/';
        }
    }

    public static function capture()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);

        return new self(
            isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            $path,
            $_GET,
            $_POST,
            $_SERVER
        );
    }

    public function method()
    {
        return $this->method;
    }

    public function path()
    {
        return $this->path;
    }

    public function input($key, $default = null)
    {
        return array_key_exists($key, $this->body) ? $this->body[$key] : $default;
    }

    public function query($key, $default = null)
    {
        return array_key_exists($key, $this->query) ? $this->query[$key] : $default;
    }

    public function route($key, $default = null)
    {
        return array_key_exists($key, $this->routeParams) ? $this->routeParams[$key] : $default;
    }

    public function routeAll()
    {
        return $this->routeParams;
    }

    public function all()
    {
        return $this->body;
    }

    public function queryAll()
    {
        return $this->query;
    }

    public function ip()
    {
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }

        return isset($this->server['REMOTE_ADDR']) ? $this->server['REMOTE_ADDR'] : null;
    }

    public function userAgent()
    {
        return isset($this->server['HTTP_USER_AGENT']) ? $this->server['HTTP_USER_AGENT'] : null;
    }

    public function header($name, $default = null)
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (array_key_exists($normalized, $this->server)) {
            return $this->server[$normalized];
        }

        return $default;
    }

    public function server()
    {
        return $this->server;
    }
}

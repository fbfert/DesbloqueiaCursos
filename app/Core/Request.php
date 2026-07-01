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
    private $rawBody;
    private $contentType;

    public function __construct($method, $path, array $query, array $body, array $server = array(), array $routeParams = array(), $rawBody = null, $contentType = null)
    {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->routeParams = $routeParams;
        $this->rawBody = $rawBody;
        $this->contentType = $contentType;

        if ($this->path === '/') {
            $this->path = '/';
        }
    }

    public static function capture()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $rawBody = file_get_contents('php://input');
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : (isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : '');
        $body = $_POST;

        if (is_string($contentType) && stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode((string) $rawBody, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            $path,
            $_GET,
            $body,
            $_SERVER,
            array(),
            $rawBody,
            $contentType
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

    public function rawBody()
    {
        return $this->rawBody;
    }

    public function contentType()
    {
        return $this->contentType;
    }
}

<?php

namespace App\Core;

class Response
{
    private $content;
    private $status;
    private $headers;

    public function __construct($content = '', $status = 200, array $headers = array())
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function json(array $data, $status = 200)
    {
        return new self(json_encode($data), $status, array(
            'Content-Type' => 'application/json; charset=utf-8',
        ));
    }

    public static function redirect($url, $status = 302)
    {
        return new self('', $status, array(
            'Location' => $url,
        ));
    }

    public function send()
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->content;
    }
}

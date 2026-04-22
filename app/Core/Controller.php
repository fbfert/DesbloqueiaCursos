<?php

namespace App\Core;

class Controller
{
    protected function view($template, array $data = array())
    {
        return new Response(View::render($template, $data));
    }

    protected function json(array $data, $status = 200)
    {
        return Response::json($data, $status);
    }

    protected function redirect($url)
    {
        return Response::redirect($url);
    }
}

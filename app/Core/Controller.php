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
        $this->preserveOldInputOnValidationFailure();
        return Response::redirect($url);
    }

    private function preserveOldInputOnValidationFailure()
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            return;
        }

        if (!Session::hasFlash('errors')) {
            return;
        }

        if (Session::hasFlash('old_input')) {
            return;
        }

        $old = isset($_POST) && is_array($_POST) ? $_POST : array();
        if (!$old) {
            return;
        }

        $old = $this->removeSensitiveFields($old);
        Session::flash('old_input', $old);
    }

    private function removeSensitiveFields(array $values)
    {
        $sanitized = array();

        foreach ($values as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if ($normalizedKey === '_token' || $normalizedKey === 'csrf_token') {
                continue;
            }

            if ($this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->removeSensitiveFields($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey($key)
    {
        return strpos($key, 'senha') !== false
            || strpos($key, 'password') !== false
            || strpos($key, 'token') !== false;
    }
}

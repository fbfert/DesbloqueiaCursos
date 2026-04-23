<?php

namespace App\Core;

class Csrf
{
    const SESSION_KEY = '_csrf_token';

    public static function token()
    {
        Session::start();

        $token = Session::get(self::SESSION_KEY);
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function field()
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate($token)
    {
        $stored = Session::get(self::SESSION_KEY);
        if (!$stored || !is_string($token) || trim($token) === '') {
            return false;
        }

        return hash_equals((string) $stored, (string) $token);
    }

    public static function injectIntoHtml($html)
    {
        if (stripos($html, '<form') === false || stripos($html, 'method=') === false) {
            return $html;
        }

        return preg_replace_callback(
            '/(<form\b[^>]*\bmethod=(["\'])post\2[^>]*>)/i',
            function ($matches) {
                $formTag = $matches[1];
                if (stripos($formTag, 'name="_token"') !== false || stripos($formTag, "name='_token'") !== false) {
                    return $formTag;
                }

                return $formTag . self::field();
            },
            $html
        );
    }
}

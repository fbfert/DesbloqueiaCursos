<?php

namespace App\Core;

class ErrorHandler
{
    private static $debug = false;

    public static function register($debug = false)
    {
        self::$debug = (bool) $debug;

        error_reporting(E_ALL);
        ini_set('display_errors', self::$debug ? '1' : '0');

        set_exception_handler(array(__CLASS__, 'handleException'));
        set_error_handler(array(__CLASS__, 'handleError'));
    }

    public static function handleError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::render(500, $message, $file, $line);
        return true;
    }

    public static function handleException($exception)
    {
        self::render(500, $exception->getMessage(), $exception->getFile(), $exception->getLine());
    }

    private static function render($status, $message, $file, $line)
    {
        Logger::error('app.error', array(
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ));

        http_response_code($status);

        echo View::render('errors/500', array(
            'title' => 'Erro interno',
            'debug' => self::$debug,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        ));

        exit;
    }
}

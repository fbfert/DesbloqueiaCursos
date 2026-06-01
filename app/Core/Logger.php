<?php

namespace App\Core;

class Logger
{
    public static function info($message, array $context = array())
    {
        self::write('info', $message, $context);
    }

    public static function error($message, array $context = array())
    {
        self::write('error', $message, $context);
    }

    public static function warning($message, array $context = array())
    {
        self::write('warning', $message, $context);
    }

    private static function write($level, $message, array $context)
    {
        $line = json_encode(array(
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ));

        $path = BASE_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';
        file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
    }
}

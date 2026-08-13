<?php

namespace App\Core;

use PDO;

class Database
{
    private static $connection;

    public static function connection()
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require BASE_PATH . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new PDO($dsn, $config['username'], $config['password'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ));

        return self::$connection;
    }

    /**
     * Injeta a conexao PDO.
     *
     * Usado pelos testes (tests/Unit/*), que apontam para um banco de testes
     * sem depender de config/database.php.
     */
    public static function setConnection(?PDO $connection)
    {
        self::$connection = $connection;
    }
}

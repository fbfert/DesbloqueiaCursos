<?php

namespace App\Core;

class Validator
{
    public static function email($value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function cpf($value)
    {
        if (is_array($value) || is_object($value)) {
            return false;
        }

        $cpf = preg_replace('/[^0-9]+/', '', trim((string) $value));

        if (self::allowTestCpfs() && self::isKnownTestCpf($cpf)) {
            return true;
        }

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t <= 10; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) substr($cpf, $i, 1) * (($t + 1) - $i);
            }

            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) substr($cpf, $t, 1) !== $digit) {
                return false;
            }
        }

        return true;
    }

    private static function allowTestCpfs()
    {
        $value = strtolower(trim((string) Env::get('ALLOW_TEST_CPFS', 'false')));
        return in_array($value, array('1', 'true', 'yes', 'on'), true);
    }

    private static function isKnownTestCpf($cpf)
    {
        return in_array($cpf, array(
            '12345678900',
            '12345678901',
            '12345678909',
            '11111111111',
            '22222222222',
            '33333333333',
            '44444444444',
            '55555555555',
            '66666666666',
            '77777777777',
            '88888888888',
            '99999999999',
        ), true);
    }

    public static function onlyDigits($value)
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    public static function upperName($value)
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }

        return strtoupper($value);
    }
}

<?php

namespace App\Support;

use App\Core\Env;

class Crypto
{
    public static function encrypt($plainText)
    {
        $plainText = (string) $plainText;
        if ($plainText === '') {
            return null;
        }

        $key = self::key();
        if ($key === null || !extension_loaded('openssl')) {
            return null;
        }

        $cipher = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($cipher);
        if ($ivLength <= 0) {
            return null;
        }

        $iv = random_bytes($ivLength);
        $encrypted = openssl_encrypt($plainText, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return null;
        }

        $payload = array(
            'iv' => base64_encode($iv),
            'value' => base64_encode($encrypted),
            'mac' => base64_encode(hash_hmac('sha256', $iv . $encrypted, $key, true)),
        );

        return base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function decrypt($cipherText)
    {
        $cipherText = trim((string) $cipherText);
        if ($cipherText === '') {
            return null;
        }

        $key = self::key();
        if ($key === null || !extension_loaded('openssl')) {
            return null;
        }

        $decoded = base64_decode($cipherText, true);
        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            return null;
        }

        $iv = isset($payload['iv']) ? base64_decode((string) $payload['iv'], true) : false;
        $value = isset($payload['value']) ? base64_decode((string) $payload['value'], true) : false;
        $mac = isset($payload['mac']) ? base64_decode((string) $payload['mac'], true) : false;
        if ($iv === false || $value === false || $mac === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $iv . $value, $key, true);
        if (!hash_equals($expected, $mac)) {
            return null;
        }

        $plainText = openssl_decrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plainText === false) {
            return null;
        }

        return $plainText;
    }

    public static function mask($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', max(4, $length));
        }

        return substr($value, 0, 4) . str_repeat('*', max(8, $length - 8)) . substr($value, -4);
    }

    public static function hasKey()
    {
        return self::key() !== null;
    }

    private static function key()
    {
        $rawKey = trim((string) Env::get('APP_KEY', ''));
        if ($rawKey === '') {
            $appConfig = require BASE_PATH . '/config/app.php';
            $rawKey = trim((string) ($appConfig['key'] ?? ''));
        }

        if ($rawKey === '') {
            return null;
        }

        if (strpos($rawKey, 'base64:') === 0) {
            $decoded = base64_decode(substr($rawKey, 7), true);
            if ($decoded !== false && $decoded !== '') {
                $rawKey = $decoded;
            }
        }

        if ($rawKey === '') {
            return null;
        }

        return hash('sha256', $rawKey, true);
    }
}

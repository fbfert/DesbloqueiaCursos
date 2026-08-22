<?php

namespace App\Support;

/**
 * Normalizacao e validacao de numero de WhatsApp brasileiro para E.164.
 *
 * Regras aplicadas (Brasil, +55):
 *   - DDD valido: 11..19, 21, 22, 24, 27, 28, 31..35, 37, 38, 41..49,
 *     51, 53, 54, 55, 61..69, 71, 73..75, 77, 79, 81, 82..89, 91..99.
 *     A lista abaixo e a oficial da Anatel; DDD fora dela e recusado.
 *   - Celular tem 9 digitos e comeca com 9. Numero de 8 digitos que
 *     comeca com 6..9 e um celular antigo: o nono digito e acrescentado.
 *   - Fixo (8 digitos comecando com 2..5) e recusado: WhatsApp e celular.
 *
 * Sempre devolve +55DDDNUMERO (14 caracteres) ou null.
 */
class Whatsapp
{
    /** DDDs em uso no Brasil. */
    private static $ddds = array(
        11, 12, 13, 14, 15, 16, 17, 18, 19,
        21, 22, 24, 27, 28,
        31, 32, 33, 34, 35, 37, 38,
        41, 42, 43, 44, 45, 46, 47, 48, 49,
        51, 53, 54, 55,
        61, 62, 63, 64, 65, 66, 67, 68, 69,
        71, 73, 74, 75, 77, 79,
        81, 82, 83, 84, 85, 86, 87, 88, 89,
        91, 92, 93, 94, 95, 96, 97, 98, 99,
    );

    /**
     * Devolve o numero em E.164 (+55DDDNUMERO) ou null se invalido.
     */
    public static function normalizar($valor)
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor);

        if ($digitos === '') {
            return null;
        }

        // Prefixo internacional digitado como 0055 ou 55.
        if (strlen($digitos) > 11 && strpos($digitos, '0055') === 0) {
            $digitos = substr($digitos, 4);
        }
        if (strlen($digitos) > 11 && strpos($digitos, '55') === 0) {
            $digitos = substr($digitos, 2);
        }

        // Zero de operadora antes do DDD.
        if (strlen($digitos) === 11 && $digitos[0] === '0') {
            $digitos = substr($digitos, 1);
        }
        if (strlen($digitos) === 12 && $digitos[0] === '0') {
            $digitos = substr($digitos, 1);
        }

        if (strlen($digitos) !== 10 && strlen($digitos) !== 11) {
            return null;
        }

        $ddd = (int) substr($digitos, 0, 2);
        if (!in_array($ddd, self::$ddds, true)) {
            return null;
        }

        $numero = substr($digitos, 2);

        if (strlen($numero) === 8) {
            // Celular antigo (6,7,8,9) ganha o nono digito. Fixo (2..5) nao serve.
            if (!in_array($numero[0], array('6', '7', '8', '9'), true)) {
                return null;
            }
            $numero = '9' . $numero;
        }

        if (strlen($numero) !== 9 || $numero[0] !== '9') {
            return null;
        }

        return '+55' . $ddd . $numero;
    }

    public static function valido($valor)
    {
        return self::normalizar($valor) !== null;
    }

    /**
     * Formato de leitura para telas e e-mails: (11) 98888-7777.
     */
    public static function formatar($e164)
    {
        $digitos = preg_replace('/\D+/', '', (string) $e164);
        if (strpos($digitos, '55') === 0 && strlen($digitos) === 13) {
            $digitos = substr($digitos, 2);
        }

        if (strlen($digitos) !== 11) {
            return (string) $e164;
        }

        return '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 5) . '-' . substr($digitos, 7);
    }
}

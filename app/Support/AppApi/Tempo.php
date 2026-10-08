<?php

namespace App\Support\AppApi;

use App\Core\Env;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Datas da API do app.
 *
 * As colunas DATETIME do banco não têm fuso. O contrato pede ISO-8601 com fuso
 * (`2026-10-07T18:30:00-03:00`), então toda data "ingênua" é interpretada no fuso
 * da aplicação (APP_TIMEZONE, padrão America/Sao_Paulo). As tabelas novas do app
 * (082) são gravadas por aqui também, para que gravar e comparar usem o mesmo
 * fuso — independentemente do fuso do PHP (UTC na VPS) e do MySQL (UTC−3).
 */
class Tempo
{
    private static $relogio = null;

    /** Só para testes: fixa o "agora" (timestamp Unix). null volta ao relógio real. */
    public static function fixar($timestamp)
    {
        self::$relogio = $timestamp === null ? null : (int) $timestamp;
    }

    public static function agoraTs()
    {
        return self::$relogio !== null ? self::$relogio : time();
    }

    public static function fuso()
    {
        $nome = trim((string) Env::get('APP_TIMEZONE', 'America/Sao_Paulo'));
        try {
            return new DateTimeZone($nome !== '' ? $nome : 'America/Sao_Paulo');
        } catch (\Exception $e) {
            return new DateTimeZone('America/Sao_Paulo');
        }
    }

    /** Timestamp → 'Y-m-d H:i:s' no fuso da aplicação (para gravar). */
    public static function sql($timestamp = null)
    {
        $ts = $timestamp === null ? self::agoraTs() : (int) $timestamp;
        return (new DateTimeImmutable('@' . $ts))->setTimezone(self::fuso())->format('Y-m-d H:i:s');
    }

    /** 'Y-m-d H:i:s' (fuso da aplicação) → timestamp, ou null. */
    public static function timestamp($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strpos($valor, '0000-00-00') === 0) {
            return null;
        }
        try {
            return (new DateTimeImmutable($valor, self::fuso()))->getTimestamp();
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Data/hora do banco → ISO-8601 com fuso, ou null. */
    public static function iso($valor)
    {
        $ts = self::timestamp($valor);
        return $ts === null ? null : self::isoDeTimestamp($ts);
    }

    /**
     * Data/hora GRAVADA PELO PHP (date('Y-m-d H:i:s')) → ISO-8601 com fuso.
     *
     * O site grava parte das colunas com NOW() do MySQL (horário de Brasília) e
     * parte com date() do PHP, que na VPS roda sem fuso configurado (UTC) — ver
     * docs/2026-08-15-fuso-horario-php-mysql.md. Colunas do segundo grupo
     * (concluido_em do progresso, horários do quiz, envio/correção de avaliação,
     * emissão de certificado) são lidas no fuso do PHP e apresentadas no fuso da
     * aplicação, para que o instante saia certo no app.
     */
    public static function isoPhp($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strpos($valor, '0000-00-00') === 0) {
            return null;
        }
        try {
            $data = new DateTimeImmutable($valor, new DateTimeZone(date_default_timezone_get()));
            return $data->setTimezone(self::fuso())->format('c');
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function isoDeTimestamp($timestamp)
    {
        if ($timestamp === null) {
            return null;
        }
        return (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone(self::fuso())->format('c');
    }

    /** Data sem hora (DATE) → 'Y-m-d', ou null. */
    public static function data($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '' || strpos($valor, '0000-00-00') === 0) {
            return null;
        }
        return substr($valor, 0, 10);
    }
}

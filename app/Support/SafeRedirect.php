<?php

namespace App\Support;

/**
 * Validação de retorno interno seguro para o ambiente V2 (Fase 2.13).
 *
 * Aceita SOMENTE caminhos internos que comecem por `/v2/` (ou o próprio `/v2`),
 * preservando a query string. Bloqueia URLs externas, protocolo explícito,
 * esquema `javascript:`/`data:`, barras duplas (`//`, protocol-relative),
 * barras invertidas e caracteres de controle. Nunca aceita host/esquema.
 *
 * O objetivo é impedir open redirect: o único destino possível é uma rota V2
 * interna. Qualquer entrada inválida retorna null, cabendo ao chamador aplicar
 * um fallback fixo (ex.: `/v2/aluno/`).
 */
class SafeRedirect
{
    public static function v2Path($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        // Caracteres de controle (inclui CR/LF/TAB) e barra invertida → rejeita.
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $raw)) {
            return null;
        }

        // Esquemas perigosos em qualquer posição.
        $lower = strtolower($raw);
        if (strpos($lower, 'javascript:') !== false || strpos($lower, 'data:') !== false || strpos($lower, 'vbscript:') !== false) {
            return null;
        }

        // Precisa ser caminho absoluto interno; bloqueia protocol-relative `//`.
        if ($raw[0] !== '/' || strpos($raw, '//') === 0) {
            return null;
        }

        $parsed = parse_url($raw);
        if ($parsed === false) {
            return null;
        }

        // Nunca aceita esquema ou host (URL absoluta).
        if (isset($parsed['scheme']) || isset($parsed['host'])) {
            return null;
        }

        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';
        if ($path !== '/v2' && strpos($path, '/v2/') !== 0) {
            return null;
        }

        $out = $path;
        if (isset($parsed['query']) && $parsed['query'] !== '') {
            $out .= '?' . $parsed['query'];
        }

        return $out;
    }

    /**
     * Constrói `/v2/login` com o retorno seguro embutido (rawurlencode). Quando o
     * retorno é inválido/ausente, devolve `/v2/login` puro.
     */
    public static function v2LoginWithReturn($rawReturn)
    {
        $safe = self::v2Path($rawReturn);
        if ($safe === null) {
            return '/v2/login';
        }

        return '/v2/login?redirect=' . rawurlencode($safe);
    }
}

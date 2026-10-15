<?php

namespace App\Support;

/**
 * Validação de retorno interno seguro (Fase 2.13; ampliada em tema-caderno-unico).
 *
 * - v2Path(): aceita SOMENTE caminhos que comecem por `/v2/` (ou o próprio `/v2`).
 * - caminhoInterno(): aceita também as áreas do backoffice (`/admin`, `/professor`,
 *   `/revisor`) e as rotas legadas do aluno, que redirecionam para o tema. É o
 *   retorno usado pelo login do tema, para onde todo login exigido passa a levar.
 *
 * As duas preservam a query string e bloqueiam URLs externas, protocolo explícito,
 * esquema `javascript:`/`data:`, barras duplas (`//`, protocol-relative), barras
 * invertidas, caracteres de controle e segmentos `.`/`..`. Nunca aceitam host/esquema.
 * Entrada inválida retorna null, cabendo ao chamador aplicar um fallback fixo.
 */
class SafeRedirect
{
    /** Prefixos aceitos por caminhoInterno(), além de /v2. */
    const PREFIXOS_INTERNOS = array(
        '/admin', '/professor', '/revisor',
        '/meus-cursos', '/aluno', '/area-curso', '/pedidos', '/minha-conta', '/minha-pagina',
        '/checkout', '/inscricao', '/certificados', '/cursos', '/categorias',
    );

    public static function v2Path($raw)
    {
        $partes = self::validar($raw);
        if ($partes === null || !self::temPrefixo($partes['path'], array('/v2'))) {
            return null;
        }

        return $partes['url'];
    }

    public static function caminhoInterno($raw)
    {
        $partes = self::validar($raw);
        if ($partes === null || !self::temPrefixo($partes['path'], array_merge(array('/v2'), self::PREFIXOS_INTERNOS))) {
            return null;
        }

        return $partes['url'];
    }

    /**
     * Constrói `/v2/login` com o retorno seguro embutido (rawurlencode). Quando o
     * retorno é inválido/ausente, devolve `/v2/login` puro.
     */
    public static function v2LoginWithReturn($rawReturn)
    {
        $safe = self::caminhoInterno($rawReturn);
        if ($safe === null) {
            return '/v2/login';
        }

        return '/v2/login?redirect=' . rawurlencode($safe);
    }

    /** @return array{path:string,url:string}|null */
    private static function validar($raw)
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

        // `/v2/../admin` seria resolvido pelo navegador fora do prefixo validado.
        if (preg_match('#(^|/)\.{1,2}(/|$)#', $path) || stripos($path, '%2e') !== false) {
            return null;
        }

        $out = $path;
        if (isset($parsed['query']) && $parsed['query'] !== '') {
            $out .= '?' . $parsed['query'];
        }

        return array('path' => $path, 'url' => $out);
    }

    private static function temPrefixo($path, array $prefixos)
    {
        foreach ($prefixos as $prefixo) {
            if ($path === $prefixo || strpos($path, $prefixo . '/') === 0) {
                return true;
            }
        }

        return false;
    }
}

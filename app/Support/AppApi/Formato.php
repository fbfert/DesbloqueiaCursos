<?php

namespace App\Support\AppApi;

use App\Core\Env;
use App\Core\Helpers;

/**
 * Conversões comuns dos presenters da API do app.
 */
class Formato
{
    /** Valor decimal em reais (string/float do banco) → centavos inteiros. */
    public static function centavos($valor)
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        return (int) round(((float) $valor) * 100);
    }

    public static function int($valor)
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    public static function texto($valor)
    {
        if ($valor === null) {
            return null;
        }
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }

    /** Base pública do site (APP_URL), sem barra final. */
    public static function urlSite()
    {
        return rtrim((string) Env::get('APP_URL', 'https://desbloqueiacursos.com.br'), '/');
    }

    /** Caminho público do site → URL absoluta (imagens, links que o app abre no navegador). */
    public static function urlAbsoluta($caminho)
    {
        $caminho = trim((string) $caminho);
        if ($caminho === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $caminho)) {
            return $caminho;
        }
        return self::urlSite() . '/' . ltrim($caminho, '/');
    }

    /** HTML do editor → HTML seguro (mesma cadeia do site: decode + HtmlSanitizer). */
    public static function htmlSeguro($html, $perfil = 'full')
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return null;
        }
        $limpo = Helpers::renderSafeHtml($html, $perfil);
        return trim((string) $limpo) === '' ? null : $limpo;
    }

    /** CPF mascarado: 123.***.***-00. */
    public static function cpfMascarado($cpf)
    {
        $digitos = preg_replace('/\D+/', '', (string) $cpf);
        if (strlen($digitos) !== 11) {
            return $digitos === '' ? null : '***';
        }
        return substr($digitos, 0, 3) . '.***.***-' . substr($digitos, 9, 2);
    }

    /** Paginação `?pagina=1&por_pagina=20` com limites. */
    public static function paginacao($pagina, $porPagina, $padrao = 20, $maximo = 50)
    {
        $pagina = max(1, (int) $pagina);
        $porPagina = (int) $porPagina;
        if ($porPagina <= 0) {
            $porPagina = $padrao;
        }
        $porPagina = min($maximo, $porPagina);

        return array($pagina, $porPagina, ($pagina - 1) * $porPagina);
    }
}

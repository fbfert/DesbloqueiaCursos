<?php

namespace App\Support;

use App\Core\Request;
use App\Core\Session;

/**
 * Captura e persistencia da origem de trafego (UTMs + gclid).
 *
 * Regra: vale a PRIMEIRA visita da sessao. Se o visitante chega por um
 * anuncio, navega por outras paginas e so entao compra, a origem gravada
 * no pedido continua sendo a do anuncio — e nao "acesso direto".
 *
 * Uma nova origem so sobrescreve a anterior se a sessao ainda nao tinha
 * nenhuma, ou se a nova traz gclid (clique de anuncio pago sempre manda).
 */
class OrigemTrafego
{
    const SESSION_KEY = 'origem_trafego';

    const CAMPOS = array(
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
    );

    /** Limites alinhados ao schema de `pedidos`. */
    private static $limites = array(
        'utm_source' => 120,
        'utm_medium' => 120,
        'utm_campaign' => 191,
        'utm_term' => 191,
        'utm_content' => 191,
        'gclid' => 191,
    );

    /**
     * Le a query string e guarda a origem na sessao. Idempotente: chamar em
     * toda requisicao GET e seguro e nao apaga o que ja foi capturado.
     */
    public static function capturar(Request $request)
    {
        $query = array();
        foreach (self::CAMPOS as $campo) {
            $query[$campo] = $request->query($campo, '');
        }

        return self::capturarDeQuery($query);
    }

    /**
     * Mesma captura, a partir de um array cru de query string. Usada no
     * bootstrap (index.php), onde ainda nao existe objeto Request, para que a
     * origem seja gravada em QUALQUER pagina de entrada — e nao so no checkout.
     */
    public static function capturarDeQuery(array $query)
    {
        $recebida = array();

        foreach (self::CAMPOS as $campo) {
            $valor = self::limpar(isset($query[$campo]) ? $query[$campo] : '', $campo);
            if ($valor !== null) {
                $recebida[$campo] = $valor;
            }
        }

        if (empty($recebida)) {
            return self::atual();
        }

        $existente = self::atual();
        $temGclidNovo = isset($recebida['gclid']);
        $jaTinha = !empty($existente['origem_capturada_em']);

        if ($jaTinha && !$temGclidNovo) {
            return $existente;
        }

        $origem = array('origem_capturada_em' => date('Y-m-d H:i:s'));
        foreach (self::CAMPOS as $campo) {
            $origem[$campo] = isset($recebida[$campo]) ? $recebida[$campo] : null;
        }

        Session::put(self::SESSION_KEY, $origem);

        return $origem;
    }

    /**
     * Origem guardada na sessao, sempre com todas as chaves presentes.
     */
    public static function atual()
    {
        $origem = Session::get(self::SESSION_KEY);
        $base = array('origem_capturada_em' => null);

        foreach (self::CAMPOS as $campo) {
            $base[$campo] = null;
        }

        if (!is_array($origem)) {
            return $base;
        }

        foreach ($base as $chave => $padrao) {
            if (isset($origem[$chave]) && $origem[$chave] !== '') {
                $base[$chave] = $origem[$chave];
            }
        }

        return $base;
    }

    /**
     * true se ha alguma informacao de origem para gravar.
     */
    public static function temOrigem()
    {
        $origem = self::atual();

        foreach (self::CAMPOS as $campo) {
            if (!empty($origem[$campo])) {
                return true;
            }
        }

        return false;
    }

    public static function esquecer()
    {
        Session::forget(self::SESSION_KEY);
    }

    private static function limpar($valor, $campo)
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        // Remove control chars; o resto e texto livre vindo do anunciante.
        $valor = preg_replace('/[\x00-\x1F\x7F]/u', '', $valor);
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        $limite = isset(self::$limites[$campo]) ? self::$limites[$campo] : 191;

        return function_exists('mb_substr') ? mb_substr($valor, 0, $limite) : substr($valor, 0, $limite);
    }
}

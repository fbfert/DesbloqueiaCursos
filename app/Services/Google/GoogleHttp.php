<?php

namespace App\Services\Google;

/**
 * HTTP mínimo para os endpoints do Google (chaves públicas e troca do código).
 * Mesmo padrão de PushService::http: curl com verificação TLS, tempo curto e um
 * transporte injetável para os testes.
 */
class GoogleHttp
{
    private $transporte;

    /**
     * @param callable|null $transporte function ($metodo, $url, array $cabecalhos, $corpo)
     *                                  → array('status' => int, 'corpo' => string, 'cabecalhos' => array, 'erro' => ?string)
     */
    public function __construct(?callable $transporte = null)
    {
        $this->transporte = $transporte;
    }

    public function requisitar($metodo, $url, array $cabecalhos = array(), $corpo = null)
    {
        if ($this->transporte !== null) {
            $r = call_user_func($this->transporte, $metodo, $url, $cabecalhos, $corpo);
            return array(
                'status' => (int) ($r['status'] ?? 0),
                'corpo' => (string) ($r['corpo'] ?? ''),
                'cabecalhos' => isset($r['cabecalhos']) && is_array($r['cabecalhos']) ? $r['cabecalhos'] : array(),
                'erro' => isset($r['erro']) ? $r['erro'] : null,
            );
        }

        if (!function_exists('curl_init')) {
            return array('status' => 0, 'corpo' => '', 'cabecalhos' => array(), 'erro' => 'curl_indisponivel');
        }

        $recebidos = array();
        $ch = curl_init($url);
        $opcoes = array(
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $cabecalhos,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADERFUNCTION => function ($ch, $linha) use (&$recebidos) {
                $partes = explode(':', $linha, 2);
                if (count($partes) === 2) {
                    $recebidos[strtolower(trim($partes[0]))] = trim($partes[1]);
                }
                return strlen($linha);
            },
        );
        if ($corpo !== null) {
            $opcoes[CURLOPT_POSTFIELDS] = $corpo;
        }
        curl_setopt_array($ch, $opcoes);
        $resposta = curl_exec($ch);
        $erro = $resposta === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array(
            'status' => $status,
            'corpo' => $resposta === false ? '' : (string) $resposta,
            'cabecalhos' => $recebidos,
            'erro' => $erro,
        );
    }
}

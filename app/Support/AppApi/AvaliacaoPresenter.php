<?php

namespace App\Support\AppApi;

use App\Services\ConteudoAvaliacaoTextualService;

/**
 * JSON da avaliação textual (contrato §Avaliação textual).
 */
class AvaliacaoPresenter
{
    /**
     * @param array $avaliacao linha de conteudo_avaliacoes_textuais
     * @param array $entregas  listarEntregasAluno, cada uma com `imagens`
     */
    public static function estado(array $avaliacao, array $entregas, $podeEnviarAgora)
    {
        $enunciado = trim((string) ($avaliacao['enunciado'] ?? ''));
        if (trim((string) ($avaliacao['orientacoes'] ?? '')) !== '') {
            $enunciado .= "\n" . (string) $avaliacao['orientacoes'];
        }

        $lista = array();
        foreach ($entregas as $entrega) {
            $lista[] = self::entrega($entrega);
        }
        $temEntrega = !empty($entregas);

        return array(
            'enunciado_html' => Formato::htmlSeguro($enunciado, 'full') ?: '',
            'nota_minima' => isset($avaliacao['nota_minima']) && $avaliacao['nota_minima'] !== null && $avaliacao['nota_minima'] !== '' ? (float) $avaliacao['nota_minima'] : null,
            'pode_enviar' => !$temEntrega && (bool) $podeEnviarAgora,
            'pode_reenviar' => $temEntrega && (bool) $podeEnviarAgora,
            'limites' => ConteudoAvaliacaoTextualService::limitesEnvio(),
            'entregas' => $lista,
        );
    }

    public static function entrega(array $entrega)
    {
        $imagens = array();
        foreach ((array) ($entrega['imagens'] ?? array()) as $imagem) {
            $imagens[] = array(
                'id' => (int) $imagem['id'],
                'url' => '/api/app/v1/avaliacoes/imagens/' . (int) $imagem['id'],
            );
        }
        $corrigida = !empty($entrega['corrigido_em']);

        return array(
            'id' => (int) $entrega['id'],
            'status' => (string) $entrega['status'],
            'texto' => (string) ($entrega['resposta'] ?? ''),
            'imagens' => $imagens,
            'nota' => $corrigida && isset($entrega['nota']) && $entrega['nota'] !== null && $entrega['nota'] !== '' ? (float) $entrega['nota'] : null,
            'feedback_html' => $corrigida ? Formato::htmlSeguro($entrega['feedback'] ?? '', 'basic') : null,
            'enviada_em' => Tempo::iso($entrega['enviado_em'] ?? ($entrega['created_at'] ?? null)),
            'corrigida_em' => Tempo::iso($entrega['corrigido_em'] ?? null),
        );
    }
}

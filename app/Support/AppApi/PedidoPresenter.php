<?php

namespace App\Support\AppApi;

use App\Services\PedidoService;

/**
 * JSON de pedidos (contrato §Pedidos). As decisões `pode_*` usam as regras de
 * PedidoService (fonte única também do site).
 */
class PedidoPresenter
{
    const ROTULOS = array(
        'rascunho' => 'Rascunho',
        'pendencia' => 'Pendência',
        'aguardando_pagamento' => 'Aguardando pagamento',
        'aguardando_reenvio' => 'Aguardando reenvio do comprovante',
        'comprovante_enviado' => 'Comprovante enviado',
        'em_analise' => 'Em análise',
        'aprovado' => 'Aprovado',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado',
        'reembolsado' => 'Reembolsado',
        'expirado' => 'Expirado',
    );

    /**
     * @param array      $pedido      linha de pedidos
     * @param array      $itens       PedidoItem::forPedido
     * @param array|null $comprovante comprovante atual (ComprovantePix::findCurrentByPedido)
     */
    public static function pedido(array $pedido, array $itens, ?array $comprovante, $pagamentoOnlineHabilitado)
    {
        $status = (string) $pedido['status'];
        $total = (float) ($pedido['total'] ?? 0);

        $listaItens = array();
        foreach ($itens as $item) {
            $titulo = (string) ($item['curso_nome'] ?? '');
            if (!empty($item['turma_nome'])) {
                $titulo .= ' — ' . (string) $item['turma_nome'];
            }
            $listaItens[] = array(
                'titulo' => $titulo,
                'valor_centavos' => Formato::centavos($item['valor_total'] ?? 0),
            );
        }

        $comprovanteJson = null;
        if ($comprovante) {
            $statusComprovante = (string) ($comprovante['status'] ?? '');
            $comprovanteJson = array(
                // pendente e em_analise são, para o aluno, a mesma coisa: aguardando análise.
                'status' => in_array($statusComprovante, array('aprovado', 'reprovado'), true) ? $statusComprovante : 'em_analise',
                'motivo' => $statusComprovante === 'reprovado' ? Formato::texto($comprovante['analise_observacao'] ?? null) : null,
            );
        }

        $aceitaComprovante = PedidoService::pedidoAceitaComprovante($status) && $total > 0;

        return array(
            'id' => (int) $pedido['id'],
            'numero' => (string) ($pedido['codigo'] ?? ''),
            'status' => $status,
            'status_rotulo' => isset(self::ROTULOS[$status]) ? self::ROTULOS[$status] : ucfirst(str_replace('_', ' ', $status)),
            'total_centavos' => Formato::centavos($total),
            'criado_em' => Tempo::iso($pedido['created_at'] ?? null),
            'itens' => $listaItens,
            'pode_cancelar' => PedidoService::pedidoPodeSerCanceladoPeloAluno($status),
            'pode_enviar_comprovante' => $aceitaComprovante,
            'pode_pagar_online' => (bool) $pagamentoOnlineHabilitado && $total > 0
                && in_array($status, array('rascunho', 'pendencia', 'aguardando_reenvio', 'aguardando_pagamento'), true),
            'comprovante' => $comprovanteJson,
            'pix' => $aceitaComprovante ? array('chave' => PedidoService::chavePix(), 'copia_e_cola' => null) : null,
        );
    }
}

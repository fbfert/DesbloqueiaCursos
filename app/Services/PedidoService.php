<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ComprovantePix;
use App\Models\Inscricao;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\ParticipantePedido;
use Exception;

class PedidoService
{
    private $pedidoModel;
    private $pedidoItemModel;
    private $participanteModel;
    private $comprovanteModel;
    private $inscricaoModel;
    private $auditService;
    private $trashService;
    private $rbacService;

    public function __construct()
    {
        $this->pedidoModel = new Pedido();
        $this->pedidoItemModel = new PedidoItem();
        $this->participanteModel = new ParticipantePedido();
        $this->comprovanteModel = new ComprovantePix();
        $this->inscricaoModel = new Inscricao();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->rbacService = new RbacService();
    }

    public function createPedido(array $pedidoData, array $itens = array(), array $participantes = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $pedidoId = $this->pedidoModel->create($pedidoData);
            $pedido = $this->pedidoModel->findById($pedidoId);

            foreach ($itens as $item) {
                $item['pedido_id'] = $pedidoId;
                $pedidoItemId = $this->pedidoItemModel->create($item);

                if (!empty($item['participantes'])) {
                    foreach ($item['participantes'] as $posicao => $participante) {
                        $participante['pedido_id'] = $pedidoId;
                        $participante['pedido_item_id'] = $pedidoItemId;
                        $participante['ordem'] = isset($participante['ordem']) ? $participante['ordem'] : $posicao + 1;
                        $this->participanteModel->create($participante);
                    }
                }
            }

            foreach ($participantes as $posicao => $participante) {
                $participante['pedido_id'] = $pedidoId;
                $participante['ordem'] = isset($participante['ordem']) ? $participante['ordem'] : $posicao + 1;
                $this->participanteModel->create($participante);
            }

            $this->pedidoModel->addStatusHistory($pedidoId, null, isset($pedidoData['status']) ? $pedidoData['status'] : 'rascunho', 'Criacao do pedido', $actorUserId);

            $this->auditService->record(
                'pedido.criado',
                'pedido',
                $pedidoId,
                array('pedido' => $pedido, 'itens' => $itens, 'participantes' => $participantes),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.criado', array(
                'pedido_id' => $pedidoId,
                'usuario_id' => $actorUserId,
                'ip_address' => $ipAddress,
            ));

            $pdo->commit();

            return array('ok' => true, 'pedido_id' => $pedidoId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.criar_falhou', array(
                'message' => $exception->getMessage(),
                'usuario_id' => $actorUserId,
            ));

            throw $exception;
        }
    }

    public function registrarStatus($pedidoId, $novoStatus, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        $statusValidos = array(
            'rascunho',
            'pendencia',
            'aguardando_pagamento',
            'aguardando_reenvio',
            'comprovante_enviado',
            'em_analise',
            'aprovado',
            'pago',
            'cancelado',
            'reembolsado',
            'expirado',
        );

        if (!in_array($novoStatus, $statusValidos, true)) {
            return array('ok' => false, 'message' => 'Status de pedido invalido.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->pedidoModel->updateStatus($pedidoId, $novoStatus);
            $this->pedidoModel->addStatusHistory($pedidoId, $pedido['status'], $novoStatus, $observacao, $actorUserId);

            $this->auditService->record(
                'pedido.status.atualizado',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => $pedido['status'],
                    'status_novo' => $novoStatus,
                    'observacao' => $observacao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.status.atualizado', array(
                'pedido_id' => $pedidoId,
                'status_novo' => $novoStatus,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.status.falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function anexarComprovantePix(array $dados, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $existente = $this->comprovanteModel->findByPedido($dados['pedido_id']);

            if ($existente) {
                $dados['motivo_reenvio'] = isset($dados['motivo_reenvio']) ? $dados['motivo_reenvio'] : 'Reenvio do comprovante PIX';
                $comprovanteId = $this->comprovanteModel->createVersion($dados['pedido_id'], $dados);
                $acao = 'comprovante_pix.atualizado';
            } else {
                $comprovanteId = $this->comprovanteModel->create($dados);
                $acao = 'comprovante_pix.criado';
            }

            $this->auditService->record(
                $acao,
                'comprovante_pix',
                $comprovanteId,
                array('pedido_id' => $dados['pedido_id'], 'dados' => $dados),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array(
                'comprovante_pix_id' => $comprovanteId,
                'pedido_id' => $dados['pedido_id'],
            ));

            $pdo->commit();

            return array('ok' => true, 'comprovante_pix_id' => $comprovanteId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('comprovante_pix.falhou', array(
                'pedido_id' => isset($dados['pedido_id']) ? $dados['pedido_id'] : null,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function excluir($pedidoId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('pedido', $pedidoId, $justificativa, $pedido, $actorUserId, $ipAddress, $userAgent);
            $this->pedidoModel->softDelete($pedidoId);

            $this->auditService->record(
                'pedido.excluido',
                'pedido',
                $pedidoId,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.excluido', array(
                'pedido_id' => $pedidoId,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.excluir_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function aprovarPedido($pedidoId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->pedidoModel->markApproved($pedidoId, $actorUserId);
            $this->pedidoModel->addStatusHistory($pedidoId, $pedido['status'], 'aprovado', $observacao, $actorUserId);

            $this->auditService->record(
                'pedido.aprovado',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => $pedido['status'],
                    'status_novo' => 'aprovado',
                    'observacao' => $observacao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.aprovado', array(
                'pedido_id' => $pedidoId,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.aprovar_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function marcarPendencia($pedidoId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->registrarStatus($pedidoId, 'pendencia', $observacao, $actorUserId, $ipAddress, $userAgent);
    }

    public function solicitarReenvioComprovante($pedidoId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->registrarStatus($pedidoId, 'aguardando_reenvio', $observacao, $actorUserId, $ipAddress, $userAgent);
    }

    public function detalharBackoffice($pedidoId, $usuarioId)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('pedido' => null);
        }

        $canSeePix = $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver');

        $pedido['itens'] = $this->pedidoItemModel->forPedido($pedidoId);
        $pedido['participantes'] = $this->participanteModel->forPedido($pedidoId);
        $pedido['inscricoes'] = $this->inscricaoModel->forPedido($pedidoId);
        $pedido['historico'] = $this->pedidoModel->historyForPedido($pedidoId);
        $pedido['comprovante_atual'] = $canSeePix ? $this->comprovanteModel->findByPedido($pedidoId) : null;
        $pedido['comprovantes'] = $canSeePix ? $this->comprovanteModel->versionsForPedido($pedidoId) : array();

        return array(
            'pedido' => $pedido,
            'can_see_pix' => $canSeePix,
        );
    }

    public function listarBackoffice($usuarioId)
    {
        $pedidos = $this->pedidoModel->allForBackoffice();
        $canSeePedidos = $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver');
        $canSeePix = $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver');

        if (!$canSeePedidos) {
            return array('pedidos' => array());
        }

        foreach ($pedidos as &$pedido) {
            if (!$canSeePix) {
                $pedido['comprovante_pix'] = null;
                continue;
            }

            $pedido['comprovante_pix'] = $this->comprovanteModel->findByPedido($pedido['id']);
        }
        unset($pedido);

        foreach ($pedidos as &$pedido) {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) AS total
                 FROM participantes_pedido
                 WHERE pedido_id = :pedido_id
                   AND deleted_at IS NULL'
            );
            $stmt->execute(array('pedido_id' => $pedido['id']));
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $pedido['quantidade_participantes'] = $row ? (int) $row['total'] : 0;
        }
        unset($pedido);

        return array('pedidos' => $pedidos);
    }
}

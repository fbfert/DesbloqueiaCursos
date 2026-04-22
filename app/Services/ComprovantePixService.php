<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ComprovantePix;
use App\Models\Pedido;
use Exception;

class ComprovantePixService
{
    private $comprovanteModel;
    private $pedidoModel;
    private $auditService;
    private $rbacService;

    public function __construct()
    {
        $this->comprovanteModel = new ComprovantePix();
        $this->pedidoModel = new Pedido();
        $this->auditService = new AuditService();
        $this->rbacService = new RbacService();
    }

    public function listarBackoffice($usuarioId)
    {
        $canSeePix = $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver');

        if (!$canSeePix) {
            return array('comprovantes_pix' => array());
        }

        return array('comprovantes_pix' => $this->comprovanteModel->allForBackoffice());
    }

    public function aprovar($comprovanteId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $comprovante = $this->comprovanteModel->findById($comprovanteId);

        if (!$comprovante) {
            return array('ok' => false, 'message' => 'Comprovante nao encontrado.');
        }

        $pedido = $this->pedidoModel->findById($comprovante['pedido_id']);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido do comprovante nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $agora = date('Y-m-d H:i:s');

            $this->comprovanteModel->updateStatus(
                $comprovanteId,
                'aprovado',
                $observacao,
                $actorUserId,
                $agora
            );

            $this->pedidoModel->updateStatus($pedido['id'], 'pago');
            $this->pedidoModel->addStatusHistory(
                $pedido['id'],
                $pedido['status'],
                'pago',
                $observacao,
                $actorUserId
            );

            $this->auditService->record(
                'comprovante_pix.aprovado',
                'comprovante_pix',
                $comprovanteId,
                array(
                    'pedido_id' => $pedido['id'],
                    'status_anterior_comprovante' => $comprovante['status'],
                    'status_novo_comprovante' => 'aprovado',
                    'status_anterior_pedido' => $pedido['status'],
                    'status_novo_pedido' => 'pago',
                    'observacao' => $observacao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('comprovante_pix.aprovado', array(
                'comprovante_pix_id' => $comprovanteId,
                'pedido_id' => $pedido['id'],
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('comprovante_pix.aprovar_falhou', array(
                'comprovante_pix_id' => $comprovanteId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function reprovar($comprovanteId, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $comprovante = $this->comprovanteModel->findById($comprovanteId);

        if (!$comprovante) {
            return array('ok' => false, 'message' => 'Comprovante nao encontrado.');
        }

        $pedido = $this->pedidoModel->findById($comprovante['pedido_id']);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido do comprovante nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $agora = date('Y-m-d H:i:s');

            $this->comprovanteModel->updateStatus(
                $comprovanteId,
                'reprovado',
                $observacao,
                $actorUserId,
                $agora
            );

            $this->pedidoModel->updateStatus($pedido['id'], 'aguardando_reenvio');
            $this->pedidoModel->addStatusHistory(
                $pedido['id'],
                $pedido['status'],
                'aguardando_reenvio',
                $observacao,
                $actorUserId
            );

            $this->auditService->record(
                'comprovante_pix.reprovado',
                'comprovante_pix',
                $comprovanteId,
                array(
                    'pedido_id' => $pedido['id'],
                    'status_anterior_comprovante' => $comprovante['status'],
                    'status_novo_comprovante' => 'reprovado',
                    'status_anterior_pedido' => $pedido['status'],
                    'status_novo_pedido' => 'aguardando_reenvio',
                    'observacao' => $observacao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('comprovante_pix.reprovado', array(
                'comprovante_pix_id' => $comprovanteId,
                'pedido_id' => $pedido['id'],
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('comprovante_pix.reprovar_falhou', array(
                'comprovante_pix_id' => $comprovanteId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }
}

<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\ComprovantePix;
use App\Models\Pedido;
use App\Services\FileStorageService;
use Exception;

class ComprovantePixService
{
    private $comprovanteModel;
    private $pedidoModel;
    private $fileStorage;
    private $emailService;
    private $auditService;
    private $rbacService;

    public function __construct()
    {
        $this->comprovanteModel = new ComprovantePix();
        $this->pedidoModel = new Pedido();
        $this->fileStorage = new FileStorageService();
        $this->emailService = new EmailService();
        $this->auditService = new AuditService();
        $this->rbacService = new RbacService();
    }

    public function enviarUpload($pedidoId, array $arquivo, array $dados = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (empty($arquivo['tmp_name']) || empty($arquivo['name'])) {
            return array('ok' => false, 'message' => 'Selecione um comprovante valido.');
        }

        $stored = $this->fileStorage->storeUploadedFile($arquivo, 'comprovantes_pix/' . $pedidoId, 'pix');

        $payload = array(
            'pedido_id' => $pedidoId,
            'usuario_id' => $actorUserId,
            'arquivo_caminho' => $stored['relative_path'],
            'arquivo_nome_original' => $stored['original_name'],
            'arquivo_mime_type' => $stored['mime_type'],
            'arquivo_tamanho_bytes' => $stored['size'],
            'valor_informado' => isset($dados['valor_informado']) ? $dados['valor_informado'] : null,
            'enviado_em' => date('Y-m-d H:i:s'),
            'status' => 'pendente',
            'analise_observacao' => null,
            'analisado_por_usuario_id' => null,
            'analisado_em' => null,
            'motivo_reenvio' => isset($dados['motivo_reenvio']) ? $dados['motivo_reenvio'] : null,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $existente = $this->comprovanteModel->findCurrentByPedido($pedidoId);
            if ($existente) {
                $comprovanteId = $this->comprovanteModel->createVersion($pedidoId, $payload);
            } else {
                $comprovanteId = $this->comprovanteModel->create($payload);
            }

            $this->pedidoModel->updateStatus($pedidoId, 'comprovante_enviado');
            $this->pedidoModel->addStatusHistory($pedidoId, $pedido['status'], 'comprovante_enviado', 'Comprovante PIX enviado', $actorUserId);

            $this->auditService->record(
                'comprovante_pix.enviado',
                'comprovante_pix',
                $comprovanteId,
                array(
                    'pedido_id' => $pedidoId,
                    'arquivo' => $stored,
                    'motivo_reenvio' => isset($dados['motivo_reenvio']) ? $dados['motivo_reenvio'] : null,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('comprovante_pix.enviado', array(
                'comprovante_pix_id' => $comprovanteId,
                'pedido_id' => $pedidoId,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            $this->emailService->comprovanteEnviado($this->pedidoModel->findById($pedidoId), $actorUserId, $ipAddress, $userAgent);

            return array('ok' => true, 'comprovante_pix_id' => $comprovanteId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('comprovante_pix.enviar_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
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

            $this->emailService->pedidoAprovado($this->pedidoModel->findById($pedido['id']), $observacao, $actorUserId, $ipAddress, $userAgent);

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

            $this->emailService->pendencia($this->pedidoModel->findById($pedido['id']), $observacao, $actorUserId, $ipAddress, $userAgent);

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

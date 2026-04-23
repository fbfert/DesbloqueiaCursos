<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\ParticipantePedido;
use App\Models\Inscricao;
use App\Models\CursoEvento;
use App\Models\Turma;
use Exception;

class InscricaoService
{
    private $inscricaoModel;
    private $pedidoModel;
    private $pedidoItemModel;
    private $participanteModel;
    private $cursoModel;
    private $turmaModel;
    private $auditService;
    private $trashService;
    private $rbacService;

    public function __construct()
    {
        $this->inscricaoModel = new Inscricao();
        $this->pedidoModel = new Pedido();
        $this->pedidoItemModel = new PedidoItem();
        $this->participanteModel = new ParticipantePedido();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->rbacService = new RbacService();
    }

    public function gerarDoPedido($pedidoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        $itens = $this->pedidoItemModel->forPedido($pedidoId);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $criados = array();

            foreach ($itens as $item) {
                $participantes = $this->participanteModel->forPedidoItem($item['id']);

                foreach ($participantes as $participante) {
                    $existente = $this->inscricaoModel->findByPedidoItemAndParticipante($item['id'], $participante['id']);
                    if ($existente) {
                        continue;
                    }

                    $turmaId = !empty($item['turma_id']) ? (int) $item['turma_id'] : null;
                    $cursoId = (int) $item['curso_evento_id'];

                    $criados[] = $this->inscricaoModel->create(array(
                        'pedido_id' => $pedidoId,
                        'pedido_item_id' => $item['id'],
                        'participante_pedido_id' => $participante['id'],
                        'usuario_id' => !empty($participante['usuario_id']) ? $participante['usuario_id'] : null,
                        'curso_evento_id' => $cursoId,
                        'turma_id' => $turmaId,
                        'status' => 'pendente',
                        'confirmado_em' => null,
                    ));
                }
            }

            $this->auditService->record(
                'checkout.inscricoes.geradas',
                'pedido',
                $pedidoId,
                array('inscricoes_ids' => $criados),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('checkout.inscricoes.geradas', array(
                'pedido_id' => $pedidoId,
                'total' => count($criados),
            ));

            $pdo->commit();

            return array('ok' => true, 'inscricoes_ids' => $criados);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('checkout.inscricoes.falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function criar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $inscricaoId = $this->inscricaoModel->create($data);
            $this->inscricaoModel->addStatusHistory($inscricaoId, null, isset($data['status']) ? $data['status'] : 'pendente', 'Criacao da inscricao', $actorUserId);

            $this->auditService->record(
                'inscricao.criada',
                'inscricao',
                $inscricaoId,
                array('dados' => $data),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('inscricao.criada', array(
                'inscricao_id' => $inscricaoId,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array('ok' => true, 'inscricao_id' => $inscricaoId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('inscricao.criar_falhou', array(
                'message' => $exception->getMessage(),
                'usuario_id' => $actorUserId,
            ));

            throw $exception;
        }
    }

    public function registrarStatus($inscricaoId, $novoStatus, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $inscricao = $this->inscricaoModel->findById($inscricaoId);

        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        $statusValidos = array(
            'pendente',
            'com_pendencia',
            'ativa',
            'em_andamento',
            'cancelada',
            'reprovada',
            'concluida',
            'concluida_sem_certificado',
            'certificado_emitido',
        );

        if (!in_array($novoStatus, $statusValidos, true)) {
            return array('ok' => false, 'message' => 'Status de inscricao invalido.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->inscricaoModel->updateStatus($inscricaoId, $novoStatus);
            $this->inscricaoModel->addStatusHistory($inscricaoId, $inscricao['status'], $novoStatus, $observacao, $actorUserId);

            $this->auditService->record(
                'inscricao.status.atualizado',
                'inscricao',
                $inscricaoId,
                array(
                    'status_anterior' => $inscricao['status'],
                    'status_novo' => $novoStatus,
                    'observacao' => $observacao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('inscricao.status.atualizado', array(
                'inscricao_id' => $inscricaoId,
                'status_novo' => $novoStatus,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('inscricao.status.falhou', array(
                'inscricao_id' => $inscricaoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function alterarStatus($inscricaoId, $novoStatus, $observacao = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->registrarStatus($inscricaoId, $novoStatus, $observacao, $actorUserId, $ipAddress, $userAgent);
    }

    public function excluir($inscricaoId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $inscricao = $this->inscricaoModel->findById($inscricaoId);

        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('inscricao', $inscricaoId, $justificativa, $inscricao, $actorUserId, $ipAddress, $userAgent);
            $this->inscricaoModel->softDelete($inscricaoId);

            $this->auditService->record(
                'inscricao.excluida',
                'inscricao',
                $inscricaoId,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('inscricao.excluida', array(
                'inscricao_id' => $inscricaoId,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('inscricao.excluir_falhou', array(
                'inscricao_id' => $inscricaoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function listarBackoffice($usuarioId)
    {
        $canSeePedidos = $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver');

        if (!$canSeePedidos) {
            return array('inscricoes' => array());
        }

        return array('inscricoes' => $this->inscricaoModel->allForBackoffice());
    }

    public function listarDoUsuario($usuarioId)
    {
        return array('inscricoes' => $this->inscricaoModel->forUsuario($usuarioId));
    }
}

<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Helpers;
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
    private $emailService;
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
        $this->emailService = new EmailService();
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

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('inscricao.gerar_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para gerar inscricoes deste pedido.');
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
                    $usuarioIdParticipante = !empty($participante['usuario_id']) ? (int) $participante['usuario_id'] : null;

                    if ($usuarioIdParticipante && $turmaId) {
                        $inscricaoExistente = $this->inscricaoModel->findByUsuarioTurma($usuarioIdParticipante, $turmaId);
                        if ($inscricaoExistente) {
                            $pdo->rollBack();
                            return array(
                                'ok' => false,
                                'message' => 'Você já está inscrito nesta turma.',
                            );
                        }
                    }

                    $criados[] = $this->inscricaoModel->create(array(
                        'pedido_id' => $pedidoId,
                        'pedido_item_id' => $item['id'],
                        'participante_pedido_id' => $participante['id'],
                        'usuario_id' => $usuarioIdParticipante,
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

            foreach ($criados as $inscricaoId) {
                $inscricao = $this->inscricaoModel->findById($inscricaoId);
                if ($inscricao) {
                    $this->emailService->cursoProximo(
                        $this->envelopeInscricaoParaEmail($inscricao),
                        $actorUserId,
                        $ipAddress,
                        $userAgent
                    );
                }
            }

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
        $usuarioId = isset($data['usuario_id']) ? (int) $data['usuario_id'] : 0;
        $turmaId = isset($data['turma_id']) ? (int) $data['turma_id'] : 0;
        if ($usuarioId > 0 && $turmaId > 0) {
            $inscricaoExistente = $this->inscricaoModel->findByUsuarioTurma($usuarioId, $turmaId);
            if ($inscricaoExistente) {
                return array('ok' => false, 'message' => 'Você já está inscrito nesta turma.');
            }
        }

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

        if (!$this->inscricaoPodeSerAcessadaPor($inscricao, $actorUserId)) {
            $this->registrarAcessoNegado('inscricao.status.negado', $inscricaoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para alterar esta inscricao.');
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

            $inscricaoAtualizada = $this->inscricaoModel->findById($inscricaoId);
            if ($novoStatus === 'com_pendencia') {
                $this->emailService->pendencia($this->envelopeInscricaoParaEmail($inscricaoAtualizada), $observacao, $actorUserId, $ipAddress, $userAgent);
            } elseif ($novoStatus === 'em_andamento') {
                $this->emailService->cursoProximo($this->envelopeInscricaoParaEmail($inscricaoAtualizada), $actorUserId, $ipAddress, $userAgent);
            } elseif ($novoStatus === 'concluida' || $novoStatus === 'concluida_sem_certificado') {
                $this->emailService->concluido($this->envelopeInscricaoParaEmail($inscricaoAtualizada), $actorUserId, $ipAddress, $userAgent);
            } elseif ($novoStatus === 'certificado_emitido') {
                $this->emailService->certificadoDisponivel($this->envelopeInscricaoParaEmail($inscricaoAtualizada), $actorUserId, $ipAddress, $userAgent);
            }

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

        if (!$this->inscricaoPodeSerAcessadaPor($inscricao, $actorUserId)) {
            $this->registrarAcessoNegado('inscricao.excluir_negado', $inscricaoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para excluir esta inscricao.');
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

    public function listarBackoffice($usuarioId, array $filters = array(), $page = 1, $perPage = 20)
    {
        $canSeePedidos = $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver');

        if (!$canSeePedidos) {
            return array(
                'inscricoes' => array(),
                'pagination' => array(
                    'total' => 0,
                    'page' => 1,
                    'per_page' => (int) $perPage,
                    'pages' => 1,
                ),
                'cursos' => array(),
                'turmas' => array(),
            );
        }

        $page = (int) $page;
        if ($page <= 0) {
            $page = 1;
        }

        $perPage = (int) $perPage;
        if ($perPage <= 0) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $total = $this->inscricaoModel->countFilteredBackoffice($filters);
        $offset = ($page - 1) * $perPage;
        $inscricoes = $this->inscricaoModel->listFilteredBackoffice($filters, $perPage, $offset);

        return array(
            'inscricoes' => $inscricoes,
            'pagination' => array(
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1,
            ),
            'cursos' => $this->cursoModel->allForSelect(),
            'turmas' => $this->turmaModel->allWithCourse(),
        );
    }

    public function listarDoUsuario($usuarioId)
    {
        return array('inscricoes' => $this->inscricaoModel->forUsuario($usuarioId));
    }

    public function listarAprovadasDoUsuario($usuarioId)
    {
        return array('inscricoes' => $this->inscricaoModel->forUsuarioAprovadas($usuarioId));
    }

    public function usuarioPossuiInscricaoNaTurma($usuarioId, $turmaId)
    {
        return !empty($this->inscricaoModel->findByUsuarioTurma($usuarioId, $turmaId));
    }

    private function envelopeInscricaoParaEmail(?array $inscricao = null)
    {
        if (!$inscricao) {
            return array();
        }

        $pedido = $this->pedidoModel->findById(isset($inscricao['pedido_id']) ? $inscricao['pedido_id'] : 0);
        $participante = null;
        if (!empty($inscricao['participante_pedido_id'])) {
            $stmt = Database::connection()->prepare(
                'SELECT * FROM participantes_pedido WHERE id = :id AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute(array('id' => $inscricao['participante_pedido_id']));
            $participante = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        $curso = null;
        if (!empty($inscricao['curso_evento_id'])) {
            $curso = $this->cursoModel->findPublicById((int) $inscricao['curso_evento_id']);
        }

        $turma = null;
        if (!empty($inscricao['turma_id'])) {
            $turma = $this->turmaModel->findPublicById((int) $inscricao['turma_id']);
        }

        $certificado = null;
        if (!empty($inscricao['id'])) {
            $stmt = Database::connection()->prepare(
                'SELECT *
                 FROM certificados
                 WHERE inscricao_id = :inscricao_id
                   AND deleted_at IS NULL
                   AND status = "emitido"
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute(array('inscricao_id' => $inscricao['id']));
            $certificado = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return array(
            'id' => $inscricao['id'],
            'status' => $inscricao['status'],
            'pagador_email' => isset($pedido['pagador_email']) ? $pedido['pagador_email'] : null,
            'pagador_nome' => isset($pedido['pagador_nome']) ? $pedido['pagador_nome'] : null,
            'pedido_codigo' => isset($pedido['codigo']) ? $pedido['codigo'] : null,
            'curso_nome' => isset($curso['nome']) ? $curso['nome'] : null,
            'turma_nome' => isset($turma['nome']) ? $turma['nome'] : null,
            'participante_nome' => isset($participante['nome']) ? $participante['nome'] : null,
            'participante_email' => isset($participante['email']) ? $participante['email'] : null,
            'certificado_codigo' => isset($certificado['codigo']) ? $certificado['codigo'] : null,
            'certificado_pdf_url' => isset($certificado['codigo']) ? Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo'])) : null,
            'certificado_validacao_url' => isset($certificado['codigo']) ? Helpers::url('certificados/validar?codigo=' . urlencode($certificado['codigo'])) : null,
        );
    }

    private function pedidoPodeSerAcessadoPor(array $pedido, $usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver')) {
            return true;
        }

        return ((int) $pedido['comprador_usuario_id'] === (int) $usuarioId)
            || ((int) $pedido['pagador_usuario_id'] === (int) $usuarioId);
    }

    private function inscricaoPodeSerAcessadaPor(array $inscricao, $usuarioId)
    {
        if (!$usuarioId) {
            return false;
        }

        if ($this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver')) {
            return true;
        }

        $pedido = $this->pedidoModel->findById((int) $inscricao['pedido_id']);
        if (!$pedido) {
            return false;
        }

        return $this->pedidoPodeSerAcessadoPor($pedido, $usuarioId);
    }

    private function registrarAcessoNegado($evento, $inscricaoId, $usuarioId, $ipAddress = null, $userAgent = null)
    {
        $payload = array(
            'inscricao_id' => $inscricaoId,
            'usuario_id' => $usuarioId,
            'ip_address' => $ipAddress,
        );

        $this->auditService->record($evento, 'inscricao', $inscricaoId, $payload, $usuarioId, $ipAddress, $userAgent);
        Logger::error($evento, $payload);
    }
}




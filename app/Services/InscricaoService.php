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
                        $inscricaoExistente = $this->inscricaoModel->findAcessoAtivoPorUsuarioTurma($usuarioIdParticipante, $turmaId);
                        if ($inscricaoExistente) {
                            $pdo->rollBack();
                            return array(
                                'ok' => false,
                                'message' => 'Você já está matriculado neste curso.',
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
                    $this->emailService()->cursoProximo(
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
            $inscricaoExistente = $this->inscricaoModel->findAcessoAtivoPorUsuarioTurma($usuarioId, $turmaId);
            if ($inscricaoExistente) {
                return array('ok' => false, 'message' => 'Você já está matriculado neste curso.');
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
            $resultadoEmail = null;
            if ($novoStatus === 'com_pendencia') {
                $resultadoEmail = $this->emailService()->pendencia($this->envelopeInscricaoParaEmail($inscricaoAtualizada), $observacao, $actorUserId, $ipAddress, $userAgent);
            } elseif ($novoStatus === 'em_andamento') {
                $resultadoEmail = $this->emailService()->cursoProximo($this->envelopeInscricaoParaEmail($inscricaoAtualizada), $actorUserId, $ipAddress, $userAgent);
            } elseif ($novoStatus === 'concluida' || $novoStatus === 'concluida_sem_certificado') {
                $resultadoEmail = $this->emailService()->concluido($this->envelopeInscricaoParaEmail($inscricaoAtualizada), $actorUserId, $ipAddress, $userAgent);
            } elseif ($novoStatus === 'certificado_emitido') {
                $resultadoEmail = $this->emailService()->certificadoDisponivel($this->envelopeInscricaoParaEmail($inscricaoAtualizada), $actorUserId, $ipAddress, $userAgent);
            }

            return array('ok' => true, 'email_result' => $resultadoEmail);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('inscricao.status.falhou', array(
                'inscricao_id' => $inscricaoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    private function emailService()
    {
        if (!$this->emailService) {
            $this->emailService = new EmailService();
        }

        return $this->emailService;
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
        return !empty($this->inscricaoModel->findAcessoAtivoPorUsuarioTurma($usuarioId, $turmaId));
    }

    public function situacaoAlunoNoCurso($usuarioId, $cursoId, $turmaId = null)
    {
        $usuarioId = (int) $usuarioId;
        $cursoId = (int) $cursoId;
        $turmaId = $turmaId !== null && (int) $turmaId > 0 ? (int) $turmaId : null;

        $situacao = array(
            'status_fluxo' => 'nao_inscrito',
            'bloquear_nova_inscricao' => false,
            'permitir_nova_inscricao' => true,
            'permitir_continuar_pagamento' => false,
            'pedido_id' => null,
            'checkout_url' => null,
            'pedido_status' => null,
            'inscricao_status' => null,
            'inscricao_id' => null,
            'curso_id' => $cursoId,
            'turma_id' => $turmaId,
        );

        if ($usuarioId <= 0 || $cursoId <= 0) {
            return $situacao;
        }

        $inscricaoAtiva = $turmaId !== null
            ? $this->inscricaoModel->findAcessoAtivoPorUsuarioTurma($usuarioId, $turmaId)
            : $this->inscricaoModel->findAcessoAtivoPorUsuarioCurso($usuarioId, $cursoId);

        if ($inscricaoAtiva) {
            $situacao['status_fluxo'] = 'matriculado';
            $situacao['bloquear_nova_inscricao'] = true;
            $situacao['permitir_nova_inscricao'] = false;
            $situacao['inscricao_id'] = isset($inscricaoAtiva['id']) ? (int) $inscricaoAtiva['id'] : null;
            $situacao['inscricao_status'] = isset($inscricaoAtiva['status']) ? (string) $inscricaoAtiva['status'] : null;
            $situacao['pedido_id'] = isset($inscricaoAtiva['pedido_id']) ? (int) $inscricaoAtiva['pedido_id'] : null;
            $situacao['pedido_status'] = isset($inscricaoAtiva['pedido_status']) ? (string) $inscricaoAtiva['pedido_status'] : null;

            return $situacao;
        }

        $pedidoPendente = $this->pedidoModel->findPedidoPendenteDoAlunoCurso($usuarioId, $cursoId, $turmaId);
        if ($pedidoPendente) {
            $situacao['status_fluxo'] = 'pendente_pagamento';
            $situacao['bloquear_nova_inscricao'] = false;
            $situacao['permitir_nova_inscricao'] = false;
            $situacao['permitir_continuar_pagamento'] = true;
            $situacao['pedido_id'] = isset($pedidoPendente['id']) ? (int) $pedidoPendente['id'] : null;
            $situacao['pedido_status'] = isset($pedidoPendente['status']) ? (string) $pedidoPendente['status'] : null;
            $situacao['checkout_url'] = !empty($pedidoPendente['payment_provider_payment_url'])
                ? (string) $pedidoPendente['payment_provider_payment_url']
                : '/checkout/resumo?pedido_id=' . (int) $pedidoPendente['id'];
            $situacao['turma_id'] = !empty($pedidoPendente['turma_id']) ? (int) $pedidoPendente['turma_id'] : $situacao['turma_id'];

            return $situacao;
        }

        $pedidoHistorico = $this->pedidoModel->findUltimoDoAlunoCurso($usuarioId, $cursoId, $turmaId);
        $inscricaoHistorica = $this->inscricaoModel->findUltimaPorUsuarioCurso($usuarioId, $cursoId, $turmaId);
        $registroMaisRecente = $this->selecionarRegistroMaisRecenteFluxo($pedidoHistorico, $inscricaoHistorica);

        if (!$registroMaisRecente) {
            return $situacao;
        }

        $statusFluxo = $this->mapearStatusFluxoInscricao(isset($registroMaisRecente['status_original']) ? $registroMaisRecente['status_original'] : '');
        $pedidoStatusRegistro = strtolower(trim((string) ($registroMaisRecente['pedido_status'] ?? '')));
        if ($statusFluxo === 'pendente_pagamento' && in_array($pedidoStatusRegistro, array('cancelado', 'cancelada', 'cancelled', 'expirado', 'vencido', 'expired', 'falhou', 'failed', 'recusado', 'reprovado'), true)) {
            if (in_array($pedidoStatusRegistro, array('cancelado', 'cancelada', 'cancelled'), true)) {
                $statusFluxo = 'cancelado';
            } elseif (in_array($pedidoStatusRegistro, array('expirado', 'vencido', 'expired'), true)) {
                $statusFluxo = 'expirado';
            } else {
                $statusFluxo = 'falhou';
            }
            $registroMaisRecente['status_original'] = $pedidoStatusRegistro;
        }
        if ($statusFluxo !== 'nao_inscrito') {
            $situacao['status_fluxo'] = $statusFluxo;
            $situacao['permitir_nova_inscricao'] = true;
            $situacao['bloquear_nova_inscricao'] = false;
            $situacao['pedido_id'] = !empty($registroMaisRecente['pedido_id']) ? (int) $registroMaisRecente['pedido_id'] : null;
            $situacao['pedido_status'] = !empty($registroMaisRecente['pedido_status']) ? (string) $registroMaisRecente['pedido_status'] : null;
            $situacao['inscricao_id'] = !empty($registroMaisRecente['inscricao_id']) ? (int) $registroMaisRecente['inscricao_id'] : null;
            $situacao['inscricao_status'] = !empty($registroMaisRecente['inscricao_status']) ? (string) $registroMaisRecente['inscricao_status'] : null;
            $situacao['turma_id'] = !empty($registroMaisRecente['turma_id']) ? (int) $registroMaisRecente['turma_id'] : $situacao['turma_id'];
        }

        return $situacao;
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
            'certificado_url_download' => isset($certificado['codigo']) ? Helpers::url('certificados/pdf?codigo=' . urlencode($certificado['codigo'])) : null,
            'certificado_pdf_caminho' => isset($certificado['pdf_caminho']) ? $certificado['pdf_caminho'] : null,
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

    private function selecionarRegistroMaisRecenteFluxo(?array $pedido = null, ?array $inscricao = null)
    {
        $pedidoData = null;
        if (!empty($pedido)) {
            $pedidoData = array(
                'origem' => 'pedido',
                'id' => isset($pedido['id']) ? (int) $pedido['id'] : null,
                'pedido_id' => isset($pedido['id']) ? (int) $pedido['id'] : null,
                'inscricao_id' => null,
                'status_original' => isset($pedido['status']) ? (string) $pedido['status'] : '',
                'pedido_status' => isset($pedido['status']) ? (string) $pedido['status'] : null,
                'inscricao_status' => null,
                'turma_id' => isset($pedido['turma_id']) ? (int) $pedido['turma_id'] : null,
                'created_at' => isset($pedido['created_at']) ? (string) $pedido['created_at'] : null,
            );
        }

        $inscricaoData = null;
        if (!empty($inscricao)) {
            $inscricaoData = array(
                'origem' => 'inscricao',
                'id' => isset($inscricao['id']) ? (int) $inscricao['id'] : null,
                'pedido_id' => isset($inscricao['pedido_id']) ? (int) $inscricao['pedido_id'] : null,
                'inscricao_id' => isset($inscricao['id']) ? (int) $inscricao['id'] : null,
                'status_original' => isset($inscricao['status']) ? (string) $inscricao['status'] : '',
                'pedido_status' => isset($inscricao['pedido_status']) ? (string) $inscricao['pedido_status'] : null,
                'inscricao_status' => isset($inscricao['status']) ? (string) $inscricao['status'] : null,
                'turma_id' => isset($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null,
                'created_at' => isset($inscricao['created_at']) ? (string) $inscricao['created_at'] : null,
            );
        }

        if (!$pedidoData) {
            return $inscricaoData;
        }

        if (!$inscricaoData) {
            return $pedidoData;
        }

        $pedidoTimestamp = !empty($pedidoData['created_at']) ? strtotime($pedidoData['created_at']) : 0;
        $inscricaoTimestamp = !empty($inscricaoData['created_at']) ? strtotime($inscricaoData['created_at']) : 0;

        if ($pedidoTimestamp === $inscricaoTimestamp) {
            return $pedidoData['id'] >= $inscricaoData['id'] ? $pedidoData : $inscricaoData;
        }

        return $pedidoTimestamp >= $inscricaoTimestamp ? $pedidoData : $inscricaoData;
    }

    private function mapearStatusFluxoInscricao($status)
    {
        $status = strtolower(trim((string) $status));

        $statusAtivos = array('pago', 'aprovado', 'confirmado', 'ativo', 'concluido', 'matriculado');
        if (in_array($status, $statusAtivos, true)) {
            return 'matriculado';
        }

        $statusPendentes = array(
            'pendente',
            'aguardando_pagamento',
            'aguardando_pix',
            'checkout_criado',
            'em_aberto',
            'pending',
            'rascunho',
            'pendencia',
            'aguardando_reenvio',
            'comprovante_enviado',
            'em_analise',
        );
        if (in_array($status, $statusPendentes, true)) {
            return 'pendente_pagamento';
        }

        $statusCancelados = array('cancelado', 'cancelada', 'cancelled');
        if (in_array($status, $statusCancelados, true)) {
            return 'cancelado';
        }

        $statusExpirados = array('expirado', 'vencido', 'expired');
        if (in_array($status, $statusExpirados, true)) {
            return 'expirado';
        }

        $statusFalhas = array('falhou', 'failed');
        if (in_array($status, $statusFalhas, true)) {
            return 'falhou';
        }

        $statusReprovados = array('recusado', 'reprovado');
        if (in_array($status, $statusReprovados, true)) {
            return 'reprovado';
        }

        return 'nao_inscrito';
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




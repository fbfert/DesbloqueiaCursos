<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\CursoEvento;
use App\Models\ComprovantePix;
use App\Models\Inscricao;
use App\Models\PedidoCupom;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\ParticipantePedido;
use App\Models\Turma;
use Exception;

class PedidoService
{
    private $pedidoModel;
    private $pedidoItemModel;
    private $participanteModel;
    private $comprovanteModel;
    private $inscricaoModel;
    private $cursoModel;
    private $cursoService;
    private $turmaModel;
    private $pedidoCupomModel;
    private $cupomService;
    private $emailService;
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
        $this->cursoModel = new CursoEvento();
        $this->cursoService = new CursoService();
        $this->turmaModel = new Turma();
        $this->pedidoCupomModel = new PedidoCupom();
        $this->cupomService = new CupomService();
        $this->emailService = new EmailService();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
        $this->rbacService = new RbacService();
    }

    public function criarCheckoutDraft(array $dados, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cursoId = isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : 0;
        $turmaId = !empty($dados['turma_id']) ? (int) $dados['turma_id'] : null;
        $quantidade = isset($dados['quantidade']) ? max(1, (int) $dados['quantidade']) : 1;

        $curso = $this->cursoModel->findPublicById($cursoId);
        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso nao encontrado.');
        }

        $turma = null;
        if ($turmaId) {
            $turma = $this->turmaModel->findPublicById($turmaId);
            if (!$turma || (int) $turma['curso_evento_id'] !== $cursoId) {
                return array('ok' => false, 'message' => 'Turma invalida para o curso selecionado.');
            }
        }

        $valorBaseCurso = (float) $this->cursoService->calcularValorEfetivoCurso($curso);

        $valorUnitario = isset($dados['valor_unitario']) && $dados['valor_unitario'] !== ''
            ? (float) $dados['valor_unitario']
            : (float) ($turma && $turma['valor_override'] !== null && $turma['valor_override'] !== '' ? $turma['valor_override'] : $valorBaseCurso);

        $subtotal = $valorUnitario * $quantidade;
        $pedidoData = array(
            'codigo' => isset($dados['codigo']) && $dados['codigo'] !== '' ? $dados['codigo'] : 'PR-' . date('YmdHis') . '-' . strtoupper(substr(sha1(random_bytes(8)), 0, 6)),
            'comprador_usuario_id' => isset($dados['comprador_usuario_id']) ? $dados['comprador_usuario_id'] : null,
            'pagador_usuario_id' => isset($dados['pagador_usuario_id']) ? $dados['pagador_usuario_id'] : null,
            'pagador_nome' => isset($dados['pagador_nome']) ? $dados['pagador_nome'] : null,
            'pagador_cpf' => isset($dados['pagador_cpf']) ? $dados['pagador_cpf'] : null,
            'pagador_email' => isset($dados['pagador_email']) ? $dados['pagador_email'] : null,
            'pagador_telefone' => isset($dados['pagador_telefone']) ? $dados['pagador_telefone'] : null,
            'pagador_cidade' => isset($dados['pagador_cidade']) ? $dados['pagador_cidade'] : null,
            'pagador_estado' => isset($dados['pagador_estado']) ? $dados['pagador_estado'] : null,
            'pagador_empresa_nome' => isset($dados['pagador_empresa_nome']) ? $dados['pagador_empresa_nome'] : null,
            'pagador_empresa_documento' => isset($dados['pagador_empresa_documento']) ? $dados['pagador_empresa_documento'] : null,
            'tipo_pedido' => isset($dados['tipo_pedido']) ? $dados['tipo_pedido'] : 'propria',
            'status' => 'rascunho',
            'subtotal' => $subtotal,
            'desconto_total' => 0,
            'acrescimo_total' => 0,
            'total' => $subtotal,
            'observacoes_internas' => isset($dados['observacoes_internas']) ? $dados['observacoes_internas'] : null,
            'observacoes_publicas' => isset($dados['observacoes_publicas']) ? $dados['observacoes_publicas'] : null,
            'canal_origem' => isset($dados['canal_origem']) ? $dados['canal_origem'] : 'web',
        );

        $itemData = array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId,
            'quantidade' => $quantidade,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $subtotal,
            'status' => 'ativo',
        );

        $resultado = $this->createPedido($pedidoData, array($itemData), array(), $actorUserId, $ipAddress, $userAgent);

        return array(
            'ok' => true,
            'pedido_id' => $resultado['pedido_id'],
            'pedido' => $this->pedidoModel->findById($resultado['pedido_id']),
            'curso' => $curso,
            'turma' => $turma,
        );
    }

    public function adicionarParticipantesAoPedido($pedidoId, array $participantes, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.participantes.negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para alterar este pedido.');
        }

        if (!$this->pedidoPodeReceberParticipantes($pedido)) {
            $this->registrarAcessoNegado(
                'pedido.participantes.estado_invalido',
                $pedidoId,
                $actorUserId,
                $ipAddress,
                $userAgent,
                array('status_atual' => $pedido['status'])
            );

            return array('ok' => false, 'message' => 'Pedido nao pode receber participantes neste status.');
        }

        $itens = $this->pedidoItemModel->forPedido($pedidoId);
        if (empty($itens)) {
            return array('ok' => false, 'message' => 'Pedido sem itens.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $created = array();
            foreach ($participantes as $posicao => $participante) {
                if (trim((string) (isset($participante['nome']) ? $participante['nome'] : '')) === '') {
                    continue;
                }

                $pedidoItemId = isset($participante['pedido_item_id']) && $participante['pedido_item_id']
                    ? (int) $participante['pedido_item_id']
                    : (int) $itens[0]['id'];

                $payload = array(
                    'pedido_id' => $pedidoId,
                    'pedido_item_id' => $pedidoItemId,
                    'usuario_id' => isset($participante['usuario_id']) ? $participante['usuario_id'] : null,
                    'nome' => isset($participante['nome']) ? $participante['nome'] : null,
                    'cpf' => isset($participante['cpf']) ? $participante['cpf'] : null,
                    'email' => isset($participante['email']) ? $participante['email'] : null,
                    'telefone' => isset($participante['telefone']) ? $participante['telefone'] : null,
                    'ordem' => isset($participante['ordem']) ? (int) $participante['ordem'] : $posicao + 1,
                    'status' => isset($participante['status']) ? $participante['status'] : 'ativo',
                );

                $created[] = $this->participanteModel->create($payload);
            }

            $this->auditService->record(
                'checkout.participantes.salvos',
                'pedido',
                $pedidoId,
                array('participantes' => $created),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('checkout.participantes.salvos', array(
                'pedido_id' => $pedidoId,
                'total' => count($created),
            ));

            $pdo->commit();

            return array('ok' => true, 'participantes_ids' => $created);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('checkout.participantes.falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function sincronizarParticipanteCompraPropria($pedidoId, array $participante, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->isCompraPropriaPedido(isset($pedido['tipo_pedido']) ? $pedido['tipo_pedido'] : '')) {
            return array('ok' => false, 'message' => 'Pedido nao e de compra propria.');
        }

        $itens = $this->pedidoItemModel->forPedido($pedidoId);
        if (empty($itens)) {
            return array('ok' => false, 'message' => 'Pedido sem itens.');
        }

        $pedidoItemId = isset($itens[0]['id']) ? (int) $itens[0]['id'] : null;
        if ($pedidoItemId <= 0) {
            return array('ok' => false, 'message' => 'Pedido sem item valido.');
        }

        $payload = array(
            'pedido_id' => $pedidoId,
            'pedido_item_id' => $pedidoItemId,
            'usuario_id' => isset($participante['usuario_id']) ? (int) $participante['usuario_id'] : null,
            'nome' => isset($participante['nome']) ? trim((string) $participante['nome']) : '',
            'cpf' => isset($participante['cpf']) ? trim((string) $participante['cpf']) : null,
            'email' => isset($participante['email']) ? trim((string) $participante['email']) : null,
            'telefone' => isset($participante['telefone']) ? trim((string) $participante['telefone']) : null,
            'ordem' => 1,
            'status' => 'ativo',
        );

        if ($payload['nome'] === '') {
            return array('ok' => false, 'message' => 'Informe os dados do pagador para gerar o participante automatico.');
        }

        $participantesExistentes = $this->participanteModel->forPedido($pedidoId);
        $participanteExistente = $this->encontrarParticipanteCompraPropria($participantesExistentes, $payload);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($participanteExistente) {
                $this->participanteModel->update((int) $participanteExistente['id'], $payload);
                $participanteId = (int) $participanteExistente['id'];
                $acao = 'checkout.participante_auto.atualizado';
            } else {
                $participanteId = $this->participanteModel->create($payload);
                $acao = 'checkout.participante_auto.criado';
            }

            $this->auditService->record(
                $acao,
                'pedido',
                $pedidoId,
                array(
                    'pedido_item_id' => $pedidoItemId,
                    'participante_id' => $participanteId,
                    'usuario_id' => $payload['usuario_id'],
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array(
                'pedido_id' => $pedidoId,
                'pedido_item_id' => $pedidoItemId,
                'participante_id' => $participanteId,
                'usuario_id' => $payload['usuario_id'],
            ));

            $pdo->commit();

            return array('ok' => true, 'participante_id' => $participanteId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('checkout.participante_auto.falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function finalizarCheckout($pedidoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.checkout.finalizar_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para finalizar este pedido.');
        }

        if ($pedido['status'] === 'aguardando_pagamento') {
            return array('ok' => true, 'message' => 'Pedido ja foi finalizado.');
        }

        if (!$this->pedidoPodeSerFinalizado($pedido)) {
            $this->registrarAcessoNegado(
                'pedido.checkout.finalizar_estado_invalido',
                $pedidoId,
                $actorUserId,
                $ipAddress,
                $userAgent,
                array('status_atual' => $pedido['status'])
            );

            return array('ok' => false, 'message' => 'Pedido nao pode ser finalizado neste status.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $revalidacao = $this->revalidarCupomAoFecharPedido($pedidoId, $actorUserId, $ipAddress, $userAgent);
            if (isset($revalidacao['ok']) && $revalidacao['ok'] === false) {
                $pdo->rollBack();
                return array(
                    'ok' => false,
                    'message' => isset($revalidacao['message']) ? $revalidacao['message'] : 'Cupom invalido no fechamento do pedido.',
                );
            }

            $this->pedidoModel->markAwaitingPayment($pedidoId);
            $this->pedidoModel->addStatusHistory($pedidoId, $pedido['status'], 'aguardando_pagamento', 'Checkout concluido', $actorUserId);

            $this->auditService->record(
                'checkout.finalizado',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => $pedido['status'],
                    'status_novo' => 'aguardando_pagamento',
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('checkout.finalizado', array('pedido_id' => $pedidoId));

            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('checkout.finalizar_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function detalharCheckout($pedidoId, $usuarioId = null, $exigirUsuarioAutenticado = false)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('pedido' => null);
        }

        if ($exigirUsuarioAutenticado && !$usuarioId) {
            $this->registrarAcessoNegado('pedido.detalhar_negado', $pedidoId, null, null, null, array(
                'motivo' => 'auth_ausente',
            ));
            return array('pedido' => null);
        }

        $canSeePix = $this->pedidoPodeSerAcessadoPor($pedido, $usuarioId);

        if ($usuarioId !== null && !$canSeePix) {
            $this->registrarAcessoNegado('pedido.detalhar_negado', $pedidoId, $usuarioId, null, null);
            return array('pedido' => null);
        }

        $pedido['itens'] = $this->pedidoItemModel->forPedido($pedidoId);
        $pedido['participantes'] = $this->participanteModel->forPedido($pedidoId);
        $pedido['inscricoes'] = $this->inscricaoModel->forPedido($pedidoId);
        $pedido['cupom'] = $this->pedidoCupomModel->findByPedido($pedidoId);
        $pedido['comprovante_atual'] = $this->comprovanteModel->findByPedido($pedidoId);
        $pedido['comprovantes'] = $this->comprovanteModel->versionsForPedido($pedidoId);

        if (!$canSeePix) {
            $pedido['comprovante_atual'] = null;
            $pedido['comprovantes'] = array();
        }

        return array(
            'pedido' => $pedido,
            'can_see_pix' => $canSeePix,
        );
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

            $this->emailService->pedidoCriado($this->pedidoModel->findById($pedidoId), $actorUserId, $ipAddress, $userAgent);

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

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.status.negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para alterar este pedido.');
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

            if (in_array($novoStatus, array('pendencia', 'aguardando_reenvio', 'aprovado'), true)) {
                $pedidoAtualizado = $this->pedidoModel->findById($pedidoId);
                if ($novoStatus === 'pendencia') {
                    $this->emailService->pendencia($pedidoAtualizado, $observacao, $actorUserId, $ipAddress, $userAgent);
                } elseif ($novoStatus === 'aguardando_reenvio') {
                    $this->emailService->pendencia($pedidoAtualizado, $observacao, $actorUserId, $ipAddress, $userAgent);
                } elseif ($novoStatus === 'aprovado') {
                    $this->emailService->pedidoAprovado($pedidoAtualizado, $observacao, $actorUserId, $ipAddress, $userAgent);
                }
            }

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
        if (empty($dados['pedido_id'])) {
            return array('ok' => false, 'message' => 'Pedido nao informado.');
        }

        $pedidoId = (int) $dados['pedido_id'];
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.comprovante.negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para anexar comprovante neste pedido.');
        }

        if (!$this->pedidoPodeReceberComprovante($pedido)) {
            $this->registrarAcessoNegado(
                'pedido.comprovante.estado_invalido',
                $pedidoId,
                $actorUserId,
                $ipAddress,
                $userAgent,
                array('status_atual' => $pedido['status'])
            );

            return array('ok' => false, 'message' => 'Pedido nao aceita comprovante neste status.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $existente = $this->comprovanteModel->findByPedido($pedidoId);

            if ($existente) {
                $dados['motivo_reenvio'] = isset($dados['motivo_reenvio']) ? $dados['motivo_reenvio'] : 'Reenvio do comprovante PIX';
                $comprovanteId = $this->comprovanteModel->createVersion($pedidoId, $dados);
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
                'pedido_id' => $pedidoId,
            ));

            $pdo->commit();

            $pedidoAtualizado = $this->pedidoModel->findById($pedidoId);
            $this->emailService->comprovanteEnviado($pedidoAtualizado, $actorUserId, $ipAddress, $userAgent);

            return array('ok' => true, 'comprovante_pix_id' => $comprovanteId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('comprovante_pix.falhou', array(
                'pedido_id' => $pedidoId,
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

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.excluir_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para excluir este pedido.');
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

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.aprovar_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para aprovar este pedido.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $comprovanteAtual = $this->comprovanteModel->findCurrentByPedido($pedidoId);
            $statusNovoPedido = $comprovanteAtual ? 'pago' : 'aprovado';

            if ($statusNovoPedido === 'pago') {
                $this->pedidoModel->updateStatus($pedidoId, 'pago');
            } else {
                $this->pedidoModel->markApproved($pedidoId, $actorUserId);
            }

            if ($comprovanteAtual && (string) $comprovanteAtual['status'] !== 'aprovado') {
                $this->comprovanteModel->updateStatus(
                    (int) $comprovanteAtual['id'],
                    'aprovado',
                    $observacao,
                    $actorUserId,
                    date('Y-m-d H:i:s')
                );
            }

            $this->pedidoModel->addStatusHistory($pedidoId, $pedido['status'], $statusNovoPedido, $observacao, $actorUserId);
            $this->sincronizarInscricoesAprovadas($pedidoId, $actorUserId);

            $this->auditService->record(
                'pedido.aprovado',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => $pedido['status'],
                    'status_novo' => $statusNovoPedido,
                    'comprovante_pix_id' => $comprovanteAtual ? (int) $comprovanteAtual['id'] : null,
                    'observacao' => $observacao,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.aprovado', array(
                'pedido_id' => $pedidoId,
                'status_novo' => $statusNovoPedido,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            $this->emailService->pedidoAprovado($this->pedidoModel->findById($pedidoId), $observacao, $actorUserId, $ipAddress, $userAgent);

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

    private function sincronizarInscricoesAprovadas($pedidoId, $actorUserId = null)
    {
        $inscricoes = $this->inscricaoModel->forPedido($pedidoId);
        if (empty($inscricoes)) {
            return;
        }

        foreach ($inscricoes as $inscricao) {
            $statusAtual = isset($inscricao['status']) ? (string) $inscricao['status'] : '';
            if (in_array($statusAtual, array('pendente', 'em_analise', 'aprovado'), true)) {
                $this->inscricaoModel->updateStatus((int) $inscricao['id'], 'ativa', date('Y-m-d H:i:s'));
                $this->inscricaoModel->addStatusHistory(
                    (int) $inscricao['id'],
                    $statusAtual,
                    'ativa',
                    'Inscrição ativada automaticamente após aprovação do pedido.',
                    $actorUserId
                );
            }
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

    public function aplicarCupomAoPedido($pedidoId, $cupomCodigo, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.cupom.negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para aplicar cupom neste pedido.');
        }

        return $this->cupomService->aplicarAoPedido($pedidoId, $cupomCodigo, $actorUserId, $ipAddress, $userAgent);
    }

    public function revalidarCupomAoFecharPedido($pedidoId, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.cupom.revalidar_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para revalidar cupom neste pedido.');
        }

        return $this->cupomService->revalidarNoFechamento($pedidoId, $actorUserId, $ipAddress, $userAgent);
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

    private function pedidoPodeSerFinalizado(array $pedido)
    {
        return in_array($pedido['status'], array('rascunho', 'pendencia', 'aguardando_reenvio', 'aguardando_pagamento'), true);
    }

    private function pedidoPodeReceberParticipantes(array $pedido)
    {
        return in_array($pedido['status'], array('rascunho', 'pendencia', 'aguardando_reenvio'), true);
    }

    private function pedidoPodeReceberComprovante(array $pedido)
    {
        return in_array($pedido['status'], array('aguardando_pagamento', 'comprovante_enviado', 'pendencia', 'aguardando_reenvio'), true);
    }

    private function isCompraPropriaPedido($tipoPedido)
    {
        $normalizado = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $tipoPedido), 'UTF-8') : strtolower(trim((string) $tipoPedido));

        return in_array($normalizado, array('propria', 'própria', 'compra_propria', 'compra_própria'), true);
    }

    private function encontrarParticipanteCompraPropria(array $participantes, array $dados)
    {
        $usuarioId = isset($dados['usuario_id']) ? (int) $dados['usuario_id'] : 0;
        $cpf = isset($dados['cpf']) ? preg_replace('/\D+/', '', (string) $dados['cpf']) : '';
        $pedidoItemId = isset($dados['pedido_item_id']) ? (int) $dados['pedido_item_id'] : 0;

        foreach ($participantes as $participante) {
            if ($pedidoItemId > 0 && isset($participante['pedido_item_id']) && (int) $participante['pedido_item_id'] === $pedidoItemId) {
                return $participante;
            }

            $participanteUsuarioId = isset($participante['usuario_id']) ? (int) $participante['usuario_id'] : 0;
            if ($usuarioId > 0 && $participanteUsuarioId === $usuarioId) {
                return $participante;
            }

            if ($cpf !== '' && preg_replace('/\D+/', '', (string) $participante['cpf']) === $cpf) {
                return $participante;
            }
        }

        return null;
    }

    private function registrarAcessoNegado($evento, $pedidoId, $usuarioId, $ipAddress = null, $userAgent = null, array $context = array())
    {
        $payload = array_merge($context, array(
            'pedido_id' => $pedidoId,
            'usuario_id' => $usuarioId,
            'ip_address' => $ipAddress,
        ));

        $this->auditService->record($evento, 'pedido', $pedidoId, $payload, $usuarioId, $ipAddress, $userAgent);
        Logger::error($evento, $payload);
    }
}




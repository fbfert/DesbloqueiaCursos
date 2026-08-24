<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Validator;
use App\Models\CursoEvento;
use App\Models\ComprovantePix;
use App\Models\Inscricao;
use App\Models\PedidoCupom;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\PagamentoGatewayLog;
use App\Models\PagamentoGatewayTransacao;
use App\Models\ParticipantePedido;
use App\Models\Usuario;
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
    private $gatewayLogModel;
    private $gatewayTransacaoModel;
    private $cupomService;
    private $emailService;
    private $auditService;
    private $inscricaoService;
    private $trashService;
    private $rbacService;
    private $usuarioModel;

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
        $this->gatewayLogModel = new PagamentoGatewayLog();
        $this->gatewayTransacaoModel = new PagamentoGatewayTransacao();
        $this->cupomService = new CupomService();
        $this->emailService = new EmailService();
        $this->auditService = new AuditService();
        $this->inscricaoService = new InscricaoService();
        $this->trashService = new TrashService();
        $this->rbacService = new RbacService();
        $this->usuarioModel = new Usuario();
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

    public function dadosPedidoManual()
    {
        return array(
            'cursos' => $this->cursoModel->allForSelect(array('ativo')),
            'turmas' => $this->turmaModel->allForSelect(),
        );
    }

    public function buscarAlunosPedidoManual($termo, $limit = 12)
    {
        return $this->usuarioModel->buscarAlunosParaPedidoManual($termo, $limit);
    }

    public function obterAlunoPedidoManual($alunoUsuarioId)
    {
        return $this->usuarioModel->findAlunoById((int) $alunoUsuarioId);
    }

    public function criarPedidoManual(array $dados, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $alunoUsuarioId = isset($dados['aluno_usuario_id']) ? (int) $dados['aluno_usuario_id'] : 0;
        $cursoId = isset($dados['curso_evento_id']) ? (int) $dados['curso_evento_id'] : 0;
        $turmaId = !empty($dados['turma_id']) ? (int) $dados['turma_id'] : null;
        $cupomCodigo = trim((string) ($dados['cupom_codigo'] ?? ''));
        $cupomJustificativa = trim((string) ($dados['cupom_justificativa'] ?? ''));
        $observacoesInternas = trim((string) ($dados['observacoes_internas'] ?? ''));

        if ($alunoUsuarioId <= 0) {
            return array('ok' => false, 'message' => 'Selecione um aluno.');
        }

        $aluno = $this->usuarioModel->findAlunoById($alunoUsuarioId);
        if (!$aluno) {
            return array('ok' => false, 'message' => 'Aluno não encontrado ou inativo.');
        }

        if ($cursoId <= 0) {
            return array('ok' => false, 'message' => 'Selecione um curso.');
        }

        $curso = $this->cursoModel->findAdminById($cursoId);
        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso não encontrado.');
        }

        $turma = null;
        if ($turmaId !== null) {
            $turma = $this->turmaModel->findAdminById($turmaId);
            if (!$turma || (int) $turma['curso_evento_id'] !== $cursoId) {
                return array('ok' => false, 'message' => 'Turma inválida para o curso selecionado.');
            }
        }

        if (!empty($curso['usar_turmas']) && $turmaId === null) {
            return array('ok' => false, 'message' => 'Selecione uma turma para este curso.');
        }

        if ($turmaId !== null && $this->inscricaoModel->findAcessoAtivoPorUsuarioTurma($alunoUsuarioId, $turmaId)) {
            return array('ok' => false, 'message' => 'Este aluno já está matriculado neste curso.');
        }

        $pagadorNome = trim((string) ($dados['pagador_nome'] ?? ''));
        if ($pagadorNome === '') {
            $pagadorNome = (string) ($aluno['nome'] ?? '');
        }

        $pagadorCpf = trim((string) ($dados['pagador_cpf'] ?? ''));
        if ($pagadorCpf === '') {
            $pagadorCpf = (string) ($aluno['cpf'] ?? '');
        }

        $pagadorEmail = trim((string) ($dados['pagador_email'] ?? ''));
        if ($pagadorEmail === '') {
            $pagadorEmail = (string) ($aluno['email'] ?? '');
        }

        $pagadorTelefone = trim((string) ($dados['pagador_telefone'] ?? ''));
        if ($pagadorTelefone === '') {
            $pagadorTelefone = (string) ($aluno['telefone'] ?? '');
        }

        $erros = array();
        if ($pagadorNome === '') {
            $erros[] = 'Informe o nome do pagador.';
        }
        if (!Validator::cpf($pagadorCpf)) {
            $erros[] = 'Informe um CPF válido do pagador.';
        }
        if (!Validator::email($pagadorEmail)) {
            $erros[] = 'Informe um e-mail válido do pagador.';
        }
        if ($pagadorTelefone === '') {
            $erros[] = 'Informe o WhatsApp do pagador.';
        }
        if ($cupomCodigo !== '' && $cupomJustificativa === '') {
            $erros[] = 'Informe a justificativa para aplicar o cupom manualmente.';
        }

        if (!empty($erros)) {
            return array('ok' => false, 'errors' => $erros);
        }

        $valorBaseCurso = (float) $this->cursoService->calcularValorEfetivoCurso($curso);
        $valorUnitario = (float) ($turma && isset($turma['valor_override']) && $turma['valor_override'] !== null && $turma['valor_override'] !== ''
            ? $turma['valor_override']
            : $valorBaseCurso);
        $subtotal = round($valorUnitario, 2);

        $pedidoContexto = array(
            'status' => 'aguardando_pagamento',
            'subtotal_calculado' => $subtotal,
            'quantidade_total' => 1,
            'pagador_usuario_id' => $alunoUsuarioId,
            'comprador_usuario_id' => $alunoUsuarioId,
            'itens' => array(
                array(
                    'curso_evento_id' => $cursoId,
                    'turma_id' => $turmaId,
                    'quantidade' => 1,
                    'valor_unitario' => $valorUnitario,
                    'valor_total' => $subtotal,
                    'status' => 'ativo',
                    'curso_em_promocao' => !empty($curso['em_promocao']) ? 1 : 0,
                    'curso_tipo' => isset($curso['tipo']) ? $curso['tipo'] : '',
                ),
            ),
        );

        if ($cupomCodigo !== '') {
            $validacaoCupom = $this->cupomService->prevalidarAplicacaoPedidoManual($pedidoContexto, $cupomCodigo);
            if (empty($validacaoCupom['ok'])) {
                return array(
                    'ok' => false,
                    'errors' => isset($validacaoCupom['errors']) && is_array($validacaoCupom['errors'])
                        ? $validacaoCupom['errors']
                        : array(isset($validacaoCupom['message']) ? $validacaoCupom['message'] : 'Cupom inválido para este pedido.'),
                );
            }
        }

        $pedidoData = array(
            'codigo' => 'PR-' . date('YmdHis') . '-' . strtoupper(substr(sha1(random_bytes(8)), 0, 6)),
            'comprador_usuario_id' => $alunoUsuarioId,
            'pagador_usuario_id' => $alunoUsuarioId,
            'pagador_nome' => $pagadorNome,
            'pagador_cpf' => $pagadorCpf,
            'pagador_email' => $pagadorEmail,
            'pagador_telefone' => $pagadorTelefone,
            'tipo_pedido' => 'propria',
            'status' => 'aguardando_pagamento',
            'subtotal' => $subtotal,
            'desconto_total' => 0,
            'acrescimo_total' => 0,
            'total' => $subtotal,
            'observacoes_internas' => $observacoesInternas !== '' ? $observacoesInternas : null,
            'observacoes_publicas' => null,
            'canal_origem' => 'admin',
        );

        $itemData = array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId,
            'quantidade' => 1,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $subtotal,
            'status' => 'ativo',
            'participantes' => array(
                array(
                    'usuario_id' => $alunoUsuarioId,
                    'nome' => $pagadorNome,
                    'cpf' => $pagadorCpf,
                    'email' => $pagadorEmail,
                    'telefone' => $pagadorTelefone,
                    'status' => 'ativo',
                    'ordem' => 1,
                ),
            ),
        );

        $pedidoCriado = $this->createPedido($pedidoData, array($itemData), array(), $actorUserId, $ipAddress, $userAgent);
        if (empty($pedidoCriado['ok'])) {
            return $pedidoCriado;
        }

        $pedidoId = (int) $pedidoCriado['pedido_id'];

        if ($cupomCodigo !== '') {
            $aplicacaoCupom = $this->aplicarCupomManualAoPedido(
                $pedidoId,
                $cupomCodigo,
                $cupomJustificativa,
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            if (empty($aplicacaoCupom['ok'])) {
                return array(
                    'ok' => false,
                    'message' => isset($aplicacaoCupom['message']) ? $aplicacaoCupom['message'] : 'Não foi possível aplicar o cupom manualmente.',
                    'pedido_id' => $pedidoId,
                );
            }
        }

        $resultadoInscricoes = $this->inscricaoService->gerarDoPedido($pedidoId, $actorUserId, $ipAddress, $userAgent);
        if (empty($resultadoInscricoes['ok'])) {
            return array(
                'ok' => false,
                'message' => isset($resultadoInscricoes['message']) ? $resultadoInscricoes['message'] : 'Não foi possível gerar as inscrições do pedido.',
                'pedido_id' => $pedidoId,
            );
        }

        return array(
            'ok' => true,
            'pedido_id' => $pedidoId,
            'status_novo' => 'aguardando_pagamento',
            'cupom_aplicado' => $cupomCodigo !== '',
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

        if (in_array((string) $pedido['status'], array('aguardando_pagamento', 'aprovado', 'pago'), true)) {
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

            $pedidoAtualizado = $this->pedidoModel->findById($pedidoId);
            if (!$pedidoAtualizado) {
                $pdo->rollBack();
                return array('ok' => false, 'message' => 'Pedido nao encontrado.');
            }

            $autoAprovadoZeroValor = ((float) $pedidoAtualizado['total'] <= 0.0);
            if ($autoAprovadoZeroValor) {
                $this->pedidoModel->markApproved($pedidoId, $actorUserId);
                $this->pedidoModel->addStatusHistory(
                    $pedidoId,
                    $pedido['status'],
                    'aprovado',
                    'Checkout concluido com valor zero após cupom. Pedido aprovado automaticamente.',
                    $actorUserId
                );
                $this->sincronizarInscricoesAprovadas($pedidoId, $actorUserId);
            } else {
                $this->pedidoModel->markAwaitingPayment($pedidoId);
                $this->pedidoModel->addStatusHistory($pedidoId, $pedido['status'], 'aguardando_pagamento', 'Checkout concluido', $actorUserId);
            }

            $this->auditService->record(
                'checkout.finalizado',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => $pedido['status'],
                    'status_novo' => $autoAprovadoZeroValor ? 'aprovado' : 'aguardando_pagamento',
                    'auto_aprovado_zero_valor' => $autoAprovadoZeroValor,
                    'total' => (float) $pedido['total'],
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('checkout.finalizado', array(
                'pedido_id' => $pedidoId,
                'auto_aprovado_zero_valor' => $autoAprovadoZeroValor,
                'total' => (float) $pedidoAtualizado['total'],
            ));

            $pdo->commit();

            if ($autoAprovadoZeroValor) {
                $observacao = 'Pedido aprovado automaticamente porque o total final ficou em R$ 0,00 após aplicação de cupom.';
                try {
                    $this->emailService->pedidoAprovado($pedidoAtualizado, $observacao, $actorUserId, $ipAddress, $userAgent);
                    $this->emailService->pedidoAprovadoFinanceiroZeroValor($pedidoAtualizado, $observacao, $actorUserId, $ipAddress, $userAgent);
                } catch (Exception $emailException) {
                    Logger::error('checkout.finalizado.notificacao_falhou', array(
                        'pedido_id' => $pedidoId,
                        'message' => $emailException->getMessage(),
                    ));
                }

                return array(
                    'ok' => true,
                    'auto_aprovado_zero_valor' => true,
                    'message' => 'Pedido aprovado automaticamente. Não é necessário comprovante PIX.',
                );
            }

            return array('ok' => true, 'auto_aprovado_zero_valor' => false);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('checkout.finalizar_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function registrarCheckoutGateway($pedidoId, array $dadosGateway, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.gateway.checkout_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para iniciar este pagamento.');
        }

        $this->pedidoModel->updatePaymentGatewayData($pedidoId, $dadosGateway);

        $this->auditService->record(
            'pedido.gateway.checkout_registrado',
            'pedido',
            $pedidoId,
            array(
                'payment_gateway' => isset($dadosGateway['payment_gateway']) ? $dadosGateway['payment_gateway'] : null,
                'payment_external_id' => isset($dadosGateway['payment_external_id']) ? $dadosGateway['payment_external_id'] : null,
                'payment_provider_checkout_id' => isset($dadosGateway['payment_provider_checkout_id']) ? $dadosGateway['payment_provider_checkout_id'] : null,
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('pedido.gateway.checkout_registrado', array(
            'pedido_id' => $pedidoId,
            'payment_gateway' => isset($dadosGateway['payment_gateway']) ? $dadosGateway['payment_gateway'] : null,
        ));

        return array('ok' => true);
    }

    public function confirmarPagamentoGateway($pedidoId, array $dados = array(), $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if ((string) $pedido['status'] === 'pago') {
            return array('ok' => true, 'status_anterior' => 'pago', 'status_novo' => 'pago', 'already_paid' => true);
        }

        $statusAtual = isset($pedido['status']) ? (string) $pedido['status'] : '';
        if (in_array($statusAtual, array('cancelado', 'reembolsado'), true)) {
            return array('ok' => false, 'message' => 'Pedido nao pode ser confirmado neste status.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $pedidoAtualizado = $this->pedidoModel->findById($pedidoId);
            if (!$pedidoAtualizado) {
                $pdo->rollBack();
                return array('ok' => false, 'message' => 'Pedido nao encontrado.');
            }

            if ((string) $pedidoAtualizado['status'] !== 'pago') {
                $this->pedidoModel->markPaid($pedidoId, $actorUserId);
                $this->pedidoModel->addStatusHistory(
                    $pedidoId,
                    $pedidoAtualizado['status'],
                    'pago',
                    isset($dados['observacao']) ? $dados['observacao'] : 'Pagamento confirmado por gateway.',
                    $actorUserId
                );
                $this->sincronizarInscricoesAprovadas($pedidoId, $actorUserId);
            }

            $this->auditService->record(
                'pedido.pagamento_confirmado',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => $pedidoAtualizado['status'],
                    'status_novo' => 'pago',
                    'gateway' => isset($dados['gateway']) ? $dados['gateway'] : null,
                    'event_type' => isset($dados['event_type']) ? $dados['event_type'] : null,
                    'event_id' => isset($dados['event_id']) ? $dados['event_id'] : null,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.pagamento_confirmado', array(
                'pedido_id' => $pedidoId,
                'gateway' => isset($dados['gateway']) ? $dados['gateway'] : null,
                'event_id' => isset($dados['event_id']) ? $dados['event_id'] : null,
            ));

            $pdo->commit();

            $pedidoFinal = $this->pedidoModel->findById($pedidoId);
            if ($pedidoFinal) {
                $observacao = isset($dados['observacao']) ? $dados['observacao'] : 'Pagamento confirmado por gateway.';
                try {
                    $this->emailService->pedidoAprovado($pedidoFinal, $observacao, $actorUserId, $ipAddress, $userAgent);
                } catch (Exception $emailException) {
                    Logger::error('pedido.pagamento_confirmado.notificacao_falhou', array(
                        'pedido_id' => $pedidoId,
                        'message' => $emailException->getMessage(),
                    ));
                }
            }

            return array('ok' => true, 'status_anterior' => $statusAtual, 'status_novo' => 'pago');
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.pagamento_confirmado_falhou', array(
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
        $pedido['comprovante_aguardando_aprovacao'] = $this->pedidoTemComprovanteAguardandoAprovacao($pedido);

        if (!$canSeePix) {
            $pedido['comprovante_atual'] = null;
            $pedido['comprovantes'] = array();
            $pedido['comprovante_aguardando_aprovacao'] = false;
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
            $motivoReenvio = trim((string) (isset($dados['motivo_reenvio']) ? $dados['motivo_reenvio'] : ''));

            if ($existente) {
                if ($motivoReenvio === '') {
                    $pdo->rollBack();
                    return array('ok' => false, 'message' => 'Informe o motivo do reenvio.');
                }

                $dados['motivo_reenvio'] = $motivoReenvio;
                $comprovanteId = $this->comprovanteModel->createVersion($pedidoId, $dados);
                $acao = 'comprovante_pix.atualizado';
            } else {
                $dados['motivo_reenvio'] = null;
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

    public function reverterCancelamento($pedidoId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->usuarioPodeGerenciarPedidos($actorUserId)) {
            $this->registrarAcessoNegado('pedido.reverter_cancelamento_sem_permissao', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para reverter este cancelamento.');
        }

        $avaliacao = $this->avaliarReversaoCancelamentoPedido($pedidoId, $pedido);
        if (empty($avaliacao['ok'])) {
            $this->auditService->record(
                'pedido.cancelamento_reversao_bloqueada',
                'pedido',
                $pedidoId,
                array(
                    'motivos' => isset($avaliacao['motivos']) ? $avaliacao['motivos'] : array(),
                    'status' => $pedido['status'],
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.cancelamento_reversao_bloqueada', array(
                'pedido_id' => $pedidoId,
                'motivos' => isset($avaliacao['motivos']) ? $avaliacao['motivos'] : array(),
            ));

            return array(
                'ok' => false,
                'message' => isset($avaliacao['motivos_texto']) ? $avaliacao['motivos_texto'] : 'Não foi possível reverter o cancelamento do pedido.',
            );
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa da reversão do cancelamento.');
        }

        $statusAnterior = $avaliacao['status_anterior'];
        $observacao = 'Cancelamento revertido pelo administrador. Motivo: ' . $justificativa . '. Status restaurado: ' . $statusAnterior . '.';

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->pedidoModel->updateStatus($pedidoId, $statusAnterior);
            $this->pedidoModel->addStatusHistory($pedidoId, 'cancelado', $statusAnterior, $observacao, $actorUserId);

            $this->auditService->record(
                'pedido.cancelamento.revertido',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => 'cancelado',
                    'status_restaurado' => $statusAnterior,
                    'justificativa' => $justificativa,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.cancelamento.revertido', array(
                'pedido_id' => $pedidoId,
                'status_restaurado' => $statusAnterior,
                'usuario_id' => $actorUserId,
            ));

            if (in_array($statusAnterior, array('aprovado', 'pago'), true)) {
                $this->sincronizarInscricoesAprovadas($pedidoId, $actorUserId);
            }

            $pdo->commit();

            return array(
                'ok' => true,
                'status_restaurado' => $statusAnterior,
            );
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.cancelamento.reverter_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function reabrirCanceladoComoAguardandoPagamento($pedidoId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->usuarioPodeGerenciarPedidos($actorUserId)) {
            $this->registrarAcessoNegado('pedido.reabrir_aguardando_pagamento_sem_permissao', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para reabrir este pedido.');
        }

        if ((string) $pedido['status'] !== 'cancelado') {
            return array('ok' => false, 'message' => 'Somente pedidos cancelados podem ser reabertos como aguardando pagamento.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa da reabertura do pedido.');
        }

        $observacao = 'Pedido cancelado reaberto pelo administrador. Motivo: ' . $justificativa . '. Status restaurado: aguardando_pagamento.';

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->pedidoModel->updateStatus($pedidoId, 'aguardando_pagamento');
            $this->pedidoModel->addStatusHistory($pedidoId, 'cancelado', 'aguardando_pagamento', $observacao, $actorUserId);

            $this->auditService->record(
                'pedido.reaberto_aguardando_pagamento',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => 'cancelado',
                    'status_restaurado' => 'aguardando_pagamento',
                    'justificativa' => $justificativa,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.reaberto_aguardando_pagamento', array(
                'pedido_id' => $pedidoId,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array(
                'ok' => true,
                'status_restaurado' => 'aguardando_pagamento',
            );
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.reabrir_aguardando_pagamento_falhou', array(
                'pedido_id' => $pedidoId,
                'message' => $exception->getMessage(),
            ));

            throw $exception;
        }
    }

    public function marcarRascunhoComoAguardandoPagamento($pedidoId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);

        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido não encontrado.');
        }

        if (!$this->usuarioPodeGerenciarPedidos($actorUserId)) {
            $this->registrarAcessoNegado('pedido.rascunho_aguardando_pagamento_sem_permissao', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você não tem permissão para alterar este pedido.');
        }

        if ((string) $pedido['status'] !== 'rascunho') {
            return array('ok' => false, 'message' => 'Somente pedidos em rascunho podem ser movidos para aguardando pagamento.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa da alteração de status.');
        }

        $observacao = 'Pedido movido manualmente de rascunho para aguardando pagamento pelo administrador. Motivo: ' . $justificativa . '.';

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->pedidoModel->updateStatus($pedidoId, 'aguardando_pagamento');
            $this->pedidoModel->addStatusHistory($pedidoId, 'rascunho', 'aguardando_pagamento', $observacao, $actorUserId);

            $this->auditService->record(
                'pedido.ajustado_aguardando_pagamento',
                'pedido',
                $pedidoId,
                array(
                    'status_anterior' => 'rascunho',
                    'status_restaurado' => 'aguardando_pagamento',
                    'justificativa' => $justificativa,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.ajustado_aguardando_pagamento', array(
                'pedido_id' => $pedidoId,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            return array(
                'ok' => true,
                'status_restaurado' => 'aguardando_pagamento',
            );
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.ajustar_aguardando_pagamento_falhou', array(
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

        if (!$this->usuarioPodeGerenciarPedidos($actorUserId)) {
            $this->registrarAcessoNegado('pedido.excluir_sem_permissao', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para excluir este pedido.');
        }

        if (!$this->pedidoPodeSerAcessadoPor($pedido, $actorUserId)) {
            $this->registrarAcessoNegado('pedido.excluir_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para excluir este pedido.');
        }

        $notificacaoEmail = $this->montarNotificacaoExclusaoPedido($pedidoId, $pedido);
        $avaliacao = $this->avaliarExclusaoPedido($pedidoId, $pedido);
        if (empty($avaliacao['ok'])) {
            $motivo = !empty($avaliacao['motivos'])
                ? $this->formatarMotivoBloqueioExclusao($avaliacao['motivos'])
                : 'Este pedido nao pode ser excluido.';

            $this->auditService->record(
                'pedido.exclusao_bloqueada',
                'pedido',
                $pedidoId,
                array(
                    'motivos' => isset($avaliacao['motivos']) ? $avaliacao['motivos'] : array(),
                    'status' => $pedido['status'],
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.exclusao_bloqueada', array(
                'pedido_id' => $pedidoId,
                'motivos' => isset($avaliacao['motivos']) ? $avaliacao['motivos'] : array(),
                'usuario_id' => $actorUserId,
            ));

            return array('ok' => false, 'message' => $motivo);
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

            $this->enviarEmailExclusaoPedido($notificacaoEmail, $actorUserId, $ipAddress, $userAgent);

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

    public function excluirPedidosAntigosNaoConfirmados($dias = 30, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $dias = max(1, (int) $dias);
        $dataLimite = date('Y-m-d H:i:s', strtotime('-' . $dias . ' days'));
        $statusProtegidos = $this->pedidoStatusProtegidoParaExclusao();
        $placeholdersStatus = array();
        $params = array('data_limite' => $dataLimite);

        if (!$this->usuarioPodeGerenciarPedidos($actorUserId)) {
            return array(
                'ok' => false,
                'message' => 'Você nao tem permissao para excluir pedidos.',
            );
        }

        foreach ($statusProtegidos as $indice => $status) {
            $chave = 'status_protegido_' . $indice;
            $placeholdersStatus[] = ':' . $chave;
            $params[$chave] = $status;
        }

        $sql = 'SELECT p.*
                FROM pedidos p
                WHERE p.deleted_at IS NULL
                  AND p.created_at < :data_limite';

        if (!empty($placeholdersStatus)) {
            $sql .= ' AND p.status NOT IN (' . implode(', ', $placeholdersStatus) . ')';
        }

        $sql .= ' ORDER BY p.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $candidatos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($candidatos)) {
            return array(
                'ok' => true,
                'excluidos' => 0,
                'ignorados' => 0,
                'candidatos' => 0,
                'message' => 'Nenhum pedido antigo sem pagamento confirmado foi encontrado.',
            );
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        $excluidos = 0;
        $ignorados = 0;
        $bloqueios = array();
        $notificacoesEmail = array();

        try {
            foreach ($candidatos as $pedido) {
                $notificacaoEmail = $this->montarNotificacaoExclusaoPedido((int) $pedido['id'], $pedido);
                $avaliacao = $this->avaliarExclusaoPedido((int) $pedido['id'], $pedido);

                if (empty($avaliacao['ok'])) {
                    $ignorados++;
                    $bloqueios[] = array(
                        'pedido_id' => (int) $pedido['id'],
                        'codigo' => $pedido['codigo'],
                        'motivos' => isset($avaliacao['motivos']) ? $avaliacao['motivos'] : array(),
                    );
                    continue;
                }

                $justificativa = 'Exclusao em lote de pedido antigo sem pagamento confirmado.';
                $this->trashService->record('pedido', (int) $pedido['id'], $justificativa, $pedido, $actorUserId, $ipAddress, $userAgent);
                $this->pedidoModel->softDelete((int) $pedido['id']);

                $this->auditService->record(
                    'pedido.excluido_em_lote',
                    'pedido',
                    (int) $pedido['id'],
                    array(
                        'justificativa' => $justificativa,
                        'data_limite' => $dataLimite,
                    ),
                    $actorUserId,
                    $ipAddress,
                    $userAgent
                );

                Logger::info('pedido.excluido_em_lote', array(
                    'pedido_id' => (int) $pedido['id'],
                    'codigo' => $pedido['codigo'],
                    'usuario_id' => $actorUserId,
                ));

                $notificacoesEmail[] = $notificacaoEmail;
                $excluidos++;
            }

            $this->auditService->record(
                'pedido.exclusao_em_lote_processada',
                'pedido',
                null,
                array(
                    'data_limite' => $dataLimite,
                    'candidatos' => count($candidatos),
                    'excluidos' => $excluidos,
                    'ignorados' => $ignorados,
                    'bloqueios' => $bloqueios,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('pedido.exclusao_em_lote_processada', array(
                'candidatos' => count($candidatos),
                'excluidos' => $excluidos,
                'ignorados' => $ignorados,
                'usuario_id' => $actorUserId,
            ));

            $pdo->commit();

            foreach ($notificacoesEmail as $notificacaoEmail) {
                $this->enviarEmailExclusaoPedido($notificacaoEmail, $actorUserId, $ipAddress, $userAgent);
            }

            if ($excluidos === 0) {
                return array(
                    'ok' => true,
                    'excluidos' => 0,
                    'ignorados' => $ignorados,
                    'candidatos' => count($candidatos),
                    'message' => 'Nenhum pedido antigo sem pagamento confirmado foi encontrado.',
                );
            }

            return array(
                'ok' => true,
                'excluidos' => $excluidos,
                'ignorados' => $ignorados,
                'candidatos' => count($candidatos),
                'message' => 'Foram excluídos ' . $excluidos . ' pedidos antigos sem pagamento confirmado.',
            );
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('pedido.exclusao_em_lote_falhou', array(
                'message' => $exception->getMessage(),
                'usuario_id' => $actorUserId,
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
                $this->pedidoModel->markPaid($pedidoId, $actorUserId);
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

    public function aplicarCupomManualAoPedido($pedidoId, $cupomCodigo, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->usuarioPodeGerenciarPedidos($actorUserId)) {
            $this->registrarAcessoNegado('pedido.cupom.manual_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para aplicar cupom manualmente neste pedido.');
        }

        $status = isset($pedido['status']) ? (string) $pedido['status'] : '';
        if (in_array($status, array('cancelado', 'reembolsado', 'expirado'), true)) {
            return array('ok' => false, 'message' => 'Não é possível aplicar cupom neste pedido porque ele está cancelado, reembolsado ou expirado.');
        }

        return $this->cupomService->aplicarAoPedidoManual(
            $pedidoId,
            $cupomCodigo,
            $justificativa,
            $actorUserId,
            $ipAddress,
            $userAgent,
            array('permitir_confirmado' => true)
        );
    }

    public function removerCupomManualDoPedido($pedidoId, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $pedido = $this->pedidoModel->findById($pedidoId);
        if (!$pedido) {
            return array('ok' => false, 'message' => 'Pedido nao encontrado.');
        }

        if (!$this->usuarioPodeGerenciarPedidos($actorUserId)) {
            $this->registrarAcessoNegado('pedido.cupom.remocao_negado', $pedidoId, $actorUserId, $ipAddress, $userAgent);
            return array('ok' => false, 'message' => 'Você nao tem permissao para remover cupom deste pedido.');
        }

        if ($this->pedidoStatusBloqueadoParaCupom($pedido)) {
            return array('ok' => false, 'message' => 'Não é possível remover cupom em pedido já confirmado.');
        }

        return $this->cupomService->removerCupomManualDoPedido($pedidoId, $justificativa, $actorUserId, $ipAddress, $userAgent);
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
        $canManagePedidos = $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar');

        $pedido['itens'] = $this->pedidoItemModel->forPedido($pedidoId);
        $pedido['participantes'] = $this->participanteModel->forPedido($pedidoId);
        $pedido['inscricoes'] = $this->inscricaoModel->forPedido($pedidoId);
        $pedido['historico'] = $this->pedidoModel->historyForPedido($pedidoId);
        $pedido['comprovante_atual'] = $canSeePix ? $this->comprovanteModel->findByPedido($pedidoId) : null;
        $pedido['comprovantes'] = $canSeePix ? $this->comprovanteModel->versionsForPedido($pedidoId) : array();
        $pedido['exclusao'] = $this->avaliarExclusaoPedido($pedidoId, $pedido);
        $pedido['cupom_manual'] = $this->avaliarCupomManualPedido($pedido);
        $pedido['usuario_pagador'] = null;
        $pedido['pagamentos_gateway_transacoes'] = array();
        $pedido['pagamentos_gateway_logs'] = array();

        if (!empty($pedido['pagador_usuario_id'])) {
            $pedido['usuario_pagador'] = $this->usuarioModel->findById((int) $pedido['pagador_usuario_id']);
        }

        if (!empty($pedido['payment_gateway'])) {
            $pedido['pagamentos_gateway_transacoes'] = $this->gatewayTransacaoModel->listByPedido($pedidoId);
            if ($canSeePix) {
                $pedido['pagamentos_gateway_logs'] = $this->gatewayLogModel->listByPedido($pedidoId);
            }
        }

        return array(
            'pedido' => $pedido,
            'can_see_pix' => $canSeePix,
            'can_manage_pedidos' => $canManagePedidos,
        );
    }

    public function listarBackoffice($usuarioId, array $filters = array(), $page = 1, $perPage = 20)
    {
        $canSeePedidos = $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver');
        $canSeePix = $this->rbacService->userHasPermission($usuarioId, 'pedidos.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'financeiro.ver');
        $canManagePedidos = $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar');

        if (!$canSeePedidos) {
            return array(
                'pedidos' => array(),
                'pagination' => array(
                    'total' => 0,
                    'page' => 1,
                    'per_page' => (int) $perPage,
                    'pages' => 1,
                ),
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

        $total = $this->pedidoModel->countFilteredForBackoffice($filters);
        $offset = ($page - 1) * $perPage;
        $pedidos = $this->pedidoModel->listFilteredForBackoffice($filters, $perPage, $offset);

        foreach ($pedidos as &$pedido) {
            $pedido['cupom_manual'] = $this->avaliarCupomManualPedido($pedido);

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
            $pedido['exclusao'] = $this->avaliarExclusaoPedido((int) $pedido['id'], $pedido);
        }
        unset($pedido);

        return array(
            'pedidos' => $pedidos,
            'pagination' => array(
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1,
            ),
            'can_manage_pedidos' => $canManagePedidos,
        );
    }

    public function listarExcluidosBackoffice($usuarioId, array $filters = array())
    {
        $canManagePedidos = $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar');
        $filters = $this->normalizarFiltrosExclusao($filters);

        if (!$canManagePedidos) {
            return array(
                'pedidos_excluidos' => array(),
                'filters' => $filters,
                'can_manage_pedidos' => false,
            );
        }

        return array(
            'pedidos_excluidos' => $this->pedidoModel->allDeletedForBackoffice($filters),
            'filters' => $filters,
            'can_manage_pedidos' => true,
        );
    }

    public function avaliarExclusaoPedido($pedidoId, ?array $pedido = null)
    {
        if ($pedido === null) {
            $pedido = $this->pedidoModel->findById($pedidoId);
        }

        if (!$pedido) {
            return array(
                'ok' => false,
                'motivos' => array('pedido_nao_encontrado'),
            );
        }

        $motivos = array();
        $status = isset($pedido['status']) ? (string) $pedido['status'] : '';

        if (in_array($status, $this->pedidoStatusProtegidoParaExclusao(), true)) {
            $motivos[] = 'pagamento_confirmado';
        }

        if ($this->pedidoPossuiComprovanteAprovado($pedidoId)) {
            $motivos[] = 'comprovante_aprovado';
        }

        if ($this->pedidoPossuiInscricaoSensivel($pedidoId)) {
            $motivos[] = 'inscricao_sensivel';
        }

        if ($this->pedidoPossuiCertificado($pedidoId)) {
            $motivos[] = 'certificado_emitido';
        }

        return array(
            'ok' => empty($motivos),
            'motivos' => array_values(array_unique($motivos)),
            'motivos_texto' => empty($motivos) ? null : $this->formatarMotivoBloqueioExclusao($motivos),
            'pedido' => $pedido,
        );
    }

    public function avaliarReversaoCancelamentoPedido($pedidoId, ?array $pedido = null)
    {
        if ($pedido === null) {
            $pedido = $this->pedidoModel->findById($pedidoId);
        }

        if (!$pedido) {
            return array(
                'ok' => false,
                'motivos' => array('pedido_nao_encontrado'),
                'motivos_texto' => 'Pedido não encontrado.',
            );
        }

        $statusAtual = isset($pedido['status']) ? (string) $pedido['status'] : '';
        if ($statusAtual !== 'cancelado') {
            return array(
                'ok' => false,
                'motivos' => array('status_nao_cancelado'),
                'motivos_texto' => 'Somente pedidos cancelados podem ter a reversão administrada nesta tela.',
            );
        }

        $historicoCancelamento = $this->pedidoModel->latestStatusHistoryForStatus($pedidoId, 'cancelado');
        $statusAnterior = !empty($historicoCancelamento) && isset($historicoCancelamento['status_anterior'])
            ? trim((string) $historicoCancelamento['status_anterior'])
            : '';

        if ($statusAnterior === '' || $statusAnterior === 'cancelado') {
            return array(
                'ok' => false,
                'motivos' => array('status_anterior_indisponivel'),
                'motivos_texto' => 'Não foi possível identificar o status anterior para reverter este cancelamento.',
            );
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
            'reembolsado',
            'expirado',
        );

        if (!in_array($statusAnterior, $statusValidos, true)) {
            return array(
                'ok' => false,
                'motivos' => array('status_anterior_invalido'),
                'motivos_texto' => 'O status anterior registrado para este cancelamento é inválido para reversão.',
            );
        }

        return array(
            'ok' => true,
            'motivos' => array(),
            'motivos_texto' => '',
            'status_anterior' => $statusAnterior,
            'historico_cancelamento' => $historicoCancelamento,
        );
    }

    private function pedidoPossuiComprovanteAprovado($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM comprovantes_pix
             WHERE pedido_id = :pedido_id
               AND deleted_at IS NULL
               AND status = "aprovado"'
        );
        $stmt->execute(array('pedido_id' => (int) $pedidoId));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return !empty($row) && (int) $row['total'] > 0;
    }

    private function pedidoPossuiInscricaoSensivel($pedidoId)
    {
        $statusSensiveis = array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido');
        $placeholders = array();
        $params = array('pedido_id' => (int) $pedidoId);

        foreach ($statusSensiveis as $indice => $status) {
            $chave = 'status_' . $indice;
            $placeholders[] = ':' . $chave;
            $params[$chave] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM inscricoes
             WHERE pedido_id = :pedido_id
               AND deleted_at IS NULL
               AND status IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return !empty($row) && (int) $row['total'] > 0;
    }

    private function pedidoPossuiCertificado($pedidoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM certificados
             WHERE pedido_id = :pedido_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('pedido_id' => (int) $pedidoId));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return !empty($row) && (int) $row['total'] > 0;
    }

    private function pedidoStatusProtegidoParaExclusao()
    {
        return array(
            'aprovado',
            'pago',
            'reembolsado',
        );
    }

    private function pedidoStatusBloqueadoParaCupom(array $pedido, $permitirConfirmado = false)
    {
        $status = isset($pedido['status']) ? (string) $pedido['status'] : '';

        if (in_array($status, array(
            'cancelado',
            'reembolsado',
            'expirado',
        ), true)) {
            return true;
        }

        if (!$permitirConfirmado && in_array($status, array(
            'pago',
            'aprovado',
            'confirmado',
            'concluido',
            'concluida',
        ), true)) {
            return true;
        }

        return false;
    }

    private function avaliarCupomManualPedido(array $pedido)
    {
        if (empty($pedido)) {
            return array(
                'ok' => false,
                'motivos' => array('Pedido nao encontrado.'),
                'motivos_texto' => 'Pedido nao encontrado.',
            );
        }

        $status = isset($pedido['status']) ? (string) $pedido['status'] : '';
        $statusConfirmado = in_array($status, array('aprovado', 'pago'), true);
        $statusPermitido = in_array($status, array(
            'rascunho',
            'pendencia',
            'aguardando_pagamento',
            'aguardando_reenvio',
            'comprovante_enviado',
            'em_analise',
            'aprovado',
            'pago',
        ), true);

        if ($statusPermitido && !in_array($status, array('cancelado', 'reembolsado', 'expirado'), true)) {
            $resposta = array(
                'ok' => true,
                'motivos' => array(),
                'motivos_texto' => '',
                'pedido_confirmado' => $statusConfirmado,
                'pode_remover' => !$statusConfirmado,
                'pode_aplicar' => true,
            );

            if ($statusConfirmado) {
                $resposta['alerta_texto'] = 'Este pedido já está confirmado. A aplicação do cupom será registrada como ajuste financeiro pós-aprovação e não mudará o status do pedido.';
            }

            return $resposta;
        }

        if (in_array($status, array('cancelado', 'reembolsado', 'expirado'), true)) {
            return array(
                'ok' => false,
                'motivos' => array('pedido_bloqueado'),
                'motivos_texto' => 'Não é possível aplicar cupom neste pedido porque ele está cancelado, reembolsado ou expirado.',
                'pedido_confirmado' => false,
                'pode_remover' => false,
                'pode_aplicar' => false,
            );
        }

        return array(
            'ok' => false,
            'motivos' => array('status_nao_permitido'),
            'motivos_texto' => 'Não é possível aplicar cupom neste pedido neste status.',
            'pedido_confirmado' => false,
            'pode_remover' => false,
            'pode_aplicar' => false,
        );
    }

    private function usuarioPodeGerenciarPedidos($usuarioId)
    {
        return $this->rbacService->userHasPermission($usuarioId, 'pedidos.gerenciar');
    }

    private function formatarMotivoBloqueioExclusao(array $motivos)
    {
        $mapa = array(
            'pagamento_confirmado' => 'Este pedido não pode ser excluído porque possui pagamento confirmado.',
            'comprovante_aprovado' => 'Este pedido não pode ser excluído porque possui comprovante aprovado.',
            'inscricao_sensivel' => 'Este pedido não pode ser excluído porque possui inscrições ativas ou concluídas.',
            'certificado_emitido' => 'Este pedido não pode ser excluído porque possui certificado emitido.',
            'pedido_nao_encontrado' => 'Pedido não encontrado.',
        );

        $mensagens = array();
        foreach ($motivos as $motivo) {
            if (isset($mapa[$motivo]) && !in_array($mapa[$motivo], $mensagens, true)) {
                $mensagens[] = $mapa[$motivo];
            }
        }

        if (empty($mensagens)) {
            return 'Este pedido não pode ser excluído.';
        }

        return implode(' ', $mensagens);
    }

    private function montarNotificacaoExclusaoPedido($pedidoId, array $pedido)
    {
        $itens = $this->pedidoItemModel->forPedido($pedidoId);
        $cursos = array();

        foreach ($itens as $item) {
            if (!empty($item['curso_nome'])) {
                $cursos[] = $item['curso_nome'];
            }
        }

        $cursos = array_values(array_unique($cursos));
        $cursoNome = !empty($cursos) ? implode(', ', $cursos) : 'Curso não informado';

        $valorTotal = isset($pedido['total']) && is_numeric($pedido['total']) ? (float) $pedido['total'] : 0.0;
        $valorPago = $this->calcularValorPagoPedido($pedido, $valorTotal);
        $valorNaoPago = max(0.0, $valorTotal - $valorPago);

        return array(
            'pedido' => $pedido,
            'cursos' => $cursos,
            'pedido_codigo' => isset($pedido['codigo']) ? (string) $pedido['codigo'] : '',
            'curso_nome' => $cursoNome,
            'valor_total' => $this->formatarMoedaBR($valorTotal),
            'valor_pago' => $this->formatarMoedaBR($valorPago),
            'valor_nao_pago' => $this->formatarMoedaBR($valorNaoPago),
            'aluno_nome' => isset($pedido['pagador_nome']) ? (string) $pedido['pagador_nome'] : '',
            'aluno_email' => isset($pedido['pagador_email']) ? (string) $pedido['pagador_email'] : '',
        );
    }

    private function enviarEmailExclusaoPedido(array $notificacaoEmail, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (empty($notificacaoEmail['pedido']) || empty($notificacaoEmail['pedido']['pagador_email'])) {
            return;
        }

        try {
            $this->emailService->pedidoExcluidoInatividade(
                $notificacaoEmail['pedido'],
                isset($notificacaoEmail['cursos']) && is_array($notificacaoEmail['cursos']) ? $notificacaoEmail['cursos'] : array(),
                $notificacaoEmail,
                $actorUserId,
                $ipAddress,
                $userAgent
            );
        } catch (Exception $exception) {
            Logger::error('pedido.email_exclusao_falhou', array(
                'pedido_id' => isset($notificacaoEmail['pedido']['id']) ? (int) $notificacaoEmail['pedido']['id'] : null,
                'message' => $exception->getMessage(),
            ));
        }
    }

    private function calcularValorPagoPedido(array $pedido, $valorTotal = 0.0)
    {
        $valorTotal = is_numeric($valorTotal) ? (float) $valorTotal : 0.0;
        $valorPago = 0.0;

        if (isset($pedido['payment_provider_paid_amount']) && is_numeric($pedido['payment_provider_paid_amount'])) {
            $valorPago = (float) $pedido['payment_provider_paid_amount'];
        }

        if ($valorPago <= 0.0 && !empty($pedido['payment_provider_status'])) {
            $statusGateway = strtolower(trim((string) $pedido['payment_provider_status']));
            if (in_array($statusGateway, array('paid', 'pago', 'aprovado', 'approved', 'confirmed', 'success', 'succeeded', 'completed'), true)) {
                $valorPago = isset($pedido['payment_provider_amount']) && is_numeric($pedido['payment_provider_amount'])
                    ? (float) $pedido['payment_provider_amount']
                    : $valorTotal;
            }
        }

        if ($valorPago < 0.0) {
            $valorPago = 0.0;
        }

        if ($valorPago > $valorTotal) {
            $valorPago = $valorTotal;
        }

        return $valorPago;
    }

    private function formatarMoedaBR($valor)
    {
        $valor = is_numeric($valor) ? (float) $valor : 0.0;

        return 'R$ ' . number_format(max(0.0, $valor), 2, ',', '.');
    }

    private function normalizarFiltrosExclusao(array $filters)
    {
        return array(
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'status' => isset($filters['status']) ? trim((string) $filters['status']) : '',
            'curso' => isset($filters['curso']) ? trim((string) $filters['curso']) : '',
            'de' => $this->normalizarDataFiltro(isset($filters['de']) ? $filters['de'] : ''),
            'ate' => $this->normalizarDataFiltro(isset($filters['ate']) ? $filters['ate'] : ''),
        );
    }

    private function normalizarDataFiltro($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        $data = \DateTime::createFromFormat('Y-m-d', $valor);
        if ($data && $data->format('Y-m-d') === $valor) {
            return $valor;
        }

        return '';
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

    private function pedidoTemComprovanteAguardandoAprovacao(array $pedido)
    {
        $comprovanteAtual = isset($pedido['comprovante_atual']) && is_array($pedido['comprovante_atual'])
            ? $pedido['comprovante_atual']
            : null;

        if (empty($comprovanteAtual)) {
            return false;
        }

        $pedidoStatus = isset($pedido['status']) ? (string) $pedido['status'] : '';
        if (in_array($pedidoStatus, array('aprovado', 'pago'), true)) {
            return false;
        }

        $comprovanteStatus = isset($comprovanteAtual['status']) ? (string) $comprovanteAtual['status'] : '';

        return $comprovanteStatus !== '' && !in_array($comprovanteStatus, array('aprovado', 'reprovado'), true);
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




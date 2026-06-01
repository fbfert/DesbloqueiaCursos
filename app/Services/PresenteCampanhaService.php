<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\CursoEvento;
use App\Models\EmailModelo;
use App\Models\Inscricao;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\ParticipantePedido;
use App\Models\PresenteBeneficiario;
use App\Models\PresenteCampanha;
use App\Models\Turma;
use App\Models\Usuario;
use App\Models\Perfil;
use Exception;

class PresenteCampanhaService
{
    private $campanhaModel;
    private $beneficiarioModel;
    private $usuarioModel;
    private $cursoModel;
    private $turmaModel;
    private $perfilModel;
    private $pedidoModel;
    private $pedidoItemModel;
    private $participanteModel;
    private $inscricaoModel;
    private $emailService;
    private $emailModeloModel;
    private $auditService;
    private $rbacService;

    public function __construct()
    {
        $this->campanhaModel = new PresenteCampanha();
        $this->beneficiarioModel = new PresenteBeneficiario();
        $this->usuarioModel = new Usuario();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->perfilModel = new Perfil();
        $this->pedidoModel = new Pedido();
        $this->pedidoItemModel = new PedidoItem();
        $this->participanteModel = new ParticipantePedido();
        $this->inscricaoModel = new Inscricao();
        $this->emailService = new EmailService();
        $this->emailModeloModel = new EmailModelo();
        $this->auditService = new AuditService();
        $this->rbacService = new RbacService();
    }

    public function listarBackoffice($usuarioId, array $filters = array())
    {
        if (!$this->usuarioPodeVer($usuarioId)) {
            return array(
                'campanhas' => array(),
                'filters' => $this->normalizarFiltrosIndex($filters),
                'can_manage' => false,
                'can_cancel' => false,
            );
        }

        return array(
            'campanhas' => $this->campanhaModel->allForBackoffice($this->normalizarFiltrosIndex($filters)),
            'filters' => $this->normalizarFiltrosIndex($filters),
            'options' => array(
                'cursos' => $this->cursoModel->allForSelect(),
                'turmas' => $this->turmaModel->allForSelect(),
                'usuarios' => $this->usuarioModel->allForAdmin(array()),
            ),
            'can_manage' => $this->usuarioPodeGerenciar($usuarioId),
            'can_cancel' => $this->usuarioPodeCancelar($usuarioId),
        );
    }

    public function formData(array $filters = array(), $page = 1, $perPage = 20)
    {
        $page = max(1, (int) $page);
        $perPage = max(5, min(50, (int) $perPage));
        $filters = $this->normalizarFiltrosUsuarios($filters);
        $offset = ($page - 1) * $perPage;

        $emailModelo = $this->emailModeloModel->findByEvento('email.presente_concedido');
        if (!$emailModelo) {
            $emailModelo = array(
                'evento' => 'email.presente_concedido',
                'template' => 'presente_concedido',
                'nome' => 'Curso recebido como presente',
                'assunto' => 'Você ganhou acesso a um curso na Desbloqueia Cursos',
                'corpo_html' => '',
            );
        }

        return array(
            'filters' => $filters,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $this->usuarioModel->contarParaPresente($filters),
            'usuarios' => $this->usuarioModel->listarParaPresente($filters, $perPage, $offset),
            'cursos' => $this->cursoModel->allForSelect(),
            'turmas' => $this->turmaModel->allForSelect(),
            'perfis' => $this->perfilModel->all(),
            'email_modelo' => $emailModelo,
            'modelos_email' => $this->listarModelosEmailPresentes(),
        );
    }

    public function carregarUsuariosSelecionados(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return array();
        }

        $usuarios = array();
        foreach ($ids as $usuarioId) {
            $usuario = $this->usuarioModel->findById($usuarioId);
            if (!$usuario || (string) $usuario['status'] !== 'ativo') {
                continue;
            }

            $usuarios[] = $usuario;
        }

        return $usuarios;
    }

    public function configData(array $selectedIds = array(), $cursoId = 0, $turmaId = 0, $contextoConfirmado = false, array $old = array())
    {
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds))));
        $cursoId = (int) $cursoId;
        $turmaId = (int) $turmaId;
        $contextoConfirmado = !empty($contextoConfirmado);
        $cursoSelecionado = $cursoId > 0 ? $this->cursoModel->findPublicById($cursoId) : null;
        $turmas = array();
        if ($cursoSelecionado) {
            $turmas = $this->turmaModel->forPublicCourse($cursoId, false);
        }
        $turmaSelecionada = null;
        foreach ($turmas as $turma) {
            if ((int) $turma['id'] === $turmaId) {
                $turmaSelecionada = $turma;
                break;
            }
        }

        $emailModelo = $this->emailModeloModel->findByEvento('email.presente_concedido');
        if (!$emailModelo) {
            $emailModelo = array(
                'evento' => 'email.presente_concedido',
                'template' => 'presente_concedido',
                'nome' => 'Curso recebido como presente',
                'assunto' => 'Você ganhou acesso a um curso na Desbloqueia Cursos',
                'corpo_html' => '',
            );
        }

        return array(
            'selecionados' => $this->carregarUsuariosSelecionados($selectedIds),
            'selecionados_ids' => $selectedIds,
            'curso_selecionado' => $cursoSelecionado,
            'turma_selecionada' => $turmaSelecionada,
            'cursos' => $this->cursoModel->allForSelect(array('ativo')),
            'turmas' => $turmas,
            'email_modelo' => $emailModelo,
            'modelos_email' => $this->listarModelosEmailPresentes(),
            'old' => $old,
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId,
            'contexto_confirmado' => $contextoConfirmado && $cursoSelecionado && $turmaSelecionada,
        );
    }

    public function prepararPreview(array $input, $actorUserId = null)
    {
        $dados = $this->normalizarFormulario($input);
        $erros = $this->validarFormularioBase($dados);
        if (!empty($erros)) {
            return array('ok' => false, 'errors' => $erros);
        }

        $usuariosSelecionados = $this->resolverUsuariosSelecionados($dados);
        if (empty($usuariosSelecionados)) {
            return array('ok' => false, 'errors' => array('Selecione ao menos um usuário ativo.'));
        }

        $preview = $this->montarPreviewBeneficiarios($usuariosSelecionados, $dados);
        $dados['selecionados'] = array_map(function (array $usuario) {
            return (int) $usuario['id'];
        }, $preview['beneficiarios_aptos']);
        $dados['ignorados'] = $preview['ignorados'];
        $dados['totais'] = $preview['totais'];

        $token = $this->gerarTokenPreview();
        return array(
            'ok' => true,
            'token' => $token,
            'payload' => $dados,
            'preview' => $preview,
        );
    }

    public function executar(array $payload, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $dados = $this->normalizarFormulario($payload);
        $erros = $this->validarFormularioBase($dados);
        if (!empty($erros)) {
            return array('ok' => false, 'errors' => $erros);
        }

        $usuariosSelecionados = $this->resolverUsuariosSelecionados($dados);
        if (empty($usuariosSelecionados)) {
            return array('ok' => false, 'errors' => array('Nenhum usuário válido encontrado para conceder o presente.'));
        }

        $preview = $this->montarPreviewBeneficiarios($usuariosSelecionados, $dados);
        if (empty($preview['beneficiarios_aptos'])) {
            return array('ok' => false, 'errors' => array('Nenhum usuário elegível para receber o presente.'));
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $campanhaId = $this->campanhaModel->create(array(
                'titulo' => $dados['titulo'],
                'justificativa' => $dados['justificativa'],
                'curso_evento_id' => $dados['curso_evento_id'],
                'turma_id' => $dados['turma_id'],
                'acesso_tipo' => $dados['acesso_tipo'],
                'acesso_dias' => $dados['acesso_dias'],
                'acesso_expira_em' => $dados['acesso_expira_em'],
                'email_modelo_evento' => $dados['email_modelo_evento'],
                'email_assunto' => $dados['email_assunto'],
                'email_corpo' => $dados['email_corpo'],
                'status' => 'ativo',
                'criado_por_usuario_id' => $actorUserId,
                'atualizado_por_usuario_id' => $actorUserId,
            ));

            $totais = array(
                'total_selecionado' => count($usuariosSelecionados),
                'total_presenteado' => 0,
                'total_ignorado' => 0,
                'total_erro' => 0,
            );
            $beneficiariosCriados = array();
            $beneficiariosIgnorados = array();

            foreach ($preview['beneficiarios_aptos'] as $usuario) {
                try {
                    $resultado = $this->concederParaUsuario($campanhaId, $dados, $usuario, $actorUserId, $ipAddress, $userAgent);
                    if (!empty($resultado['ok'])) {
                        $beneficiariosCriados[] = $resultado['beneficiario'];
                        $totais['total_presenteado']++;
                    } else {
                        $beneficiariosIgnorados[] = array_merge($usuario, array('motivo' => $resultado['message'] ?? 'Não foi possível conceder.'));
                        $totais['total_ignorado']++;
                    }
                } catch (Exception $exception) {
                    $totais['total_erro']++;
                    $beneficiariosIgnorados[] = array_merge($usuario, array('motivo' => $exception->getMessage()));
                    Logger::error('presentes.campanha.usuário_falhou', array(
                        'message' => $exception->getMessage(),
                        'usuario_id' => isset($usuario['id']) ? (int) $usuario['id'] : null,
                        'campanha_id' => $campanhaId,
                    ));
                }
            }

            $this->recalcularStatusCampanha($campanhaId, $actorUserId);

            $this->auditService->record(
                'presentes.campanha.criada',
                'presente_campanha',
                $campanhaId,
                array(
                    'dados' => $dados,
                    'totais' => $totais,
                    'beneficiarios' => $beneficiariosCriados,
                    'ignorados' => $beneficiariosIgnorados,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('presentes.campanha.criada', array(
                'campanha_id' => $campanhaId,
                'total_presenteado' => $totais['total_presenteado'],
                'total_ignorado' => $totais['total_ignorado'],
                'total_erro' => $totais['total_erro'],
            ));

            $pdo->commit();

            return array(
                'ok' => true,
                'campanha_id' => $campanhaId,
                'totais' => $totais,
                'beneficiarios' => $beneficiariosCriados,
                'ignorados' => $beneficiariosIgnorados,
            );
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('presentes.campanha.criar_falhou', array('message' => $exception->getMessage()));

            throw $exception;
        }
    }

    public function detalharCampanha($campanhaId, $usuarioId)
    {
        if (!$this->usuarioPodeVer($usuarioId)) {
            return array('campanha' => null);
        }

        $campanha = $this->campanhaModel->findById($campanhaId);
        if (!$campanha) {
            return array('campanha' => null);
        }

        $beneficiarios = $this->campanhaModel->beneficiariosForCampanha($campanhaId);

        return array(
            'campanha' => $campanha,
            'beneficiarios' => $beneficiarios,
            'can_manage' => $this->usuarioPodeGerenciar($usuarioId),
            'can_cancel' => $this->usuarioPodeCancelar($usuarioId),
        );
    }

    public function cancelarBeneficiarios($campanhaId, array $beneficiarioIds, $cancelamentoTipo, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        if (!$this->usuarioPodeCancelar($actorUserId)) {
            return array('ok' => false, 'message' => 'Acesso negado.');
        }

        $campanha = $this->campanhaModel->findById($campanhaId);
        if (!$campanha) {
            return array('ok' => false, 'message' => 'Campanha não encontrada.');
        }

        $beneficiarioIds = array_values(array_unique(array_filter(array_map('intval', $beneficiarioIds))));
        if (empty($beneficiarioIds)) {
            return array('ok' => false, 'message' => 'Selecione ao menos um beneficiário.');
        }

        $justificativa = trim((string) $justificativa);
        if ($justificativa === '') {
            return array('ok' => false, 'message' => 'Informe a justificativa do cancelamento.');
        }

        $cancelamentoTipo = $this->normalizarTipoCancelamento($cancelamentoTipo);
        if ($cancelamentoTipo === '') {
            return array('ok' => false, 'message' => 'Tipo de cancelamento inválido.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $cancelados = 0;
            $ignorados = 0;

            foreach ($beneficiarioIds as $beneficiarioId) {
                $beneficiario = $this->beneficiarioModel->findById($beneficiarioId);
                if (!$beneficiario || (int) $beneficiario['campanha_id'] !== (int) $campanhaId) {
                    $ignorados++;
                    continue;
                }

                if ((string) $beneficiario['status'] !== 'ativo') {
                    $ignorados++;
                    continue;
                }

                $this->aplicarCancelamentoBeneficiario($beneficiario, $cancelamentoTipo, $justificativa, $actorUserId, $ipAddress, $userAgent);
                $cancelados++;
            }

            $this->recalcularStatusCampanha($campanhaId, $actorUserId);

            $this->auditService->record(
                'presentes.beneficiarios.cancelados',
                'presente_campanha',
                $campanhaId,
                array(
                    'beneficiario_ids' => $beneficiarioIds,
                    'cancelamento_tipo' => $cancelamentoTipo,
                    'justificativa' => $justificativa,
                    'cancelados' => $cancelados,
                    'ignorados' => $ignorados,
                ),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('presentes.beneficiarios.cancelados', array(
                'campanha_id' => $campanhaId,
                'cancelados' => $cancelados,
                'ignorados' => $ignorados,
            ));

            $pdo->commit();

            return array(
                'ok' => true,
                'cancelados' => $cancelados,
                'ignorados' => $ignorados,
            );
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('presentes.beneficiarios.cancelar_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    private function concederParaUsuario($campanhaId, array $dados, array $usuario, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $usuarioId = (int) $usuario['id'];
        $conflito = $this->inscricaoModel->findAcessoAtivoPorUsuarioCurso($usuarioId, (int) $dados['curso_evento_id']);
        if ($conflito) {
            return array('ok' => false, 'message' => 'Usuário já possui acesso ativo para este curso.');
        }

        $turmaId = !empty($dados['turma_id']) ? (int) $dados['turma_id'] : null;
        if ($turmaId && $this->inscricaoModel->findAcessoAtivoPorUsuarioTurma($usuarioId, $turmaId)) {
            return array('ok' => false, 'message' => 'Usuário já está matriculado neste curso.');
        }

        $pedidoData = array(
            'codigo' => $this->gerarCodigoPresente(),
            'comprador_usuario_id' => $usuarioId,
            'pagador_usuario_id' => $usuarioId,
            'pagador_nome' => $usuario['nome'],
            'pagador_cpf' => isset($usuario['cpf']) ? $usuario['cpf'] : null,
            'pagador_email' => $usuario['email'],
            'pagador_telefone' => isset($usuario['telefone']) ? $usuario['telefone'] : null,
            'pagador_cidade' => isset($usuario['cidade']) ? $usuario['cidade'] : null,
            'pagador_estado' => isset($usuario['estado']) ? $usuario['estado'] : null,
            'tipo_pedido' => 'terceiros',
            'status' => 'aprovado',
            'subtotal' => 0.00,
            'desconto_total' => 0.00,
            'acrescimo_total' => 0.00,
            'total' => 0.00,
            'observacoes_internas' => $dados['justificativa'],
            'observacoes_publicas' => 'Presente promocional.',
            'canal_origem' => 'presente',
            'is_presente' => 1,
            'presente_campanha_id' => $campanhaId,
            'presente_titulo' => $dados['titulo'],
            'presente_justificativa' => $dados['justificativa'],
            'presente_concedido_em' => date('Y-m-d H:i:s'),
            'aprovado_por_usuario_id' => $actorUserId,
            'aprovado_em' => date('Y-m-d H:i:s'),
        );

        $pedidoId = $this->pedidoModel->create($pedidoData);
        $pedidoItemId = $this->pedidoItemModel->create(array(
            'pedido_id' => $pedidoId,
            'curso_evento_id' => (int) $dados['curso_evento_id'],
            'turma_id' => $turmaId,
            'quantidade' => 1,
            'valor_unitario' => 0.00,
            'valor_total' => 0.00,
            'status' => 'ativo',
        ));

        $participanteId = $this->participanteModel->create(array(
            'pedido_id' => $pedidoId,
            'pedido_item_id' => $pedidoItemId,
            'usuario_id' => $usuarioId,
            'nome' => $usuario['nome'],
            'cpf' => isset($usuario['cpf']) ? $usuario['cpf'] : null,
            'email' => $usuario['email'],
            'telefone' => isset($usuario['telefone']) ? $usuario['telefone'] : null,
            'ordem' => 1,
            'status' => 'ativo',
        ));

        $inscricaoId = $this->inscricaoModel->create(array(
            'pedido_id' => $pedidoId,
            'pedido_item_id' => $pedidoItemId,
            'participante_pedido_id' => $participanteId,
            'usuario_id' => $usuarioId,
            'curso_evento_id' => (int) $dados['curso_evento_id'],
            'turma_id' => $turmaId,
            'status' => 'ativa',
            'confirmado_em' => date('Y-m-d H:i:s'),
            'is_presente' => 1,
            'presente_campanha_id' => $campanhaId,
            'acesso_expira_em' => $dados['acesso_expira_em'],
        ));

        $beneficiarioId = $this->campanhaModel->createBeneficiario(array(
            'campanha_id' => $campanhaId,
            'usuario_id' => $usuarioId,
            'pedido_id' => $pedidoId,
            'inscricao_id' => $inscricaoId,
            'acesso_expira_em' => $dados['acesso_expira_em'],
            'status' => 'ativo',
        ));

        $pedido = $this->pedidoModel->findById($pedidoId);
        $inscricao = $this->inscricaoModel->findById($inscricaoId);
        $this->pedidoModel->addStatusHistory($pedidoId, null, 'aprovado', 'Presente promocional concedido.', $actorUserId);
        $this->inscricaoModel->addStatusHistory($inscricaoId, null, 'ativa', 'Presente promocional concedido.', $actorUserId);

        $this->auditService->record(
            'presentes.beneficiario.concedido',
            'presente_beneficiario',
            $beneficiarioId,
            array(
                'campanha_id' => $campanhaId,
                'usuario_id' => $usuarioId,
                'pedido_id' => $pedidoId,
                'inscricao_id' => $inscricaoId,
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        $this->emailService->sendCustomHtml(
            'email.presente_concedido',
            'presente_concedido',
            $usuario['email'],
            $usuario['nome'],
            $dados['email_assunto'],
            $dados['email_corpo'],
            array(
                'nome_usuario' => $usuario['nome'],
                'nome_curso' => $dados['curso_nome'],
                'nome_turma' => $dados['turma_nome'] !== '' ? $dados['turma_nome'] : 'Sem turma específica',
                'prazo_acesso' => $this->formatarPrazoAcesso($dados['acesso_tipo'], $dados['acesso_expira_em'], $dados['acesso_dias']),
                'link_meus_cursos' => '/meus-cursos',
                'nome_plataforma' => 'Desbloqueia Cursos',
            ),
            'presente',
            $beneficiarioId,
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        return array(
            'ok' => true,
            'beneficiario' => array(
                'beneficiario_id' => $beneficiarioId,
                'pedido_id' => $pedidoId,
                'inscricao_id' => $inscricaoId,
                'usuario_id' => $usuarioId,
            ),
            'pedido' => $pedido,
            'inscricao' => $inscricao,
        );
    }

    private function montarPreviewBeneficiarios(array $usuariosSelecionados, array $dados)
    {
        $beneficiariosAptos = array();
        $ignorados = array();

        foreach ($usuariosSelecionados as $usuario) {
            if ($this->usuarioJaPossuiAcessoCurso($usuario['id'], $dados['curso_evento_id'])) {
                $ignorados[] = array_merge($usuario, array('motivo' => 'Já possui acesso válido a este curso.'));
                continue;
            }

            $beneficiariosAptos[] = $usuario;
        }

        return array(
            'beneficiarios_aptos' => $beneficiariosAptos,
            'ignorados' => $ignorados,
            'totais' => array(
                'selecionados' => count($usuariosSelecionados),
                'aptos' => count($beneficiariosAptos),
                'ignorados' => count($ignorados),
            ),
        );
    }

    private function usuarioJaPossuiAcessoCurso($usuarioId, $cursoId)
    {
        return (bool) $this->inscricaoModel->findAcessoAtivoPorUsuarioCurso((int) $usuarioId, (int) $cursoId);
    }

    private function resolverUsuariosSelecionados(array $dados)
    {
        if (!empty($dados['selecionar_todos_filtrados'])) {
            $ids = $this->usuarioModel->idsParaPresente($dados['filtros_usuarios']);
        } else {
            $ids = $dados['usuarios_selecionados'];
        }

        if (empty($ids)) {
            return array();
        }

        $usuarios = array();
        foreach (array_unique(array_map('intval', $ids)) as $usuarioId) {
            $usuario = $this->usuarioModel->findById($usuarioId);
            if (!$usuario || (string) $usuario['status'] !== 'ativo') {
                continue;
            }

            if (!$this->usuarioModeloPassaFiltroBasico($usuario, $dados['filtros_usuarios'])) {
                continue;
            }

            $usuarios[] = $usuario;
        }

        return $usuarios;
    }

    private function usuarioModeloPassaFiltroBasico(array $usuario, array $filtros)
    {
        $nome = isset($filtros['nome']) ? trim((string) $filtros['nome']) : '';
        if ($nome !== '') {
            $texto = strtolower((string) $usuario['nome'] . ' ' . (string) $usuario['email'] . ' ' . (string) $usuario['cpf']);
            if (strpos($texto, strtolower($nome)) === false) {
                return false;
            }
        }

        $cidade = isset($filtros['cidade']) ? trim((string) $filtros['cidade']) : '';
        if ($cidade !== '' && isset($usuario['cidade']) && stripos((string) $usuario['cidade'], $cidade) === false) {
            return false;
        }

        return true;
    }

    private function normalizarFormulario(array $input)
    {
        $filtros = $this->normalizarFiltrosUsuarios(isset($input['filtros_usuarios']) && is_array($input['filtros_usuarios']) ? $input['filtros_usuarios'] : array());
        $selecionados = isset($input['usuarios_selecionados']) && is_array($input['usuarios_selecionados']) ? array_map('intval', $input['usuarios_selecionados']) : array();

        $cursoId = isset($input['curso_evento_id']) ? (int) $input['curso_evento_id'] : 0;
        $curso = $cursoId > 0 ? $this->cursoModel->findPublicById($cursoId) : null;
        $turmaId = !empty($input['turma_id']) ? (int) $input['turma_id'] : null;
        $turma = $turmaId ? $this->turmaModel->findPublicById($turmaId) : null;
        if ($turma && (int) $turma['curso_evento_id'] !== $cursoId) {
            $turma = null;
        }

        $emailModeloEvento = trim((string) ($input['email_modelo_evento'] ?? 'email.presente_concedido'));
        $emailAssunto = trim((string) ($input['email_assunto'] ?? 'Você ganhou acesso a um curso na Desbloqueia Cursos'));
        $emailCorpo = trim((string) ($input['email_corpo'] ?? ''));
        if ($emailCorpo === '') {
            $modelo = $this->emailModeloModel->findByEvento($emailModeloEvento);
            if ($modelo && !empty($modelo['corpo_html'])) {
                $emailCorpo = $modelo['corpo_html'];
            }
        }

        return array(
            'titulo' => trim((string) ($input['titulo'] ?? '')),
            'justificativa' => trim((string) ($input['justificativa'] ?? '')),
            'curso_evento_id' => $cursoId,
            'curso_nome' => $curso ? (string) $curso['nome'] : '',
            'turma_id' => $turmaId,
            'turma_nome' => $turma ? (string) $turma['nome'] : '',
            'acesso_tipo' => $this->normalizarAcessoTipo(isset($input['acesso_tipo']) ? $input['acesso_tipo'] : 'sem_prazo'),
            'acesso_dias' => !empty($input['acesso_dias']) ? (int) $input['acesso_dias'] : null,
            'acesso_expira_em' => !empty($input['acesso_expira_em']) ? $this->normalizarDataHora((string) $input['acesso_expira_em']) : null,
            'email_modelo_evento' => $emailModeloEvento !== '' ? $emailModeloEvento : 'email.presente_concedido',
            'email_assunto' => $emailAssunto,
            'email_corpo' => $emailCorpo,
            'usuarios_selecionados' => $selecionados,
            'selecionar_todos_filtrados' => !empty($input['selecionar_todos_filtrados']) ? 1 : 0,
            'filtros_usuarios' => $filtros,
        );
    }

    private function validarFormularioBase(array $dados)
    {
        $erros = array();

        if ($dados['titulo'] === '') {
            $erros[] = 'Informe o título da campanha.';
        }

        if ($dados['justificativa'] === '') {
            $erros[] = 'Informe a justificativa da campanha.';
        }

        if ($dados['curso_evento_id'] <= 0) {
            $erros[] = 'Selecione um curso válido.';
        }

        if (empty($dados['curso_nome'])) {
            $erros[] = 'Curso não encontrado.';
        }

        if ($dados['acesso_tipo'] === 'dias' && (int) $dados['acesso_dias'] <= 0) {
            $erros[] = 'Informe a quantidade de dias de acesso.';
        }

        if ($dados['acesso_tipo'] === 'data' && empty($dados['acesso_expira_em'])) {
            $erros[] = 'Informe a data de expiração do acesso.';
        }

        if ($dados['email_assunto'] === '') {
            $erros[] = 'Informe o assunto do e-mail.';
        }

        if ($dados['email_corpo'] === '') {
            $erros[] = 'Informe o corpo do e-mail.';
        }

        return $erros;
    }

    private function normalizarAcessoTipo($valor)
    {
        $valor = trim((string) $valor);
        if (!in_array($valor, array('sem_prazo', 'dias', 'data'), true)) {
            return 'sem_prazo';
        }

        return $valor;
    }

    private function normalizarDataHora($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $data = \DateTime::createFromFormat('Y-m-d\TH:i', $valor)
            ?: \DateTime::createFromFormat('Y-m-d H:i:s', $valor)
            ?: \DateTime::createFromFormat('Y-m-d H:i', $valor);

        return $data ? $data->format('Y-m-d H:i:s') : null;
    }

    private function normalizarFiltrosUsuarios(array $filters)
    {
        return array(
            'nome' => trim((string) ($filters['nome'] ?? '')),
            'cidade' => trim((string) ($filters['cidade'] ?? '')),
            'data_inicio' => $this->normalizarDataFiltro(isset($filters['data_inicio']) ? $filters['data_inicio'] : ''),
            'data_fim' => $this->normalizarDataFiltro(isset($filters['data_fim']) ? $filters['data_fim'] : ''),
            'perfil' => trim((string) ($filters['perfil'] ?? '')),
            'curso_evento_id' => isset($filters['curso_evento_id']) ? (int) $filters['curso_evento_id'] : 0,
            'compras_tipo' => trim((string) ($filters['compras_tipo'] ?? '')),
        );
    }

    private function normalizarFiltrosIndex(array $filters)
    {
        return array(
            'q' => trim((string) ($filters['q'] ?? '')),
            'curso_evento_id' => isset($filters['curso_evento_id']) ? (int) $filters['curso_evento_id'] : 0,
            'turma_id' => isset($filters['turma_id']) ? (int) $filters['turma_id'] : 0,
            'status' => trim((string) ($filters['status'] ?? '')),
            'criado_por_usuario_id' => isset($filters['criado_por_usuario_id']) ? (int) $filters['criado_por_usuario_id'] : 0,
            'de' => $this->normalizarDataFiltro(isset($filters['de']) ? $filters['de'] : ''),
            'ate' => $this->normalizarDataFiltro(isset($filters['ate']) ? $filters['ate'] : ''),
            'beneficiario' => trim((string) ($filters['beneficiario'] ?? '')),
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

    private function gerarTokenPreview()
    {
        return 'presentes_' . bin2hex(random_bytes(16));
    }

    private function listarModelosEmailPresentes()
    {
        $modelos = array();
        foreach ($this->emailModeloModel->allForAdmin() as $modelo) {
            if (!empty($modelo['evento'])) {
                $modelos[(string) $modelo['evento']] = $modelo;
            }
        }

        $defaults = $this->emailModeloModel->findByEvento('email.presente_concedido');
        if ($defaults && !empty($defaults['evento'])) {
            $modelos[(string) $defaults['evento']] = $defaults;
        }

        return array_values($modelos);
    }

    private function formatarPrazoAcesso($tipo, $expiraEm, $dias)
    {
        if ($tipo === 'dias' && (int) $dias > 0) {
            return (int) $dias . ' dias a partir da concessão';
        }

        if ($tipo === 'data' && !empty($expiraEm)) {
            return date('d/m/Y H:i', strtotime($expiraEm));
        }

        return 'sem prazo definido';
    }

    private function aplicarCancelamentoBeneficiario(array $beneficiario, $cancelamentoTipo, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $this->beneficiarioModel->updateStatus((int) $beneficiario['id'], 'cancelado', $actorUserId, $cancelamentoTipo, $justificativa);

        if ($cancelamentoTipo === 'cancelar_pedido_inscricao') {
            $this->pedidoModel->updateStatus((int) $beneficiario['pedido_id'], 'cancelado');
            $this->pedidoModel->addStatusHistory((int) $beneficiario['pedido_id'], 'aprovado', 'cancelado', $justificativa, $actorUserId);
        }

        $this->inscricaoModel->updateStatus((int) $beneficiario['inscricao_id'], 'cancelada', null);
        $this->inscricaoModel->addStatusHistory((int) $beneficiario['inscricao_id'], 'ativa', 'cancelada', $justificativa, $actorUserId);

        $this->auditService->record(
            'presentes.beneficiario.cancelado',
            'presente_beneficiario',
            (int) $beneficiario['id'],
            array(
                'pedido_id' => (int) $beneficiario['pedido_id'],
                'inscricao_id' => (int) $beneficiario['inscricao_id'],
                'cancelamento_tipo' => $cancelamentoTipo,
                'justificativa' => $justificativa,
            ),
            $actorUserId,
            $ipAddress,
            $userAgent
        );
    }

    private function recalculateStatusCounts($campanhaId)
    {
        $beneficiarios = $this->campanhaModel->beneficiariosForCampanha($campanhaId);
        $ativos = 0;
        $cancelados = 0;
        $expirados = 0;

        foreach ($beneficiarios as $beneficiario) {
            if ($beneficiario['status'] === 'ativo') {
                $ativos++;
            } elseif ($beneficiario['status'] === 'cancelado') {
                $cancelados++;
            } elseif ($beneficiario['status'] === 'expirado') {
                $expirados++;
            }
        }

        if ($ativos > 0 && ($cancelados > 0 || $expirados > 0)) {
            return 'parcialmente_cancelado';
        }

        if ($ativos === 0 && $cancelados > 0 && $expirados === 0) {
            return 'cancelado';
        }

        if ($ativos === 0 && $cancelados === 0 && $expirados > 0) {
            return 'expirado';
        }

        return 'ativo';
    }

    private function recalcularStatusCampanha($campanhaId, $usuarioId = null)
    {
        $status = $this->recalculateStatusCounts($campanhaId);
        $this->campanhaModel->updateStatus($campanhaId, $status, $usuarioId);
    }

    private function normalizarTipoCancelamento($tipo)
    {
        $mapa = array(
            'cancelar_inscricao' => 'cancelar_inscricao',
            'cancelar_pedido_inscricao' => 'cancelar_pedido_inscricao',
            'bloquear_acesso' => 'bloquear_acesso',
        );

        $tipo = trim((string) $tipo);
        return isset($mapa[$tipo]) ? $mapa[$tipo] : '';
    }

    private function usuarioPodeVer($usuarioId)
    {
        return $this->rbacService->userHasPermission($usuarioId, 'promocionais.presentes.ver')
            || $this->rbacService->userHasPermission($usuarioId, 'promocionais.presentes.gerenciar');
    }

    private function usuarioPodeGerenciar($usuarioId)
    {
        return $this->rbacService->userHasPermission($usuarioId, 'promocionais.presentes.gerenciar');
    }

    private function usuarioPodeCancelar($usuarioId)
    {
        return $this->rbacService->userHasPermission($usuarioId, 'promocionais.presentes.cancelar')
            || $this->rbacService->userHasPermission($usuarioId, 'promocionais.presentes.cancelar_lote')
            || $this->usuarioPodeGerenciar($usuarioId);
    }

    private function gerarCodigoPresente()
    {
        return 'PRS-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }
}

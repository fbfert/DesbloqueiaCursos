<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Validator;
use App\Models\Aviso;
use App\Models\AvisoDestinatario;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Models\Usuario;
use Exception;
use InvalidArgumentException;

class AvisoService
{
    private $avisoModel;
    private $destinatarioModel;
    private $cursoModel;
    private $usuarioModel;
    private $inscricaoModel;
    private $auditService;
    private $trashService;

    private $tiposDestino = array(
        'todos_alunos' => 'Todos os alunos da plataforma',
        'alunos_curso' => 'Todos os alunos de um curso',
        'alunos_sem_compra_confirmada' => 'Alunos sem compra confirmada',
        'aluno_curso' => 'Um aluno de um curso',
        'aluno_individual' => 'Um aluno da plataforma',
    );

    private $statusOptions = array(
        'rascunho' => 'Rascunho',
        'enviado' => 'Enviado',
        'pausado' => 'Pausado',
        'encerrado' => 'Encerrado',
    );

    public function __construct()
    {
        $this->avisoModel = new Aviso();
        $this->destinatarioModel = new AvisoDestinatario();
        $this->cursoModel = new CursoEvento();
        $this->usuarioModel = new Usuario();
        $this->inscricaoModel = new Inscricao();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function formData($avisoId = null)
    {
        $aviso = $avisoId ? $this->encontrar($avisoId) : null;
        $alunoSelecionado = null;

        if ($aviso && !empty($aviso['usuario_id'])) {
            $cursoId = !empty($aviso['curso_evento_id']) ? (int) $aviso['curso_evento_id'] : null;
            if ((string) ($aviso['tipo_destino'] ?? '') === 'aluno_curso' && $cursoId) {
                $alunoSelecionado = $this->carregarAlunoConfirmadoNoCurso((int) $aviso['usuario_id'], $cursoId);
            } else {
                $alunoSelecionado = $this->carregarAlunoIndividual((int) $aviso['usuario_id']);
            }
        }

        return array(
            'aviso' => $aviso,
            'cursos' => $this->cursoModel->allForSelect(),
            'aluno_selecionado' => $alunoSelecionado,
            'tipos_destino' => $this->tiposDestino(),
            'status_options' => $this->statusOptions(),
            'origens' => $this->origens(),
            'resumo_destinatarios' => $aviso ? $this->resumoDestinatarios((int) $aviso['id']) : array('total' => 0, 'visualizados' => 0, 'ocultados' => 0, 'ativos' => 0),
        );
    }

    public function listar(array $filtros = array())
    {
        return array(
            'avisos' => $this->avisoModel->all($filtros),
            'cursos' => $this->cursoModel->allForSelect(),
            'tipos_destino' => $this->tiposDestino(),
            'status_options' => $this->statusOptions(),
            'filters' => array(
                'q' => trim((string) ($filtros['q'] ?? '')),
                'status' => trim((string) ($filtros['status'] ?? '')),
                'tipo_destino' => trim((string) ($filtros['tipo_destino'] ?? '')),
                'curso_evento_id' => isset($filtros['curso_evento_id']) ? (int) $filtros['curso_evento_id'] : 0,
                'criado_de' => trim((string) ($filtros['criado_de'] ?? '')),
                'criado_ate' => trim((string) ($filtros['criado_ate'] ?? '')),
            ),
        );
    }

    public function encontrar($id)
    {
        return $this->avisoModel->findById((int) $id);
    }

    public function salvar(array $dados, $usuarioLogadoId, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($dados['id']) ? (int) $dados['id'] : 0;
        $avisoAtual = $id > 0 ? $this->avisoModel->findById($id) : null;

        $tipoDestino = trim((string) ($dados['tipo_destino'] ?? 'todos_alunos'));
        $status = trim((string) ($dados['status'] ?? 'rascunho'));
        $statusValido = array_keys($this->statusOptions);
        $tipoValido = array_keys($this->tiposDestino);
        $permissaoOcultar = !empty($dados['permitir_ocultar']) ? 1 : 0;
        $titulo = $this->normalizarTextoOpcional($dados['titulo'] ?? null);
        $mensagem = $this->normalizarTextoOpcional($dados['mensagem'] ?? null);
        $cursoId = isset($dados['curso_evento_id']) && $dados['curso_evento_id'] !== '' ? (int) $dados['curso_evento_id'] : null;
        $usuarioId = isset($dados['usuario_id']) && $dados['usuario_id'] !== '' ? (int) $dados['usuario_id'] : null;
        $mostrarInicio = $this->normalizarDatetimeOpcional($dados['mostrar_inicio'] ?? null);
        $mostrarFim = $this->normalizarDatetimeOpcional($dados['mostrar_fim'] ?? null);
        $prioridade = isset($dados['prioridade']) && $dados['prioridade'] !== '' ? (int) $dados['prioridade'] : 0;
        $destaque = !empty($dados['destaque']) ? 1 : 0;
        $linkUrl = $this->normalizarTextoOpcional($dados['link_url'] ?? null);
        $linkRotulo = $this->normalizarTextoOpcional($dados['link_rotulo'] ?? null);
        $origem = $id > 0 && $avisoAtual && !empty($avisoAtual['origem']) ? (string) $avisoAtual['origem'] : 'manual';
        $gatilho = $id > 0 && $avisoAtual ? (isset($avisoAtual['gatilho']) ? $avisoAtual['gatilho'] : null) : null;

        $errors = array();
        if ($mensagem === '') {
            $errors[] = 'A mensagem é obrigatória.';
        }
        if (!in_array($tipoDestino, $tipoValido, true)) {
            $errors[] = 'Tipo de destino inválido.';
            $tipoDestino = 'todos_alunos';
        }
        if (!in_array($status, $statusValido, true)) {
            $errors[] = 'Status inválido.';
            $status = 'rascunho';
        }
        if ($mostrarInicio !== null && $mostrarFim !== null && strtotime($mostrarFim) < strtotime($mostrarInicio)) {
            $errors[] = 'A data final de exibição não pode ser menor que a data inicial.';
        }

        if (in_array($tipoDestino, array('alunos_curso', 'aluno_curso'), true)) {
            if ($cursoId === null || !$this->cursoModel->findById($cursoId)) {
                $errors[] = 'Selecione um curso válido.';
            }
        } else {
            $cursoId = null;
        }

        if (in_array($tipoDestino, array('aluno_curso', 'aluno_individual'), true)) {
            if ($usuarioId === null || !$this->usuarioEhAluno($usuarioId)) {
                $errors[] = 'Selecione um aluno válido.';
            }
        } else {
            $usuarioId = null;
        }

        if ($id > 0 && $avisoAtual && $avisoAtual['status'] !== 'rascunho') {
            if ((string) $avisoAtual['tipo_destino'] !== $tipoDestino) {
                $errors[] = 'Não é permitido alterar o tipo de destino de um aviso já enviado.';
            }
            if ((int) ($avisoAtual['curso_evento_id'] ?? 0) !== (int) ($cursoId ?? 0)) {
                if (in_array($tipoDestino, array('alunos_curso', 'aluno_curso'), true)) {
                    $errors[] = 'Não é permitido alterar o curso de um aviso já enviado.';
                }
            }
            if ((int) ($avisoAtual['usuario_id'] ?? 0) !== (int) ($usuarioId ?? 0)) {
                if (in_array($tipoDestino, array('aluno_curso', 'aluno_individual'), true)) {
                    $errors[] = 'Não é permitido alterar o aluno de um aviso já enviado.';
                }
            }
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'curso_evento_id' => $cursoId,
            'usuario_id' => $usuarioId,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'tipo_destino' => $tipoDestino,
            'mostrar_inicio' => $mostrarInicio,
            'mostrar_fim' => $mostrarFim,
            'permitir_ocultar' => $permissaoOcultar,
            'status' => $status,
            'origem' => $origem,
            'gatilho' => $gatilho,
            'prioridade' => $prioridade,
            'link_url' => $linkUrl,
            'link_rotulo' => $linkRotulo,
            'destaque' => $destaque,
            'editado_por' => $usuarioLogadoId,
            'editado_em' => date('Y-m-d H:i:s'),
        );

        if ($id > 0) {
            $payload['criado_por'] = $avisoAtual['criado_por'] ?? null;
            $payload['criado_em'] = $avisoAtual['criado_em'] ?? date('Y-m-d H:i:s');
        } else {
            $payload['criado_por'] = $usuarioLogadoId;
            $payload['criado_em'] = date('Y-m-d H:i:s');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $this->avisoModel->update($payload, $id);
            } else {
                $id = $this->avisoModel->create($payload);
            }

            $this->auditService->record(
                $id > 0 && $avisoAtual ? 'avisos.atualizado' : 'avisos.criado',
                'aviso',
                $id,
                array(
                    'anterior' => $avisoAtual,
                    'novo' => $payload,
                ),
                $usuarioLogadoId,
                $ipAddress,
                $userAgent
            );

            if ($status === 'enviado') {
                $resultadoEnvio = $this->enviarEmTransacao($id, $usuarioLogadoId, $ipAddress, $userAgent, true);
                if (empty($resultadoEnvio['ok'])) {
                    throw new Exception(isset($resultadoEnvio['message']) ? $resultadoEnvio['message'] : 'Não foi possível enviar o aviso.');
                }
            }

            $pdo->commit();
            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('avisos.salvar.falhou', array(
                'message' => $exception->getMessage(),
                'aviso_id' => $id,
            ));

            $message = trim((string) $exception->getMessage());
            return array('ok' => false, 'errors' => array($message !== '' ? $message : 'Não foi possível salvar o aviso.'));
        }
    }

    public function excluir($id, $usuarioLogadoId, $justificativa, $ipAddress = null, $userAgent = null)
    {
        $aviso = $this->avisoModel->findById((int) $id);
        if (!$aviso) {
            return array('ok' => false, 'message' => 'Aviso não encontrado.');
        }

        try {
            $justificativa = $this->trashService->requireReason($justificativa);
        } catch (InvalidArgumentException $exception) {
            return array('ok' => false, 'message' => $exception->getMessage());
        }

        $this->trashService->record('aviso', (int) $id, $justificativa, $aviso, $usuarioLogadoId, $ipAddress, $userAgent);
        $this->avisoModel->softDelete((int) $id);
        $this->auditService->record('avisos.excluido', 'aviso', (int) $id, array('justificativa' => $justificativa), $usuarioLogadoId, $ipAddress, $userAgent);

        return array('ok' => true);
    }

    public function enviar($avisoId, $usuarioLogadoId, $ipAddress = null, $userAgent = null)
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $resultado = $this->enviarEmTransacao((int) $avisoId, $usuarioLogadoId, $ipAddress, $userAgent, false);
            if (empty($resultado['ok'])) {
                throw new Exception(isset($resultado['message']) ? $resultado['message'] : 'Não foi possível enviar o aviso.');
            }

            $pdo->commit();
            return array('ok' => true, 'id' => (int) $avisoId, 'destinatarios' => $resultado['destinatarios'] ?? array());
        } catch (Exception $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            Logger::error('avisos.enviar.falhou', array(
                'message' => $exception->getMessage(),
                'aviso_id' => $avisoId,
            ));

            return array('ok' => false, 'message' => $exception->getMessage());
        }
    }

    public function montarDestinatarios(array $aviso)
    {
        $tipoDestino = isset($aviso['tipo_destino']) ? (string) $aviso['tipo_destino'] : 'todos_alunos';
        $avisoId = isset($aviso['id']) ? (int) $aviso['id'] : 0;
        $cursoId = isset($aviso['curso_evento_id']) && $aviso['curso_evento_id'] !== null ? (int) $aviso['curso_evento_id'] : null;
        $usuarioId = isset($aviso['usuario_id']) && $aviso['usuario_id'] !== null ? (int) $aviso['usuario_id'] : null;

        if ($tipoDestino === 'todos_alunos') {
            return $this->carregarTodosAlunos();
        }

        if ($tipoDestino === 'alunos_curso' && $cursoId !== null) {
            return $this->carregarAlunosConfirmadosNoCurso($cursoId);
        }

        if ($tipoDestino === 'alunos_sem_compra_confirmada') {
            return $this->carregarAlunosSemCompraConfirmada();
        }

        if ($tipoDestino === 'aluno_curso' && $cursoId !== null && $usuarioId !== null) {
            $aluno = $this->carregarAlunoConfirmadoNoCurso($usuarioId, $cursoId);
            return $aluno ? array($aluno) : array();
        }

        if ($tipoDestino === 'aluno_individual' && $usuarioId !== null) {
            $aluno = $this->carregarAlunoIndividual($usuarioId);
            return $aluno ? array($aluno) : array();
        }

        Logger::info('avisos.destinatarios.nao_montados', array(
            'aviso_id' => $avisoId,
            'tipo_destino' => $tipoDestino,
        ));

        return array();
    }

    public function avisosAtivosParaUsuario($usuarioId)
    {
        $avisos = $this->destinatarioModel->avisosAtivosParaUsuario((int) $usuarioId);

        foreach ($avisos as $aviso) {
            $this->marcarVisualizado((int) $aviso['id'], (int) $usuarioId);
        }

        return $avisos;
    }

    public function marcarVisualizado($avisoId, $usuarioId)
    {
        $this->destinatarioModel->marcarVisualizado((int) $avisoId, (int) $usuarioId);
    }

    public function ocultar($avisoId, $usuarioId, $ipAddress = null, $userAgent = null)
    {
        $aviso = $this->avisoModel->findById((int) $avisoId);
        if (!$aviso) {
            return array('ok' => false, 'message' => 'Aviso não encontrado.');
        }

        $this->destinatarioModel->ocultar((int) $avisoId, (int) $usuarioId);
        $this->auditService->record('avisos.ocultado', 'aviso', (int) $avisoId, array('usuario_id' => (int) $usuarioId), $usuarioId, $ipAddress, $userAgent);

        return array('ok' => true);
    }

    public function resumoDestinatarios($avisoId)
    {
        return $this->avisoModel->resumoDestinatarios((int) $avisoId);
    }

    public function destinatariosDoAviso($avisoId, array $filters = array())
    {
        $destinatarios = $this->destinatarioModel->findByAviso((int) $avisoId, $filters);

        return array(
            'destinatarios' => $destinatarios,
            'resumo' => $this->resumoDestinatarios((int) $avisoId),
            'filters' => array(
                'q' => trim((string) ($filters['q'] ?? '')),
                'status' => trim((string) ($filters['status'] ?? '')),
            ),
        );
    }

    public function buscarAlunos(array $filtros = array())
    {
        $termo = trim((string) ($filtros['q'] ?? ''));
        $cursoId = isset($filtros['curso_evento_id']) ? (int) $filtros['curso_evento_id'] : 0;
        $tipoDestino = trim((string) ($filtros['tipo_destino'] ?? ''));
        $limit = isset($filtros['limit']) ? (int) $filtros['limit'] : 12;
        if ($limit < 1 || $limit > 30) {
            $limit = 12;
        }

        $where = array(
            'u.deleted_at IS NULL',
            'u.status = "ativo"',
            'p.deleted_at IS NULL',
            'p.slug = "aluno"',
        );
        $params = array();

        if ($termo !== '') {
            $where[] = '(u.nome LIKE :q OR u.email LIKE :q OR u.cpf LIKE :q OR conf.cursos_confirmados_titulos LIKE :q)';
            $params['q'] = '%' . $termo . '%';
        }

        if ($cursoId > 0 && in_array($tipoDestino, array('alunos_curso', 'aluno_curso'), true)) {
            $where[] = 'FIND_IN_SET(:curso_id, COALESCE(conf.cursos_confirmados_ids, "")) > 0';
            $params['curso_id'] = (string) $cursoId;
        }

        $sql = 'SELECT u.id,
                       u.nome,
                       u.email,
                       u.cpf,
                       COALESCE(conf.cursos_confirmados_ids, "") AS cursos_confirmados_ids,
                       COALESCE(conf.cursos_confirmados_titulos, "") AS cursos_confirmados_titulos,
                       COALESCE(conf.total_cursos_confirmados, 0) AS total_cursos_confirmados
                FROM usuarios u
                INNER JOIN usuario_perfis up ON up.usuario_id = u.id
                INNER JOIN perfis p ON p.id = up.perfil_id
                LEFT JOIN (
                    SELECT COALESCE(i.usuario_id, pp.usuario_id) AS usuario_id,
                           GROUP_CONCAT(DISTINCT i.curso_evento_id ORDER BY i.curso_evento_id SEPARATOR ",") AS cursos_confirmados_ids,
                           GROUP_CONCAT(DISTINCT ce.nome ORDER BY ce.nome SEPARATOR " | ") AS cursos_confirmados_titulos,
                           COUNT(DISTINCT i.curso_evento_id) AS total_cursos_confirmados
                    FROM inscricoes i
                    INNER JOIN pedidos pe ON pe.id = i.pedido_id AND pe.deleted_at IS NULL
                    INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id AND pp.deleted_at IS NULL
                    LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                    INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id AND ce.deleted_at IS NULL
                    WHERE i.deleted_at IS NULL
                      AND i.status NOT IN ("pendente", "cancelada", "reprovada")
                      AND (pe.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                      AND COALESCE(i.usuario_id, pp.usuario_id) IS NOT NULL
                    GROUP BY COALESCE(i.usuario_id, pp.usuario_id)
                ) conf ON conf.usuario_id = u.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY u.nome ASC
                LIMIT ' . (int) $limit;

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->normalizarDestinatariosUsuario($rows);
    }

    private function enviarEmTransacao($avisoId, $usuarioLogadoId, $ipAddress = null, $userAgent = null, $ignoreStatus = false)
    {
        $aviso = $this->avisoModel->findById((int) $avisoId);
        if (!$aviso) {
            return array('ok' => false, 'message' => 'Aviso não encontrado.');
        }

        if (!$ignoreStatus && !in_array((string) $aviso['status'], array('rascunho', 'pausado', 'encerrado', 'enviado'), true)) {
            return array('ok' => false, 'message' => 'Status inválido para envio.');
        }

        $destinatarios = $this->montarDestinatarios($aviso);
        if (empty($destinatarios)) {
            return array('ok' => false, 'message' => 'Nenhum destinatário encontrado para este aviso.');
        }

        $novos = $this->destinatarioModel->createMany((int) $avisoId, $destinatarios);
        $this->avisoModel->marcarEnviado((int) $avisoId, (int) $usuarioLogadoId, 'enviado');
        $this->auditService->record('avisos.enviado', 'aviso', (int) $avisoId, array(
            'destinatarios' => count($destinatarios),
            'novos_destinatarios' => $novos,
        ), $usuarioLogadoId, $ipAddress, $userAgent);

        Logger::info('avisos.enviado', array(
            'aviso_id' => (int) $avisoId,
            'destinatarios' => count($destinatarios),
            'novos_destinatarios' => $novos,
        ));

        return array(
            'ok' => true,
            'destinatarios' => $destinatarios,
            'novos_destinatarios' => $novos,
        );
    }

    private function carregarTodosAlunos()
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT u.id,
                    u.nome,
                    u.email,
                    u.cpf
             FROM usuarios u
             INNER JOIN usuario_perfis up ON up.usuario_id = u.id
             INNER JOIN perfis p ON p.id = up.perfil_id
             WHERE u.deleted_at IS NULL
               AND u.status = "ativo"
               AND p.deleted_at IS NULL
               AND p.slug = "aluno"
             ORDER BY u.nome ASC'
        );
        $stmt->execute();

        return $this->normalizarDestinatariosUsuario($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function carregarAlunosConfirmadosNoCurso($cursoId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(i.usuario_id, pp.usuario_id) AS usuario_id,
                    u.nome,
                    u.email,
                    u.cpf,
                    i.curso_evento_id,
                    i.id AS inscricao_id,
                    p.id AS pedido_id
             FROM inscricoes i
             INNER JOIN pedidos p ON p.id = i.pedido_id AND p.deleted_at IS NULL
             INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id AND pp.deleted_at IS NULL
             LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
             INNER JOIN usuarios u ON u.id = COALESCE(i.usuario_id, pp.usuario_id)
             INNER JOIN usuario_perfis up ON up.usuario_id = u.id
             INNER JOIN perfis pf ON pf.id = up.perfil_id
             WHERE i.deleted_at IS NULL
               AND i.curso_evento_id = :curso_evento_id
               AND i.status NOT IN ("pendente", "cancelada", "reprovada")
               AND (p.status IN ("aprovado", "pago") OR cp.status = "aprovado")
               AND u.deleted_at IS NULL
               AND u.status = "ativo"
               AND pf.deleted_at IS NULL
               AND pf.slug = "aluno"
             ORDER BY i.id DESC'
        );
        $stmt->execute(array('curso_evento_id' => (int) $cursoId));
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->deduplicarDestinatariosPorUsuario($rows);
    }

    private function carregarAlunoConfirmadoNoCurso($usuarioId, $cursoId)
    {
        $destinatarios = $this->carregarAlunosConfirmadosNoCurso($cursoId);
        foreach ($destinatarios as $destinatario) {
            if ((int) $destinatario['usuario_id'] === (int) $usuarioId) {
                return $destinatario;
            }
        }

        return null;
    }

    private function carregarAlunoIndividual($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id,
                    u.nome,
                    u.email,
                    u.cpf
             FROM usuarios u
             INNER JOIN usuario_perfis up ON up.usuario_id = u.id
             INNER JOIN perfis p ON p.id = up.perfil_id
             WHERE u.id = :usuario_id
               AND u.deleted_at IS NULL
               AND u.status = "ativo"
               AND p.deleted_at IS NULL
               AND p.slug = "aluno"
             LIMIT 1'
        );
        $stmt->execute(array('usuario_id' => (int) $usuarioId));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['curso_evento_id'] = null;
        $row['inscricao_id'] = null;
        $row['pedido_id'] = null;

        return $row;
    }

    private function carregarAlunosSemCompraConfirmada()
    {
        $stmt = Database::connection()->query(
            'SELECT DISTINCT u.id,
                    u.nome,
                    u.email,
                    u.cpf
             FROM usuarios u
             INNER JOIN usuario_perfis up ON up.usuario_id = u.id
             INNER JOIN perfis p ON p.id = up.perfil_id
             WHERE u.deleted_at IS NULL
               AND u.status = "ativo"
               AND p.deleted_at IS NULL
               AND p.slug = "aluno"
               AND NOT EXISTS (
                    SELECT 1
                    FROM inscricoes i
                    INNER JOIN pedidos pe ON pe.id = i.pedido_id AND pe.deleted_at IS NULL
                    INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id AND pp.deleted_at IS NULL
                    LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                    WHERE i.deleted_at IS NULL
                      AND COALESCE(i.usuario_id, pp.usuario_id) = u.id
                      AND i.status NOT IN ("pendente", "cancelada", "reprovada")
                      AND (pe.status IN ("aprovado", "pago") OR cp.status = "aprovado")
               )
             ORDER BY u.nome ASC'
        );

        return $this->normalizarDestinatariosUsuario($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function carregarAlunosSelect()
    {
        $stmt = Database::connection()->query(
            'SELECT DISTINCT u.id,
                    u.nome,
                    u.email,
                    u.cpf,
                    COALESCE(conf.cursos_confirmados_ids, "") AS cursos_confirmados_ids,
                    COALESCE(conf.cursos_confirmados_titulos, "") AS cursos_confirmados_titulos,
                    COALESCE(conf.total_cursos_confirmados, 0) AS total_cursos_confirmados
             FROM usuarios u
             INNER JOIN usuario_perfis up ON up.usuario_id = u.id
             INNER JOIN perfis p ON p.id = up.perfil_id
             LEFT JOIN (
                SELECT COALESCE(i.usuario_id, pp.usuario_id) AS usuario_id,
                       GROUP_CONCAT(DISTINCT i.curso_evento_id ORDER BY i.curso_evento_id SEPARATOR ",") AS cursos_confirmados_ids,
                       GROUP_CONCAT(DISTINCT ce.nome ORDER BY ce.nome SEPARATOR " | ") AS cursos_confirmados_titulos,
                       COUNT(DISTINCT i.curso_evento_id) AS total_cursos_confirmados
                FROM inscricoes i
                INNER JOIN pedidos pe ON pe.id = i.pedido_id AND pe.deleted_at IS NULL
                INNER JOIN participantes_pedido pp ON pp.id = i.participante_pedido_id AND pp.deleted_at IS NULL
                LEFT JOIN comprovantes_pix cp ON cp.pedido_id = i.pedido_id AND cp.is_atual = 1 AND cp.deleted_at IS NULL
                INNER JOIN cursos_eventos ce ON ce.id = i.curso_evento_id AND ce.deleted_at IS NULL
                WHERE i.deleted_at IS NULL
                  AND i.status NOT IN ("pendente", "cancelada", "reprovada")
                  AND (pe.status IN ("aprovado", "pago") OR cp.status = "aprovado")
                  AND COALESCE(i.usuario_id, pp.usuario_id) IS NOT NULL
                GROUP BY COALESCE(i.usuario_id, pp.usuario_id)
             ) conf ON conf.usuario_id = u.id
             WHERE u.deleted_at IS NULL
               AND u.status = "ativo"
               AND p.deleted_at IS NULL
               AND p.slug = "aluno"
             ORDER BY u.nome ASC'
        );

        return $this->normalizarDestinatariosUsuario($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function usuarioEhAluno($usuarioId)
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id
             FROM usuarios u
             INNER JOIN usuario_perfis up ON up.usuario_id = u.id
             INNER JOIN perfis p ON p.id = up.perfil_id
             WHERE u.id = :usuario_id
               AND u.deleted_at IS NULL
               AND u.status = "ativo"
               AND p.deleted_at IS NULL
               AND p.slug = "aluno"
             LIMIT 1'
        );
        $stmt->execute(array('usuario_id' => (int) $usuarioId));
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return !empty($row);
    }

    private function normalizarDestinatariosUsuario(array $rows)
    {
        $destinatarios = array();
        foreach ($rows as $row) {
            if (empty($row['id']) && empty($row['usuario_id'])) {
                continue;
            }

            $usuarioId = (int) ($row['usuario_id'] ?? $row['id']);
            $cursosConfirmadosIds = '';
            $cursosConfirmadosTitulos = '';
            $totalCursosConfirmados = 0;
            if (!empty($row['cursos_confirmados_ids'])) {
                $cursosConfirmadosIds = trim((string) $row['cursos_confirmados_ids']);
            }
            if (!empty($row['cursos_confirmados_titulos'])) {
                $cursosConfirmadosTitulos = trim((string) $row['cursos_confirmados_titulos']);
            }
            if (isset($row['total_cursos_confirmados'])) {
                $totalCursosConfirmados = (int) $row['total_cursos_confirmados'];
            }

            $destinatarios[] = array(
                'usuario_id' => $usuarioId,
                'nome' => (string) ($row['nome'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'cpf' => (string) ($row['cpf'] ?? ''),
                'curso_evento_id' => array_key_exists('curso_evento_id', $row) ? (int) $row['curso_evento_id'] : null,
                'inscricao_id' => array_key_exists('inscricao_id', $row) ? (int) $row['inscricao_id'] : null,
                'pedido_id' => array_key_exists('pedido_id', $row) ? (int) $row['pedido_id'] : null,
                'status' => 'ativo',
                'cursos_confirmados_ids' => $cursosConfirmadosIds,
                'cursos_confirmados_titulos' => $cursosConfirmadosTitulos,
                'total_cursos_confirmados' => $totalCursosConfirmados,
            );
        }

        return $destinatarios;
    }

    private function deduplicarDestinatariosPorUsuario(array $rows)
    {
        $destinatarios = array();
        foreach ($rows as $row) {
            $usuarioId = isset($row['usuario_id']) ? (int) $row['usuario_id'] : 0;
            if ($usuarioId <= 0 || isset($destinatarios[$usuarioId])) {
                continue;
            }

            $destinatarios[$usuarioId] = array(
                'usuario_id' => $usuarioId,
                'nome' => (string) ($row['nome'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'cpf' => (string) ($row['cpf'] ?? ''),
                'curso_evento_id' => isset($row['curso_evento_id']) ? (int) $row['curso_evento_id'] : null,
                'inscricao_id' => isset($row['inscricao_id']) ? (int) $row['inscricao_id'] : null,
                'pedido_id' => isset($row['pedido_id']) ? (int) $row['pedido_id'] : null,
                'status' => 'ativo',
            );
        }

        return array_values($destinatarios);
    }

    private function normalizarTextoOpcional($valor)
    {
        $valor = trim((string) $valor);
        return $valor === '' ? null : $valor;
    }

    private function normalizarDatetimeOpcional($valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $valor = str_replace('T', ' ', $valor);
        if (strlen($valor) === 16) {
            $valor .= ':00';
        }

        $timestamp = strtotime($valor);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function tiposDestino()
    {
        return $this->tiposDestino;
    }

    private function statusOptions()
    {
        return $this->statusOptions;
    }

    private function origens()
    {
        return array(
            'manual' => 'Manual',
            'automatico' => 'Automático',
            'sistema' => 'Sistema',
        );
    }
}

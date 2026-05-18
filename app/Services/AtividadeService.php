<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Aula;
use App\Models\Atividade;
use App\Models\AtividadeEntrega;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Models\Modulo;
use App\Models\Turma;
use App\Support\HtmlSanitizer;
use Exception;

class AtividadeService
{
    private const STATUS_VALIDOS = array('rascunho', 'publicado', 'oculto');
    private const TIPOS_ENTREGA_VALIDOS = array('texto', 'arquivo', 'texto_ou_arquivo');
    private const STATUS_ENTREGA_VALIDOS = array('enviada', 'reenviada', 'corrigida', 'devolvida', 'atrasada');
    private const EXTENSOES_PERMITIDAS = array('pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv');
    private const MIME_PERMITIDOS = array(
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
    );

    private $atividadeModel;
    private $atividadeEntregaModel;
    private $cursoModel;
    private $turmaModel;
    private $moduloModel;
    private $aulaModel;
    private $inscricaoModel;
    private $fileStorageService;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->atividadeModel = new Atividade();
        $this->atividadeEntregaModel = new AtividadeEntrega();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->moduloModel = new Modulo();
        $this->aulaModel = new Aula();
        $this->inscricaoModel = new Inscricao();
        $this->fileStorageService = new FileStorageService();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listarPorContexto($cursoId, $turmaId = null, $moduloId = null, $aulaId = null, $status = null, $usuarioId = null)
    {
        return $this->atividadeModel->listForContext($cursoId, $turmaId, $moduloId, $aulaId, $status, $usuarioId);
    }

    public function listarEntregasPorAtividade($atividadeId, $status = null)
    {
        if ($status === 'pendente') {
            $status = array('enviada', 'reenviada', 'devolvida', 'atrasada');
        }

        if ($status === 'nao_enviada') {
            return $this->atividadeEntregaModel->listNaoEnviadasForAtividade($atividadeId);
        }

        return $this->atividadeEntregaModel->listForAtividade($atividadeId, $status);
    }

    public function contarPorContexto($cursoId, $turmaId = null)
    {
        return count($this->atividadeModel->listForContext($cursoId, $turmaId));
    }

    public function findById($id)
    {
        return $this->atividadeModel->findById($id);
    }

    public function findEntregaById($id)
    {
        return $this->atividadeEntregaModel->findById($id);
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $atividadeExistente = $id > 0 ? $this->atividadeModel->findById($id) : null;
        if ($id > 0 && !$atividadeExistente) {
            return array('ok' => false, 'message' => 'Atividade nao encontrada.');
        }

        $cursoId = isset($data['curso_evento_id']) ? (int) $data['curso_evento_id'] : 0;
        if ($cursoId <= 0 && !empty($atividadeExistente['curso_evento_id'])) {
            $cursoId = (int) $atividadeExistente['curso_evento_id'];
        }
        if ($cursoId <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido para a atividade.');
        }

        $aulaId = !empty($data['aula_id']) ? (int) $data['aula_id'] : 0;
        if ($aulaId <= 0 && !empty($atividadeExistente['aula_id'])) {
            $aulaId = (int) $atividadeExistente['aula_id'];
        }
        if ($aulaId <= 0) {
            return array('ok' => false, 'message' => 'Selecione uma aula para a atividade.');
        }

        $aula = $this->aulaModel->findById($aulaId);
        if (!$aula) {
            return array('ok' => false, 'message' => 'Aula nao encontrada para a atividade.');
        }

        if ((int) $aula['curso_evento_id'] !== $cursoId) {
            return array('ok' => false, 'message' => 'A aula informada nao pertence ao curso selecionado.');
        }

        $moduloId = !empty($data['modulo_id']) ? (int) $data['modulo_id'] : 0;
        if ($moduloId <= 0 && !empty($atividadeExistente['modulo_id'])) {
            $moduloId = (int) $atividadeExistente['modulo_id'];
        }
        if ($moduloId <= 0 && !empty($aula['modulo_id'])) {
            $moduloId = (int) $aula['modulo_id'];
        }
        if ($moduloId <= 0) {
            return array('ok' => false, 'message' => 'Selecione um modulo valido para a atividade.');
        }

        $modulo = $this->moduloModel->findById($moduloId);
        if (!$modulo) {
            return array('ok' => false, 'message' => 'Modulo nao encontrado para a atividade.');
        }

        if ((int) $modulo['curso_evento_id'] !== $cursoId) {
            return array('ok' => false, 'message' => 'Modulo informado nao pertence ao curso selecionado.');
        }

        $turmaId = array_key_exists('turma_id', $data) && $data['turma_id'] !== '' ? (int) $data['turma_id'] : null;
        if ($turmaId === null && !empty($atividadeExistente['turma_id'])) {
            $turmaId = (int) $atividadeExistente['turma_id'];
        }

        $turmaAula = !empty($aula['turma_id']) ? (int) $aula['turma_id'] : null;
        $turmaModulo = !empty($modulo['turma_id']) ? (int) $modulo['turma_id'] : null;
        $turmaEsperada = $turmaAula !== null ? $turmaAula : $turmaModulo;

        if ($turmaEsperada !== null && $turmaId === null) {
            $turmaId = $turmaEsperada;
        }

        if ($turmaId !== null && $turmaEsperada === null) {
            return array('ok' => false, 'message' => 'A atividade nao pertence a turma selecionada.');
        }

        if ($turmaId !== null && $turmaEsperada !== null && $turmaId !== $turmaEsperada) {
            return array('ok' => false, 'message' => 'A atividade nao pertence a turma selecionada.');
        }

        if ((int) $aula['modulo_id'] !== $moduloId) {
            return array('ok' => false, 'message' => 'A aula informada nao pertence ao modulo selecionado.');
        }

        $titulo = trim((string) (isset($data['titulo']) ? $data['titulo'] : ''));
        if ($titulo === '') {
            return array('ok' => false, 'message' => 'Informe o titulo da atividade.');
        }

        $tipoEntrega = $this->normalizarTipoEntrega(isset($data['tipo_entrega']) ? $data['tipo_entrega'] : ($atividadeExistente['tipo_entrega'] ?? 'texto'));
        if ($tipoEntrega === null) {
            return array('ok' => false, 'message' => 'Tipo de entrega invalido.');
        }

        $statusEntrada = isset($data['status']) ? trim((string) $data['status']) : '';
        if ($statusEntrada !== '' && !in_array($statusEntrada, self::STATUS_VALIDOS, true)) {
            return array('ok' => false, 'message' => 'Status da atividade invalido.');
        }

        $status = $statusEntrada !== '' ? $statusEntrada : (!empty($atividadeExistente['status']) ? (string) $atividadeExistente['status'] : 'publicado');
        if (!in_array($status, self::STATUS_VALIDOS, true)) {
            $status = 'publicado';
        }
        $descricao = isset($data['descricao']) ? HtmlSanitizer::clean((string) $data['descricao'], 'full') : null;
        $prazo = $this->normalizarPrazo(isset($data['prazo']) ? $data['prazo'] : null, $atividadeExistente);
        $notaMaxima = $this->normalizarNotaMaxima(isset($data['nota_maxima']) ? $data['nota_maxima'] : ($atividadeExistente['nota_maxima'] ?? 10));
        $payload = array(
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulo_id' => $moduloId,
            'aula_id' => $aulaId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'tipo_entrega' => $tipoEntrega,
            'prazo' => $prazo,
            'nota_maxima' => $notaMaxima,
            'visivel' => $status === 'publicado' ? 1 : 0,
            'status' => $status,
            'criado_por' => $id > 0 ? null : ($actorUserId ? (int) $actorUserId : null),
            'atualizado_por' => $actorUserId ? (int) $actorUserId : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : ($atividadeExistente ? (int) $atividadeExistente['ordem'] : 1),
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $anterior = $atividadeExistente;
                $this->atividadeModel->update($payload, $id);
                $acao = 'area_curso.atividade.atualizada';
            } else {
                $anterior = null;
                $id = $this->atividadeModel->create($payload);
                $acao = 'area_curso.atividade.criada';
            }

            $this->auditService->record($acao, 'atividade', $id, array('anterior' => $anterior, 'novo' => $payload), $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('atividade_id' => $id));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.atividade.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function alterarStatus($id, $status, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $atividade = $this->atividadeModel->findById($id);
        if (!$atividade) {
            return array('ok' => false, 'message' => 'Atividade nao encontrada.');
        }

        $status = $this->normalizarStatus($status, false);
        if ($status === null) {
            return array('ok' => false, 'message' => 'Status da atividade invalido.');
        }

        $payload = array(
            'curso_evento_id' => (int) $atividade['curso_evento_id'],
            'turma_id' => !empty($atividade['turma_id']) ? (int) $atividade['turma_id'] : null,
            'modulo_id' => (int) $atividade['modulo_id'],
            'aula_id' => (int) $atividade['aula_id'],
            'titulo' => $atividade['titulo'],
            'descricao' => $atividade['descricao'],
            'tipo_entrega' => $atividade['tipo_entrega'],
            'prazo' => $atividade['prazo'],
            'nota_maxima' => $atividade['nota_maxima'],
            'visivel' => $status === 'publicado' ? 1 : 0,
            'status' => $status,
            'criado_por' => null,
            'atualizado_por' => $actorUserId ? (int) $actorUserId : null,
            'ordem' => (int) $atividade['ordem'],
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->atividadeModel->update($payload, $id);
            $this->auditService->record('area_curso.atividade.status_alterado', 'atividade', $id, array('status' => $status), $actorUserId, $ipAddress, $userAgent);
            Logger::info('area_curso.atividade.status_alterado', array('atividade_id' => $id, 'status' => $status));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.atividade.status_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $atividade = $this->atividadeModel->findById($id);
        if (!$atividade) {
            return array('ok' => false, 'message' => 'Atividade nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('atividade', $id, $justificativa, $atividade, $actorUserId, $ipAddress, $userAgent);
            $this->atividadeModel->softDelete($id);
            $this->auditService->record('area_curso.atividade.excluida', 'atividade', $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
            Logger::info('area_curso.atividade.excluida', array('atividade_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.atividade.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function enviarEntrega(array $data, ?array $arquivo = null, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $atividadeId = isset($data['atividade_id']) ? (int) $data['atividade_id'] : 0;
        if ($atividadeId <= 0) {
            return array('ok' => false, 'message' => 'Atividade invalida.');
        }

        $atividade = $this->atividadeModel->findById($atividadeId);
        if (!$atividade || !$this->atividadePublicado($atividade)) {
            return array('ok' => false, 'message' => 'Atividade nao disponivel para entrega.');
        }

        $cursoId = (int) $atividade['curso_evento_id'];
        $turmaId = !empty($atividade['turma_id']) ? (int) $atividade['turma_id'] : null;
        $inscricao = $this->selecionarInscricaoUsuario($actorUserId, $cursoId, $turmaId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao valida nao encontrada para envio da atividade.');
        }

        $aula = $this->aulaModel->findById((int) $atividade['aula_id']);
        $modulo = $this->moduloModel->findById((int) $atividade['modulo_id']);
        if (!$aula || !$modulo || !$this->atividadeRelacionadaAoContexto($atividade, $aula, $modulo)) {
            return array('ok' => false, 'message' => 'Atividade fora do contexto selecionado.');
        }

        $tipoEntrega = (string) $atividade['tipo_entrega'];
        $respostaTexto = isset($data['resposta_texto']) ? trim((string) $data['resposta_texto']) : '';
        $temArquivo = $arquivo && !empty($arquivo['tmp_name']);

        if ($tipoEntrega === 'texto' && $respostaTexto === '') {
            return array('ok' => false, 'message' => 'Informe a resposta textual da atividade.');
        }

        if ($tipoEntrega === 'texto' && $temArquivo) {
            return array('ok' => false, 'message' => 'Esta atividade aceita apenas resposta em texto.');
        }

        if ($tipoEntrega === 'arquivo' && !$temArquivo) {
            return array('ok' => false, 'message' => 'Anexe um arquivo para esta atividade.');
        }

        if ($tipoEntrega === 'arquivo' && $respostaTexto !== '') {
            return array('ok' => false, 'message' => 'Esta atividade aceita apenas arquivo.');
        }

        if ($tipoEntrega === 'texto_ou_arquivo' && $respostaTexto === '' && !$temArquivo) {
            return array('ok' => false, 'message' => 'Envie uma resposta em texto ou em arquivo.');
        }

        $entregaExistente = $this->atividadeEntregaModel->findByAtividadeUsuario($atividadeId, $actorUserId);
        if ($entregaExistente && (string) $entregaExistente['status'] === 'corrigida') {
            return array('ok' => false, 'message' => 'A entrega desta atividade já foi corrigida.');
        }

        $payload = array(
            'atividade_id' => $atividadeId,
            'curso_evento_id' => $cursoId,
            'turma_id' => $turmaId,
            'modulo_id' => (int) $atividade['modulo_id'],
            'aula_id' => (int) $atividade['aula_id'],
            'usuario_id' => (int) $actorUserId,
            'resposta_texto' => $respostaTexto !== '' ? $respostaTexto : null,
            'arquivo_nome_original' => $entregaExistente ? $entregaExistente['arquivo_nome_original'] : null,
            'arquivo_nome_fisico' => $entregaExistente ? $entregaExistente['arquivo_nome_fisico'] : null,
            'arquivo_caminho' => $entregaExistente ? $entregaExistente['arquivo_caminho'] : null,
            'arquivo_mime' => $entregaExistente ? $entregaExistente['arquivo_mime'] : null,
            'arquivo_tamanho' => $entregaExistente ? $entregaExistente['arquivo_tamanho'] : null,
            'status' => $this->statusEntregaParaEnvio($atividade, $entregaExistente),
            'nota' => null,
            'feedback' => null,
            'corrigido_por' => null,
            'entregue_em' => date('Y-m-d H:i:s'),
            'corrigido_em' => null,
        );

        if ($temArquivo) {
            $diretorio = 'cursos/' . $cursoId . '/aulas/' . (int) $atividade['aula_id'] . '/atividades/' . $atividadeId . '/entregas/' . (int) $actorUserId;
            try {
                $upload = $this->fileStorageService->storeUploadedFile($arquivo, $diretorio, 'atividade-entrega', array(
                    'max_size_bytes' => 20 * 1024 * 1024,
                    'allowed_extensions' => self::EXTENSOES_PERMITIDAS,
                    'allowed_mime_types' => self::MIME_PERMITIDOS,
                ));
            } catch (\Throwable $exception) {
                return array('ok' => false, 'message' => $exception->getMessage());
            }
            $payload['arquivo_nome_original'] = $upload['original_name'];
            $payload['arquivo_nome_fisico'] = basename($upload['absolute_path']);
            $payload['arquivo_caminho'] = $upload['relative_path'];
            $payload['arquivo_mime'] = $upload['mime_type'];
            $payload['arquivo_tamanho'] = $upload['size'];
        }

        if ($entregaExistente && $payload['status'] === 'enviada') {
            $payload['status'] = 'reenviada';
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($entregaExistente) {
                $this->atividadeEntregaModel->update($payload, (int) $entregaExistente['id']);
                $id = (int) $entregaExistente['id'];
                $acao = 'area_curso.atividade.entrega_reenviada';
            } else {
                $id = $this->atividadeEntregaModel->create($payload);
                $acao = 'area_curso.atividade.entrega_criada';
            }

            $this->auditService->record($acao, 'atividade_entrega', $id, array('novo' => $payload, 'anterior' => $entregaExistente), $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('atividade_entrega_id' => $id));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.atividade.entrega_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function corrigirEntrega(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->processarEntregaAvaliacao($data, $actorUserId, $ipAddress, $userAgent, 'corrigida');
    }

    public function devolverEntrega(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        return $this->processarEntregaAvaliacao($data, $actorUserId, $ipAddress, $userAgent, 'devolvida');
    }

    private function processarEntregaAvaliacao(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null, $statusPadrao = 'corrigida')
    {
        $entregaId = isset($data['entrega_id']) ? (int) $data['entrega_id'] : 0;
        if ($entregaId <= 0) {
            return array('ok' => false, 'message' => 'Entrega invalida.');
        }

        $entrega = $this->atividadeEntregaModel->findById($entregaId);
        if (!$entrega) {
            return array('ok' => false, 'message' => 'Entrega nao encontrada.');
        }

        $atividade = $this->atividadeModel->findById((int) $entrega['atividade_id']);
        if (!$atividade || !$this->atividadePublicado($atividade)) {
            return array('ok' => false, 'message' => 'Atividade indisponivel para correção.');
        }

        $notaMaxima = (float) $atividade['nota_maxima'];
        $status = $this->normalizarStatusEntrega(isset($data['status']) ? $data['status'] : $statusPadrao);
        if ($status === null) {
            return array('ok' => false, 'message' => 'Status da entrega invalido.');
        }

        $nota = isset($data['nota']) && $data['nota'] !== '' ? $data['nota'] : null;
        if ($nota !== null && !is_numeric($nota)) {
            return array('ok' => false, 'message' => 'Nota da entrega invalida.');
        }
        $nota = $nota !== null ? (float) $nota : null;
        if ($nota !== null && ($nota < 0 || $nota > $notaMaxima)) {
            return array('ok' => false, 'message' => 'Nota fora do intervalo permitido.');
        }

        if ($status === 'corrigida' && $nota === null) {
            return array('ok' => false, 'message' => 'Informe a nota da entrega.');
        }

        if ($status === 'devolvida') {
            $nota = null;
            $feedback = isset($data['feedback']) ? trim((string) $data['feedback']) : '';
            if ($feedback === '') {
                return array('ok' => false, 'message' => 'Informe um feedback para devolver a atividade.');
            }
        }

        if ($status === 'corrigida' || $status === 'devolvida') {
            $feedback = isset($data['feedback']) ? trim((string) $data['feedback']) : null;
        } else {
            $feedback = isset($data['feedback']) ? trim((string) $data['feedback']) : null;
        }
        $payload = array(
            'atividade_id' => (int) $entrega['atividade_id'],
            'curso_evento_id' => (int) $entrega['curso_evento_id'],
            'turma_id' => !empty($entrega['turma_id']) ? (int) $entrega['turma_id'] : null,
            'modulo_id' => (int) $entrega['modulo_id'],
            'aula_id' => (int) $entrega['aula_id'],
            'usuario_id' => (int) $entrega['usuario_id'],
            'resposta_texto' => $entrega['resposta_texto'],
            'arquivo_nome_original' => $entrega['arquivo_nome_original'],
            'arquivo_nome_fisico' => $entrega['arquivo_nome_fisico'],
            'arquivo_caminho' => $entrega['arquivo_caminho'],
            'arquivo_mime' => $entrega['arquivo_mime'],
            'arquivo_tamanho' => $entrega['arquivo_tamanho'],
            'status' => $status,
            'nota' => $nota,
            'feedback' => $feedback,
            'corrigido_por' => $actorUserId ? (int) $actorUserId : null,
            'entregue_em' => $entrega['entregue_em'],
            'corrigido_em' => date('Y-m-d H:i:s'),
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->atividadeEntregaModel->update($payload, $entregaId);
            $acao = $status === 'devolvida' ? 'area_curso.atividade.entrega_devolvida' : 'area_curso.atividade.entrega_corrigida';
            $this->auditService->record($acao, 'atividade_entrega', $entregaId, array('novo' => $payload, 'anterior' => $entrega), $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('atividade_entrega_id' => $entregaId));
            $pdo->commit();

            return array('ok' => true, 'id' => $entregaId);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('area_curso.atividade.entrega_corrigir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function detalharEntrega($usuarioId, $entregaId, $contexto = 'aluno')
    {
        $entrega = $this->entregaAutorizada($usuarioId, $entregaId, $contexto);
        if (!$entrega) {
            return null;
        }

        $usuario = Database::connection()->prepare('SELECT nome, email, cpf FROM usuarios WHERE id = :id LIMIT 1');
        $usuario->execute(array('id' => (int) $entrega['usuario_id']));
        $usuarioDados = $usuario->fetch(\PDO::FETCH_ASSOC);

        $atividadeResumo = Database::connection()->prepare('SELECT titulo, prazo FROM atividades WHERE id = :id LIMIT 1');
        $atividadeResumo->execute(array('id' => (int) $entrega['atividade_id']));
        $atividadeDados = $atividadeResumo->fetch(\PDO::FETCH_ASSOC);

        $historico = $this->auditService->historyForEntity('atividade_entrega', $entregaId, 20);
        return array(
            'entrega' => $entrega,
            'atividade' => $this->atividadeModel->findById((int) $entrega['atividade_id']),
            'aula' => $this->aulaModel->findById((int) $entrega['aula_id']),
            'modulo' => $this->moduloModel->findById((int) $entrega['modulo_id']),
            'usuario_nome' => !empty($usuarioDados['nome']) ? $usuarioDados['nome'] : null,
            'usuario_email' => !empty($usuarioDados['email']) ? $usuarioDados['email'] : null,
            'usuario_cpf' => !empty($usuarioDados['cpf']) ? $usuarioDados['cpf'] : null,
            'atividade_titulo' => !empty($atividadeDados['titulo']) ? $atividadeDados['titulo'] : null,
            'atividade_prazo' => !empty($atividadeDados['prazo']) ? $atividadeDados['prazo'] : null,
            'historico' => $historico,
        );
    }

    public function prepararAcessoEntrega(array $entrega)
    {
        if (empty($entrega['arquivo_caminho'])) {
            return null;
        }

        return array(
            'tipo' => 'arquivo',
            'absolute_path' => $this->fileStorageService->privatePath($entrega['arquivo_caminho']),
            'content_type' => !empty($entrega['arquivo_mime']) ? $entrega['arquivo_mime'] : 'application/octet-stream',
            'filename' => basename($entrega['arquivo_nome_original'] ?: $entrega['arquivo_nome_fisico'] ?: $entrega['arquivo_caminho']),
        );
    }

    public function atividadeAutorizada($usuarioId, $atividadeId, $contexto = 'aluno')
    {
        $atividade = $this->atividadeModel->findById($atividadeId);
        if (!$atividade) {
            return null;
        }

        if ($contexto === 'admin') {
            return $atividade;
        }

        $aula = $this->aulaModel->findById((int) $atividade['aula_id']);
        $modulo = $this->moduloModel->findById((int) $atividade['modulo_id']);
        if (!$aula || !$modulo || !$this->atividadeRelacionadaAoContexto($atividade, $aula, $modulo)) {
            return null;
        }

        if ($contexto === 'professor') {
            return $this->professorPodeAcessarContexto($usuarioId, (int) $atividade['curso_evento_id'], !empty($atividade['turma_id']) ? (int) $atividade['turma_id'] : null) ? $atividade : null;
        }

        if (!$this->atividadePublicado($atividade)) {
            return null;
        }

        $inscricao = $this->selecionarInscricaoUsuario($usuarioId, (int) $atividade['curso_evento_id'], !empty($atividade['turma_id']) ? (int) $atividade['turma_id'] : null);
        if (!$inscricao) {
            return null;
        }

        return $atividade;
    }

    public function entregaAutorizada($usuarioId, $entregaId, $contexto = 'aluno')
    {
        $entrega = $this->atividadeEntregaModel->findById($entregaId);
        if (!$entrega) {
            return null;
        }

        $atividade = $this->atividadeModel->findById((int) $entrega['atividade_id']);
        $aula = $this->aulaModel->findById((int) $entrega['aula_id']);
        $modulo = $this->moduloModel->findById((int) $entrega['modulo_id']);
        if (!$atividade || !$aula || !$modulo || !$this->atividadeRelacionadaAoContexto($atividade, $aula, $modulo)) {
            return null;
        }

        if ($contexto === 'admin') {
            return $entrega;
        }

        if ($contexto === 'professor') {
            return $this->professorPodeAcessarContexto($usuarioId, (int) $entrega['curso_evento_id'], !empty($entrega['turma_id']) ? (int) $entrega['turma_id'] : null) ? $entrega : null;
        }

        if ((int) $entrega['usuario_id'] !== (int) $usuarioId) {
            return null;
        }

        if (!$this->atividadePublicado($atividade)) {
            return null;
        }

        $inscricao = $this->selecionarInscricaoUsuario($usuarioId, (int) $entrega['curso_evento_id'], !empty($entrega['turma_id']) ? (int) $entrega['turma_id'] : null);
        if (!$inscricao) {
            return null;
        }

        return $entrega;
    }

    private function atividadeRelacionadaAoContexto(array $atividade, array $aula, array $modulo)
    {
        if ((int) $atividade['curso_evento_id'] !== (int) $aula['curso_evento_id']) {
            return false;
        }

        if ((int) $atividade['curso_evento_id'] !== (int) $modulo['curso_evento_id']) {
            return false;
        }

        if ((int) $atividade['aula_id'] !== (int) $aula['id']) {
            return false;
        }

        if ((int) $atividade['modulo_id'] !== (int) $modulo['id']) {
            return false;
        }

        $turmaAtividade = !empty($atividade['turma_id']) ? (int) $atividade['turma_id'] : null;
        $turmaAula = !empty($aula['turma_id']) ? (int) $aula['turma_id'] : null;
        $turmaModulo = !empty($modulo['turma_id']) ? (int) $modulo['turma_id'] : null;

        return $turmaAtividade === $turmaAula && $turmaAtividade === $turmaModulo;
    }

    private function atividadePublicado(array $atividade)
    {
        if (isset($atividade['status']) && (string) $atividade['status'] !== 'publicado') {
            return false;
        }

        if (array_key_exists('visivel', $atividade) && empty($atividade['visivel'])) {
            return false;
        }

        $aula = $this->aulaModel->findById((int) $atividade['aula_id']);
        $modulo = $this->moduloModel->findById((int) $atividade['modulo_id']);
        if (!$aula || !$modulo) {
            return false;
        }

        return $this->conteudoPublicado($aula) && $this->conteudoPublicado($modulo);
    }

    private function conteudoPublicado(array $conteudo)
    {
        if (isset($conteudo['status']) && $conteudo['status'] !== '') {
            return (string) $conteudo['status'] === 'publicado';
        }

        if (array_key_exists('visivel', $conteudo)) {
            return !empty($conteudo['visivel']);
        }

        return true;
    }

    private function professorPodeAcessarContexto($usuarioId, $cursoId, $turmaId = null)
    {
        if ($usuarioId <= 0 || $cursoId <= 0) {
            return false;
        }

        $curso = $this->cursoModel->findById($cursoId);
        if (!$curso) {
            return false;
        }

        $cursos = $this->cursoModel->findAccessibleByUser($usuarioId);
        $cursoAutorizado = false;
        foreach ($cursos as $cursoItem) {
            if ((int) $cursoItem['id'] === (int) $cursoId) {
                $cursoAutorizado = true;
                break;
            }
        }

        if (!$cursoAutorizado) {
            return false;
        }

        if ($turmaId === null) {
            return true;
        }

        $turma = $this->turmaModel->findById($turmaId);
        if (!$turma || (int) $turma['curso_evento_id'] !== (int) $cursoId) {
            return false;
        }

        $turmas = $this->turmaModel->findAccessibleByUser($usuarioId);
        foreach ($turmas as $turmaItem) {
            if ((int) $turmaItem['id'] === (int) $turmaId) {
                return true;
            }
        }

        return false;
    }

    private function selecionarInscricaoUsuario($usuarioId, $cursoId, $turmaId = null)
    {
        $inscricoes = $this->inscricaoModel->forUsuarioAprovadas($usuarioId);
        foreach ($inscricoes as $inscricao) {
            if (!$this->inscricaoPodeAcessarConteudo($inscricao)) {
                continue;
            }

            if ((int) $inscricao['curso_evento_id'] !== (int) $cursoId) {
                continue;
            }

            $turmaInscricao = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null;
            if ($turmaId !== null && $turmaInscricao !== (int) $turmaId) {
                continue;
            }

            return $inscricao;
        }

        return null;
    }

    private function inscricaoPodeAcessarConteudo(array $inscricao)
    {
        $status = isset($inscricao['status']) ? (string) $inscricao['status'] : '';
        if (!in_array($status, array('ativa', 'em_andamento', 'concluida', 'concluida_sem_certificado', 'certificado_emitido'), true)) {
            return false;
        }

        return $this->inscricaoTemAcessoComercial($inscricao);
    }

    private function inscricaoTemAcessoComercial(array $inscricao)
    {
        $pedidoStatus = isset($inscricao['pedido_status']) ? (string) $inscricao['pedido_status'] : '';
        $comprovanteStatus = isset($inscricao['comprovante_status']) ? (string) $inscricao['comprovante_status'] : '';

        if (in_array($pedidoStatus, array('aprovado', 'pago'), true)) {
            return true;
        }

        if ($comprovanteStatus === 'aprovado') {
            return true;
        }

        return false;
    }

    private function normalizarStatus($status, $defaultPublicada = false)
    {
        $status = is_string($status) ? trim($status) : '';

        if ($status === '') {
            return $defaultPublicada ? 'publicado' : 'rascunho';
        }

        if (!in_array($status, self::STATUS_VALIDOS, true)) {
            return null;
        }

        return $status;
    }

    private function normalizarTipoEntrega($tipoEntrega)
    {
        $tipoEntrega = strtolower(trim((string) $tipoEntrega));
        if ($tipoEntrega === '') {
            return 'texto';
        }

        return in_array($tipoEntrega, self::TIPOS_ENTREGA_VALIDOS, true) ? $tipoEntrega : null;
    }

    private function normalizarStatusEntrega($status)
    {
        $status = strtolower(trim((string) $status));
        if ($status === '') {
            return 'corrigida';
        }

        return in_array($status, self::STATUS_ENTREGA_VALIDOS, true) ? $status : null;
    }

    private function normalizarPrazo($prazo, ?array $atividadeExistente = null)
    {
        $prazo = trim((string) $prazo);
        if ($prazo === '') {
            return !empty($atividadeExistente['prazo']) ? $atividadeExistente['prazo'] : null;
        }

        $timestamp = strtotime($prazo);
        if ($timestamp === false) {
            return !empty($atividadeExistente['prazo']) ? $atividadeExistente['prazo'] : null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizarNotaMaxima($valor)
    {
        if ($valor === null || $valor === '') {
            return 10.00;
        }

        $nota = (float) $valor;
        if ($nota < 0) {
            $nota = 0;
        }

        return round($nota, 2);
    }

    private function statusEntregaParaEnvio(array $atividade, $entregaExistente = null)
    {
        $prazo = !empty($atividade['prazo']) ? strtotime($atividade['prazo']) : false;
        $agora = time();

        if ($prazo !== false && $agora > $prazo) {
            return 'atrasada';
        }

        if ($entregaExistente) {
            return 'reenviada';
        }

        return 'enviada';
    }
}

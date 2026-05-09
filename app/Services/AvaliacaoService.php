<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Avaliacao;
use App\Models\AvaliacaoPergunta;
use App\Models\AvaliacaoRespostaUsuario;
use App\Models\Inscricao;
use App\Models\NotaAvaliacao;
use Exception;

class AvaliacaoService
{
    private $avaliacaoModel;
    private $perguntaModel;
    private $respostaModel;
    private $notaModel;
    private $inscricaoModel;
    private $aptidaoService;
    private $auditService;
    private $trashService;
    private $scopeService;

    public function __construct(array $dependencies = array())
    {
        $this->avaliacaoModel = isset($dependencies['avaliacaoModel']) ? $dependencies['avaliacaoModel'] : new Avaliacao();
        $this->perguntaModel = isset($dependencies['perguntaModel']) ? $dependencies['perguntaModel'] : new AvaliacaoPergunta();
        $this->respostaModel = isset($dependencies['respostaModel']) ? $dependencies['respostaModel'] : new AvaliacaoRespostaUsuario();
        $this->notaModel = isset($dependencies['notaModel']) ? $dependencies['notaModel'] : new NotaAvaliacao();
        $this->inscricaoModel = isset($dependencies['inscricaoModel']) ? $dependencies['inscricaoModel'] : new Inscricao();
        $this->aptidaoService = isset($dependencies['aptidaoService']) ? $dependencies['aptidaoService'] : new AptidaoCertificadoService();
        $this->auditService = isset($dependencies['auditService']) ? $dependencies['auditService'] : new AuditService();
        $this->trashService = isset($dependencies['trashService']) ? $dependencies['trashService'] : new TrashService();
        $this->scopeService = isset($dependencies['scopeService']) ? $dependencies['scopeService'] : new ProfessorAcademicScopeService(array(
            'inscricaoModel' => $this->inscricaoModel,
            'avaliacaoModel' => $this->avaliacaoModel,
            'perguntaModel' => $this->perguntaModel,
        ));
    }

    public function listarContexto($cursoId, $turmaId = null)
    {
        $avaliacoes = $this->avaliacaoModel->listForContext($cursoId, $turmaId);
        foreach ($avaliacoes as &$avaliacao) {
            $avaliacao['perguntas'] = $this->perguntaModel->listForAvaliacao($avaliacao['id']);
        }
        unset($avaliacao);

        return array('avaliacoes' => $avaliacoes);
    }

    public function salvarAvaliacao(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $payload = array(
            'curso_evento_id' => (int) $data['curso_evento_id'],
            'turma_id' => !empty($data['turma_id']) ? (int) $data['turma_id'] : null,
            'titulo' => trim((string) $data['titulo']),
            'descricao' => isset($data['descricao']) ? trim((string) $data['descricao']) : null,
            'tipo' => isset($data['tipo']) ? trim((string) $data['tipo']) : 'avaliacao',
            'visivel' => !empty($data['visivel']) ? 1 : 0,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'percentual_minimo' => isset($data['percentual_minimo']) ? (float) $data['percentual_minimo'] : 0,
            'nota_minima' => isset($data['nota_minima']) ? (float) $data['nota_minima'] : null,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        $validacaoContexto = $this->scopeService->validarContexto($payload['curso_evento_id'], $payload['turma_id']);
        if (empty($validacaoContexto['ok'])) {
            return $validacaoContexto;
        }

        if ($id > 0) {
            $validacaoAvaliacao = $this->scopeService->validarAvaliacaoNoContexto($id, $payload['curso_evento_id'], $payload['turma_id']);
            if (empty($validacaoAvaliacao['ok'])) {
                return $validacaoAvaliacao;
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $this->avaliacaoModel->update($payload, $id);
                $acao = 'academico.avaliacao.atualizada';
            } else {
                $id = $this->avaliacaoModel->create($payload);
                $acao = 'academico.avaliacao.criada';
            }

            $this->auditService->record($acao, 'avaliacao', $id, $payload, $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('avaliacao_id' => $id));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.avaliacao.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function salvarPergunta(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $payload = array(
            'avaliacao_id' => (int) $data['avaliacao_id'],
            'enunciado' => trim((string) $data['enunciado']),
            'tipo_resposta' => isset($data['tipo_resposta']) ? trim((string) $data['tipo_resposta']) : 'dissertativa',
            'opcoes_json' => isset($data['opcoes_json']) && trim((string) $data['opcoes_json']) !== '' ? trim((string) $data['opcoes_json']) : null,
            'obrigatoria' => !empty($data['obrigatoria']) ? 1 : 0,
            'ordem' => isset($data['ordem']) ? (int) $data['ordem'] : 1,
        );

        $cursoId = isset($data['curso_evento_id']) ? (int) $data['curso_evento_id'] : null;
        $turmaId = !empty($data['turma_id']) ? (int) $data['turma_id'] : null;

        if ($cursoId !== null) {
            $validacaoContexto = $this->scopeService->validarContexto($cursoId, $turmaId);
            if (empty($validacaoContexto['ok'])) {
                return $validacaoContexto;
            }

            $validacaoAvaliacao = $this->scopeService->validarAvaliacaoNoContexto($payload['avaliacao_id'], $cursoId, $turmaId);
            if (empty($validacaoAvaliacao['ok'])) {
                return $validacaoAvaliacao;
            }

            if ($id > 0) {
                $validacaoPergunta = $this->scopeService->validarPerguntaNoContexto($id, $cursoId, $turmaId);
                if (empty($validacaoPergunta['ok'])) {
                    return $validacaoPergunta;
                }
            }
        } elseif ($id > 0 && !$this->perguntaModel->findById($id)) {
            return array('ok' => false, 'message' => 'Pergunta nao encontrada.');
        }

        $validacaoConsistencia = $this->scopeService->validarPerguntaAvaliacaoConsistentes($id, $payload['avaliacao_id'], $cursoId, $turmaId);
        if ($id > 0 && empty($validacaoConsistencia['ok'])) {
            return $validacaoConsistencia;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $this->perguntaModel->update($payload, $id);
                $acao = 'academico.pergunta.atualizada';
            } else {
                $id = $this->perguntaModel->create($payload);
                $acao = 'academico.pergunta.criada';
            }

            $this->auditService->record($acao, 'avaliacao_pergunta', $id, $payload, $actorUserId, $ipAddress, $userAgent);
            Logger::info($acao, array('pergunta_id' => $id));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.pergunta.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function registrarResposta(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cursoId = isset($data['curso_evento_id']) ? (int) $data['curso_evento_id'] : 0;
        $turmaId = !empty($data['turma_id']) ? (int) $data['turma_id'] : null;

        $validacaoContexto = $this->scopeService->validarContexto($cursoId, $turmaId);
        if (empty($validacaoContexto['ok'])) {
            return $validacaoContexto;
        }

        $validacaoInscricao = $this->scopeService->validarInscricaoNoContexto(isset($data['inscricao_id']) ? (int) $data['inscricao_id'] : 0, $cursoId, $turmaId);
        if (empty($validacaoInscricao['ok'])) {
            return $validacaoInscricao;
        }

        $validacaoAvaliacao = $this->scopeService->validarAvaliacaoNoContexto(isset($data['avaliacao_id']) ? (int) $data['avaliacao_id'] : 0, $cursoId, $turmaId);
        if (empty($validacaoAvaliacao['ok'])) {
            return $validacaoAvaliacao;
        }

        $validacaoPergunta = $this->scopeService->validarPerguntaNoContexto(isset($data['pergunta_id']) ? (int) $data['pergunta_id'] : 0, $cursoId, $turmaId);
        if (empty($validacaoPergunta['ok'])) {
            return $validacaoPergunta;
        }

        $validacaoAvaliacaoInscricao = $this->scopeService->validarAvaliacaoInscricaoConsistentes((int) $data['avaliacao_id'], (int) $data['inscricao_id']);
        if (empty($validacaoAvaliacaoInscricao['ok'])) {
            return $validacaoAvaliacaoInscricao;
        }

        $validacaoPerguntaAvaliacao = $this->scopeService->validarPerguntaAvaliacaoConsistentes((int) $data['pergunta_id'], (int) $data['avaliacao_id'], $cursoId, $turmaId);
        if (empty($validacaoPerguntaAvaliacao['ok'])) {
            return $validacaoPerguntaAvaliacao;
        }

        $inscricao = $this->inscricaoModel->findById(isset($data['inscricao_id']) ? (int) $data['inscricao_id'] : 0);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        $payload = array(
            'avaliacao_id' => (int) $data['avaliacao_id'],
            'pergunta_id' => (int) $data['pergunta_id'],
            'inscricao_id' => (int) $inscricao['id'],
            'usuario_id' => !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
            'resposta_texto' => isset($data['resposta_texto']) ? trim((string) $data['resposta_texto']) : null,
            'resposta_json' => isset($data['resposta_json']) && trim((string) $data['resposta_json']) !== '' ? trim((string) $data['resposta_json']) : null,
            'pontuacao' => isset($data['pontuacao']) ? (float) $data['pontuacao'] : null,
            'corrigida_por_usuario_id' => $actorUserId,
            'corrigida_em' => date('Y-m-d H:i:s'),
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $id = $this->respostaModel->upsert($payload);
            $this->auditService->record('academico.resposta.registrada', 'avaliacao_resposta', $id, $payload, $actorUserId, $ipAddress, $userAgent);
            Logger::info('academico.resposta.registrada', array('resposta_id' => $id));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.resposta.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function registrarNota(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $cursoId = isset($data['curso_evento_id']) ? (int) $data['curso_evento_id'] : 0;
        $turmaId = !empty($data['turma_id']) ? (int) $data['turma_id'] : null;

        $validacaoContexto = $this->scopeService->validarContexto($cursoId, $turmaId);
        if (empty($validacaoContexto['ok'])) {
            return $validacaoContexto;
        }

        $validacaoInscricao = $this->scopeService->validarInscricaoNoContexto(isset($data['inscricao_id']) ? (int) $data['inscricao_id'] : 0, $cursoId, $turmaId);
        if (empty($validacaoInscricao['ok'])) {
            return $validacaoInscricao;
        }

        $validacaoAvaliacao = $this->scopeService->validarAvaliacaoNoContexto(isset($data['avaliacao_id']) ? (int) $data['avaliacao_id'] : 0, $cursoId, $turmaId);
        if (empty($validacaoAvaliacao['ok'])) {
            return $validacaoAvaliacao;
        }

        $validacaoAvaliacaoInscricao = $this->scopeService->validarAvaliacaoInscricaoConsistentes((int) $data['avaliacao_id'], (int) $data['inscricao_id']);
        if (empty($validacaoAvaliacaoInscricao['ok'])) {
            return $validacaoAvaliacaoInscricao;
        }

        $inscricao = $this->inscricaoModel->findById(isset($data['inscricao_id']) ? (int) $data['inscricao_id'] : 0);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        $payload = array(
            'avaliacao_id' => (int) $data['avaliacao_id'],
            'inscricao_id' => (int) $inscricao['id'],
            'usuario_id' => !empty($inscricao['usuario_id']) ? (int) $inscricao['usuario_id'] : null,
            'nota' => isset($data['nota']) ? (float) $data['nota'] : 0,
            'percentual' => isset($data['percentual']) ? (float) $data['percentual'] : 0,
            'status' => isset($data['status']) ? trim((string) $data['status']) : 'corrigida',
            'observacao' => isset($data['observacao']) ? trim((string) $data['observacao']) : null,
            'corrigida_por_usuario_id' => $actorUserId,
            'corrigida_em' => date('Y-m-d H:i:s'),
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $id = $this->notaModel->upsert($payload);
            $this->auditService->record('academico.nota.registrada', 'nota_avaliacao', $id, $payload, $actorUserId, $ipAddress, $userAgent);
            Logger::info('academico.nota.registrada', array('nota_id' => $id));

            $this->aptidaoService->recalcularInscricao((int) $inscricao['id'], $actorUserId, $ipAddress, $userAgent, $cursoId, $turmaId);
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.nota.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluirAvaliacao($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $registro = $this->avaliacaoModel->findById($id);
        if (!$registro) {
            return array('ok' => false, 'message' => 'Avaliacao nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('avaliacao', $id, $justificativa, $registro, $actorUserId, $ipAddress, $userAgent);
            $this->avaliacaoModel->softDelete($id);
            $this->auditService->record('academico.avaliacao.excluida', 'avaliacao', $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
            Logger::info('academico.avaliacao.excluida', array('avaliacao_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.avaliacao.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluirPergunta($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $registro = $this->perguntaModel->findById($id);
        if (!$registro) {
            return array('ok' => false, 'message' => 'Pergunta nao encontrada.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('avaliacao_pergunta', $id, $justificativa, $registro, $actorUserId, $ipAddress, $userAgent);
            $this->perguntaModel->softDelete($id);
            $this->auditService->record('academico.pergunta.excluida', 'avaliacao_pergunta', $id, array('justificativa' => $justificativa), $actorUserId, $ipAddress, $userAgent);
            Logger::info('academico.pergunta.excluida', array('pergunta_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('academico.pergunta.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }
}




<?php

namespace App\Services;

use App\Models\Aula;
use App\Models\Avaliacao;
use App\Models\AvaliacaoPergunta;
use App\Models\CursoEvento;
use App\Models\Inscricao;
use App\Models\Turma;

class ProfessorAcademicScopeService
{
    private $cursoModel;
    private $turmaModel;
    private $inscricaoModel;
    private $avaliacaoModel;
    private $perguntaModel;
    private $aulaModel;

    public function __construct(array $dependencies = array())
    {
        $this->cursoModel = isset($dependencies['cursoModel']) ? $dependencies['cursoModel'] : new CursoEvento();
        $this->turmaModel = isset($dependencies['turmaModel']) ? $dependencies['turmaModel'] : new Turma();
        $this->inscricaoModel = isset($dependencies['inscricaoModel']) ? $dependencies['inscricaoModel'] : new Inscricao();
        $this->avaliacaoModel = isset($dependencies['avaliacaoModel']) ? $dependencies['avaliacaoModel'] : new Avaliacao();
        $this->perguntaModel = isset($dependencies['perguntaModel']) ? $dependencies['perguntaModel'] : new AvaliacaoPergunta();
        $this->aulaModel = isset($dependencies['aulaModel']) ? $dependencies['aulaModel'] : new Aula();
    }

    public function validarContexto($cursoId, $turmaId = null)
    {
        $cursoId = (int) $cursoId;
        $turmaId = $turmaId !== null && $turmaId !== '' ? (int) $turmaId : null;

        if ($cursoId <= 0) {
            return array('ok' => false, 'message' => 'Curso invalido.');
        }

        $curso = $this->cursoModel->findById($cursoId);
        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso nao encontrado.');
        }

        if ($turmaId !== null) {
            $turma = $this->turmaModel->findById($turmaId);
            if (!$turma) {
                return array('ok' => false, 'message' => 'Turma nao encontrada.');
            }

            if ((int) $turma['curso_evento_id'] !== $cursoId) {
                return array('ok' => false, 'message' => 'Turma informada nao pertence ao curso selecionado.');
            }
        }

        return array('ok' => true);
    }

    public function validarInscricaoNoContexto($inscricaoId, $cursoId, $turmaId = null)
    {
        $inscricao = $this->inscricaoModel->findById((int) $inscricaoId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        return $this->compararRegistroComContexto($inscricao, $cursoId, $turmaId, 'A inscricao informada nao pertence ao contexto selecionado.');
    }

    public function validarAvaliacaoNoContexto($avaliacaoId, $cursoId, $turmaId = null)
    {
        $avaliacao = $this->avaliacaoModel->findById((int) $avaliacaoId);
        if (!$avaliacao) {
            return array('ok' => false, 'message' => 'Avaliacao nao encontrada.');
        }

        return $this->compararRegistroComContexto($avaliacao, $cursoId, $turmaId, 'A avaliacao informada nao pertence ao contexto selecionado.');
    }

    public function validarPerguntaNoContexto($perguntaId, $cursoId, $turmaId = null)
    {
        $pergunta = $this->perguntaModel->findById((int) $perguntaId);
        if (!$pergunta) {
            return array('ok' => false, 'message' => 'Pergunta nao encontrada.');
        }

        return $this->validarPerguntaEmAvaliacao($pergunta, null, $cursoId, $turmaId);
    }

    public function validarAulaNoContexto($aulaId, $cursoId, $turmaId = null)
    {
        $aula = $this->aulaModel->findById((int) $aulaId);
        if (!$aula) {
            return array('ok' => false, 'message' => 'Aula nao encontrada.');
        }

        return $this->compararRegistroComContexto($aula, $cursoId, $turmaId, 'A aula informada nao pertence ao contexto selecionado.');
    }

    public function validarAvaliacaoInscricaoConsistentes($avaliacaoId, $inscricaoId)
    {
        $avaliacao = $this->avaliacaoModel->findById((int) $avaliacaoId);
        if (!$avaliacao) {
            return array('ok' => false, 'message' => 'Avaliacao nao encontrada.');
        }

        $inscricao = $this->inscricaoModel->findById((int) $inscricaoId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        if ((int) $avaliacao['curso_evento_id'] !== (int) $inscricao['curso_evento_id']) {
            return array('ok' => false, 'message' => 'A avaliacao informada nao pertence ao curso da inscricao.');
        }

        $turmaAvaliacao = !empty($avaliacao['turma_id']) ? (int) $avaliacao['turma_id'] : null;
        $turmaInscricao = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null;
        if ($turmaAvaliacao !== $turmaInscricao) {
            return array('ok' => false, 'message' => 'A avaliacao informada nao pertence a turma da inscricao.');
        }

        return array('ok' => true);
    }

    public function validarAulaInscricaoConsistentes($aulaId, $inscricaoId)
    {
        $aula = $this->aulaModel->findById((int) $aulaId);
        if (!$aula) {
            return array('ok' => false, 'message' => 'Aula nao encontrada.');
        }

        $inscricao = $this->inscricaoModel->findById((int) $inscricaoId);
        if (!$inscricao) {
            return array('ok' => false, 'message' => 'Inscricao nao encontrada.');
        }

        if ((int) $aula['curso_evento_id'] !== (int) $inscricao['curso_evento_id']) {
            return array('ok' => false, 'message' => 'A aula informada nao pertence ao curso da inscricao.');
        }

        $turmaAula = !empty($aula['turma_id']) ? (int) $aula['turma_id'] : null;
        $turmaInscricao = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : null;
        if ($turmaAula !== $turmaInscricao) {
            return array('ok' => false, 'message' => 'A aula informada nao pertence a turma da inscricao.');
        }

        return array('ok' => true);
    }

    public function validarPerguntaAvaliacaoConsistentes($perguntaId, $avaliacaoId, $cursoId = null, $turmaId = null)
    {
        $pergunta = $this->perguntaModel->findById((int) $perguntaId);
        if (!$pergunta) {
            return array('ok' => false, 'message' => 'Pergunta nao encontrada.');
        }

        return $this->validarPerguntaEmAvaliacao($pergunta, $avaliacaoId, $cursoId, $turmaId);
    }

    private function validarPerguntaEmAvaliacao(array $pergunta, $avaliacaoId = null, $cursoId = null, $turmaId = null)
    {
        $avaliacao = $this->avaliacaoModel->findById((int) $pergunta['avaliacao_id']);
        if (!$avaliacao) {
            return array('ok' => false, 'message' => 'Avaliacao nao encontrada para a pergunta informada.');
        }

        if ($avaliacaoId !== null && (int) $avaliacao['id'] !== (int) $avaliacaoId) {
            return array('ok' => false, 'message' => 'A pergunta informada nao pertence a avaliacao selecionada.');
        }

        if ($cursoId !== null) {
            return $this->compararRegistroComContexto($avaliacao, $cursoId, $turmaId, 'A pergunta informada nao pertence ao contexto selecionado.');
        }

        return array('ok' => true);
    }

    private function compararRegistroComContexto(array $registro, $cursoId, $turmaId, $mensagem)
    {
        if ((int) $registro['curso_evento_id'] !== (int) $cursoId) {
            return array('ok' => false, 'message' => $mensagem);
        }

        $turmaRegistro = !empty($registro['turma_id']) ? (int) $registro['turma_id'] : null;
        $turmaContexto = $turmaId !== null && $turmaId !== '' ? (int) $turmaId : null;

        if ($turmaRegistro !== $turmaContexto) {
            return array('ok' => false, 'message' => $mensagem);
        }

        return array('ok' => true);
    }
}



<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Turma;
use App\Models\Usuario;
use App\Models\UsuarioTurma;
use Exception;

class TurmaService
{
    private $turmaModel;
    private $cursoModel;
    private $usuarioModel;
    private $usuarioTurmaModel;
    private $auditService;
    private $trashService;

    public function __construct()
    {
        $this->turmaModel = new Turma();
        $this->cursoModel = new \App\Models\CursoEvento();
        $this->usuarioModel = new Usuario();
        $this->usuarioTurmaModel = new UsuarioTurma();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function listAdmin()
    {
        return array(
            'turmas' => $this->turmaModel->allWithCourse(),
            'cursos' => $this->cursoModel->allForSelect(),
        );
    }

    public function listProfessor($usuarioId)
    {
        return array(
            'turmas' => $this->turmaModel->findAccessibleByUser($usuarioId),
        );
    }

    public function formData($turmaId = null)
    {
        return array(
            'turma' => $turmaId ? $this->turmaModel->findAdminById($turmaId) : null,
            'cursos' => $this->cursoModel->allForSelect(),
            'professores' => $this->usuarioModel->professores(),
            'professor_responsavel' => $turmaId ? $this->usuarioTurmaModel->findProfessorForTurma($turmaId) : null,
        );
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $cursoId = (int) $data['curso_evento_id'];
        $nome = trim((string) (isset($data['nome']) ? $data['nome'] : ''));
        $slug = $this->slugify(isset($data['slug']) && trim((string) $data['slug']) !== '' ? $data['slug'] : $nome);
        $codigo = strtoupper(trim((string) (isset($data['codigo']) ? $data['codigo'] : '')));
        $dataInicio = isset($data['data_inicio']) ? trim((string) $data['data_inicio']) : null;
        $dataFim = isset($data['data_fim']) ? trim((string) $data['data_fim']) : null;
        $vagas = isset($data['vagas']) && $data['vagas'] !== '' ? (int) $data['vagas'] : null;
        $status = isset($data['status']) && in_array($data['status'], array('planejada', 'aberta', 'encerrada', 'cancelada'), true) ? $data['status'] : 'planejada';
        $professorResponsavelUsuarioId = isset($data['professor_responsavel_usuario_id']) && $data['professor_responsavel_usuario_id'] !== ''
            ? (int) $data['professor_responsavel_usuario_id']
            : null;

        $errors = array();
        if ($cursoId <= 0) {
            $errors[] = 'Curso/evento e obrigatorio.';
        }
        if ($nome === '') {
            $errors[] = 'Nome da turma e obrigatorio.';
        }
        if ($slug === '') {
            $errors[] = 'Slug da turma e obrigatorio.';
        }
        if ($codigo === '') {
            $errors[] = 'Codigo da turma e obrigatorio.';
        }
        if ($dataInicio !== null && $dataInicio !== '' && $dataFim !== null && $dataFim !== '' && $dataFim < $dataInicio) {
            $errors[] = 'Data fim nao pode ser menor que a data inicio.';
        }

        $curso = $this->cursoModel->findById($cursoId);
        if (!$curso) {
            $errors[] = 'Curso/evento nao encontrado.';
        }

        $professorResponsavel = null;
        if ($professorResponsavelUsuarioId !== null) {
            $professorResponsavel = $this->validarProfessorResponsavel($professorResponsavelUsuarioId);
            if (!$professorResponsavel) {
                $errors[] = 'Professor responsavel nao encontrado.';
            }
        }

        $existenteSlug = $this->turmaModel->findBySlug($slug);
        if ($existenteSlug && (int) $existenteSlug['id'] !== $id) {
            $errors[] = 'Ja existe uma turma com este slug.';
        }

        $existenteCodigo = $this->turmaModel->findByCodigo($codigo);
        if ($existenteCodigo && (int) $existenteCodigo['id'] !== $id) {
            $errors[] = 'Ja existe uma turma com este codigo.';
        }

        if ($id > 0 && !$this->turmaModel->findById($id)) {
            $errors[] = 'Turma nao encontrada.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'curso_evento_id' => $cursoId,
            'nome' => $nome,
            'slug' => $slug,
            'codigo' => $codigo,
            'data_inicio' => $dataInicio !== '' ? $dataInicio : null,
            'data_fim' => $dataFim !== '' ? $dataFim : null,
            'vagas' => $vagas,
            'status' => $status,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $anterior = $this->turmaModel->findById($id);
                $this->turmaModel->update($payload, $id);
                $acao = 'catalogo.turma.atualizada';
            } else {
                $anterior = null;
                $id = $this->turmaModel->create($payload);
                $acao = 'catalogo.turma.criada';
            }

            $this->usuarioTurmaModel->syncProfessorForTurma(
                $id,
                $professorResponsavel ? (int) $professorResponsavel['id'] : null,
                'ativo'
            );

            $this->auditService->record(
                $acao,
                'turma',
                $id,
                array('anterior' => $anterior, 'novo' => $payload),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array('turma_id' => $id, 'codigo' => $codigo));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.turma.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $turma = $this->turmaModel->findById($id);
        if (!$turma) {
            return array('ok' => false, 'message' => 'Turma nao encontrada.');
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM inscricoes
             WHERE turma_id = :turma_id
               AND deleted_at IS NULL'
        );
        $stmt->execute(array('turma_id' => $id));
        $row = $stmt->fetch();
        if (!empty($row) && (int) $row['total'] > 0) {
            return array('ok' => false, 'message' => 'Não e seguro excluir turma com inscricoes vinculadas.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('turma', $id, $justificativa, $turma, $actorUserId, $ipAddress, $userAgent);
            $this->turmaModel->softDelete($id);

            $this->auditService->record(
                'catalogo.turma.excluida',
                'turma',
                $id,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('catalogo.turma.excluida', array('turma_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.turma.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    private function slugify($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
    }

    public function atualizarStatus($id, $status, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $turma = $this->turmaModel->findById($id);
        if (!$turma) {
            return array('ok' => false, 'message' => 'Turma nao encontrada.');
        }

        if (!in_array($status, array('planejada', 'aberta', 'encerrada', 'cancelada'), true)) {
            return array('ok' => false, 'message' => 'Status invalido para a turma.');
        }

        $payload = $turma;
        $payload['status'] = $status;
        $this->turmaModel->update($payload, $id);

        $this->auditService->record(
            'catalogo.turma.status_atualizado',
            'turma',
            $id,
            array('status_anterior' => $turma['status'], 'status_novo' => $status),
            $actorUserId,
            $ipAddress,
            $userAgent
        );

        Logger::info('catalogo.turma.status_atualizado', array('turma_id' => $id, 'status' => $status));

        return array('ok' => true);
    }

    private function validarProfessorResponsavel($usuarioId)
    {
        foreach ($this->usuarioModel->professores() as $professor) {
            if ((int) $professor['id'] === (int) $usuarioId) {
                return $professor;
            }
        }

        return null;
    }
}



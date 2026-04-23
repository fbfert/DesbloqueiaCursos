<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Categoria;
use App\Models\CursoEvento;
use App\Models\CursoPessoaVinculada;
use App\Models\Turma;
use Exception;

class CursoService
{
    private $categoriaModel;
    private $cursoModel;
    private $turmaModel;
    private $cursoPessoaModel;
    private $auditService;
    private $trashService;

    private $modalidades = array(
        'presencial',
        'online_ao_vivo',
        'hibrido',
        'sob_demanda',
    );

    public function __construct()
    {
        $this->categoriaModel = new Categoria();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->cursoPessoaModel = new CursoPessoaVinculada();
        $this->auditService = new AuditService();
        $this->trashService = new TrashService();
    }

    public function allowedModalidades()
    {
        return $this->modalidades;
    }

    public function listAdmin()
    {
        $cursos = $this->cursoModel->allWithCategoryAndCounts();
        $categorias = $this->categoriaModel->allWithCounts();

        foreach ($cursos as &$curso) {
            $curso['pessoas_vinculadas'] = $this->cursoPessoaModel->forCourse($curso['id']);
        }
        unset($curso);

        return array(
            'categorias' => $categorias,
            'cursos' => $cursos,
        );
    }

    public function listProfessor($usuarioId)
    {
        return array(
            'cursos' => $this->cursoModel->findAccessibleByUser($usuarioId),
        );
    }

    public function formData($cursoId = null)
    {
        $curso = $cursoId ? $this->cursoModel->findAdminById($cursoId) : null;

        return array(
            'curso' => $curso,
            'categorias' => $this->categoriaModel->allForSelect(),
            'turmas' => $cursoId ? $this->turmaModel->forCourse($cursoId) : array(),
            'pessoas_vinculadas' => $cursoId ? $this->cursoPessoaModel->forCourse($cursoId) : array(),
            'modalidades' => $this->modalidades,
        );
    }

    public function salvar(array $data, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $nome = trim((string) (isset($data['nome']) ? $data['nome'] : ''));
        $slug = $this->slugify(isset($data['slug']) && trim((string) $data['slug']) !== '' ? $data['slug'] : $nome);
        $categoriaId = isset($data['categoria_id']) && $data['categoria_id'] !== '' ? (int) $data['categoria_id'] : null;
        $tipo = isset($data['tipo']) && in_array($data['tipo'], array('curso', 'evento'), true) ? $data['tipo'] : 'curso';
        $modalidade = isset($data['modalidade']) && in_array($data['modalidade'], $this->modalidades, true) ? $data['modalidade'] : 'presencial';
        $thumbnail = isset($data['thumbnail']) ? trim((string) $data['thumbnail']) : null;
        $descricaoCurta = isset($data['descricao_curta']) ? trim((string) $data['descricao_curta']) : null;
        $descricaoCompleta = isset($data['descricao_completa']) ? trim((string) $data['descricao_completa']) : null;
        $cargaHoraria = isset($data['carga_horaria']) && $data['carga_horaria'] !== '' ? (int) $data['carga_horaria'] : null;
        $valor = isset($data['valor']) && $data['valor'] !== '' ? (float) $data['valor'] : 0;
        $ordem = isset($data['ordem']) ? (int) $data['ordem'] : 0;
        $status = isset($data['status']) && in_array($data['status'], array('rascunho', 'ativo', 'inativo', 'arquivado'), true) ? $data['status'] : 'rascunho';

        $errors = array();
        if ($nome === '') {
            $errors[] = 'Nome do curso/evento e obrigatorio.';
        }
        if ($slug === '') {
            $errors[] = 'Slug do curso/evento e obrigatorio.';
        }
        if ($valor < 0) {
            $errors[] = 'Valor invalido.';
        }

        $existente = $this->cursoModel->findBySlug($slug);
        if ($existente && (int) $existente['id'] !== $id) {
            $errors[] = 'Ja existe um curso/evento com este slug.';
        }

        if ($errors) {
            return array('ok' => false, 'errors' => $errors);
        }

        $payload = array(
            'categoria_id' => $categoriaId,
            'nome' => $nome,
            'slug' => $slug,
            'tipo' => $tipo,
            'modalidade' => $modalidade,
            'thumbnail' => $thumbnail,
            'descricao_curta' => $descricaoCurta,
            'descricao_completa' => $descricaoCompleta,
            'carga_horaria' => $cargaHoraria,
            'valor' => $valor,
            'em_promocao' => !empty($data['em_promocao']) ? 1 : 0,
            'destaque' => !empty($data['destaque']) ? 1 : 0,
            'ordem' => $ordem,
            'status' => $status,
        );

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $anterior = $this->cursoModel->findById($id);
                $this->cursoModel->update($payload, $id);
                $acao = 'catalogo.curso.atualizado';
            } else {
                $anterior = null;
                $id = $this->cursoModel->create($payload);
                $acao = 'catalogo.curso.criado';
            }

            $this->auditService->record(
                $acao,
                'curso_evento',
                $id,
                array('anterior' => $anterior, 'novo' => $payload),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info($acao, array('curso_evento_id' => $id, 'slug' => $slug));
            $pdo->commit();

            return array('ok' => true, 'id' => $id);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.curso.falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function excluir($id, $justificativa, $actorUserId = null, $ipAddress = null, $userAgent = null)
    {
        $curso = $this->cursoModel->findById($id);
        if (!$curso) {
            return array('ok' => false, 'message' => 'Curso/evento nao encontrado.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->trashService->record('curso_evento', $id, $justificativa, $curso, $actorUserId, $ipAddress, $userAgent);
            $this->cursoModel->softDelete($id);

            $this->auditService->record(
                'catalogo.curso.excluido',
                'curso_evento',
                $id,
                array('justificativa' => $justificativa),
                $actorUserId,
                $ipAddress,
                $userAgent
            );

            Logger::info('catalogo.curso.excluido', array('curso_evento_id' => $id));
            $pdo->commit();

            return array('ok' => true);
        } catch (Exception $exception) {
            $pdo->rollBack();
            Logger::error('catalogo.curso.excluir_falhou', array('message' => $exception->getMessage()));
            throw $exception;
        }
    }

    public function listPublic()
    {
        $cursos = $this->cursoModel->allPublic();

        foreach ($cursos as &$curso) {
            $curso['turmas'] = $this->turmaModel->forCourse($curso['id']);
            $curso['pessoas_vinculadas'] = $this->cursoPessoaModel->forCourse($curso['id']);
        }
        unset($curso);

        return array('cursos' => $cursos);
    }

    public function showPublic($cursoId, $turmaId = null)
    {
        $curso = $this->cursoModel->findPublicById($cursoId);

        if (!$curso) {
            return array('curso' => null);
        }

        $curso['turmas'] = $this->turmaModel->forCourse($cursoId);
        $curso['pessoas_vinculadas'] = $this->cursoPessoaModel->forCourse($cursoId);
        $curso['turma_selecionada'] = null;

        if ($turmaId) {
            foreach ($curso['turmas'] as $turma) {
                if ((int) $turma['id'] === (int) $turmaId) {
                    $curso['turma_selecionada'] = $turma;
                    break;
                }
            }
        }

        return array('curso' => $curso);
    }

    public function modalidadeLabel($modalidade)
    {
        $map = array(
            'presencial' => 'Presencial',
            'online_ao_vivo' => 'Online ao vivo',
            'hibrido' => 'Hibrido',
            'sob_demanda' => 'Sob demanda',
        );

        return isset($map[$modalidade]) ? $map[$modalidade] : (string) $modalidade;
    }

    private function slugify($value)
    {
        $value = trim((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim($value, '-');

        return $value;
    }
}

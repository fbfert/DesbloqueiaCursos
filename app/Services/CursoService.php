<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\CursoEvento;
use App\Models\CursoPessoaVinculada;
use App\Models\Turma;

class CursoService
{
    private $categoriaModel;
    private $cursoModel;
    private $turmaModel;
    private $cursoPessoaModel;

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
}

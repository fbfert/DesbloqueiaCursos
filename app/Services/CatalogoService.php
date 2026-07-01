<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\CursoDestaque;
use App\Models\CursoEvento;
use App\Models\CursoPessoaVinculada;
use App\Models\Turma;
use App\Models\UsuarioCurso;
use App\Models\UsuarioTurma;

class CatalogoService
{
    private $categoriaModel;
    private $cursoModel;
    private $turmaModel;
    private $cursoPessoaModel;
    private $usuarioCursoModel;
    private $usuarioTurmaModel;
    private $cursoDestaqueModel;

    public function __construct()
    {
        $this->categoriaModel = new Categoria();
        $this->cursoModel = new CursoEvento();
        $this->turmaModel = new Turma();
        $this->cursoPessoaModel = new CursoPessoaVinculada();
        $this->usuarioCursoModel = new UsuarioCurso();
        $this->usuarioTurmaModel = new UsuarioTurma();
        $this->cursoDestaqueModel = new CursoDestaque();
    }

    public function adminOverview()
    {
        $cursos = $this->cursoModel->allWithCategoryAndCounts();
        $turmas = $this->turmaModel->allWithCourse();
        $categorias = $this->categoriaModel->allWithCounts();
        $destaques = $this->cursoDestaqueModel->allWithCourse();

        foreach ($cursos as &$curso) {
            $curso['pessoas_vinculadas'] = $this->cursoPessoaModel->forCourse($curso['id']);
        }
        unset($curso);

        return array(
            'categorias' => $categorias,
            'cursos' => $cursos,
            'turmas' => $turmas,
            'destaques' => $destaques,
        );
    }

    public function professorOverview($usuarioId)
    {
        $cursos = $this->cursoModel->findAccessibleByUser($usuarioId);
        $turmas = $this->turmaModel->findAccessibleByUser($usuarioId);
        $vinculosCursos = $this->usuarioCursoModel->forUser($usuarioId);
        $vinculosTurmas = $this->usuarioTurmaModel->forUser($usuarioId);

        return array(
            'cursos' => $cursos,
            'turmas' => $turmas,
            'vinculos_cursos' => $vinculosCursos,
            'vinculos_turmas' => $vinculosTurmas,
        );
    }
}



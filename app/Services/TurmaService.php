<?php

namespace App\Services;

use App\Models\Turma;

class TurmaService
{
    private $turmaModel;

    public function __construct()
    {
        $this->turmaModel = new Turma();
    }

    public function listAdmin()
    {
        return array(
            'turmas' => $this->turmaModel->allWithCourse(),
        );
    }

    public function listProfessor($usuarioId)
    {
        return array(
            'turmas' => $this->turmaModel->findAccessibleByUser($usuarioId),
        );
    }
}

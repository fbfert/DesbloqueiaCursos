<?php

namespace App\Controllers\Api\App;

use App\Core\Request;
use App\Models\CursoEvento;
use App\Models\Turma;
use App\Services\AreaCursoService;
use App\Services\ConteudoCursoService;
use App\Support\AppApi\CursoPresenter;

/**
 * Meus cursos: `GET /inscricoes` e `GET /inscricoes/{id}` (com a árvore).
 */
class InscricoesController extends AppController
{
    public function index(Request $request)
    {
        $inscricoes = (new AreaCursoService())->inscricoesAcessiveisDoAluno($this->usuarioId());

        $cursos = array();
        $turmas = array();
        $saida = array();
        foreach ($inscricoes as $inscricao) {
            $saida[] = $this->apresentar($inscricao, $cursos, $turmas);
        }

        return $this->ok($saida);
    }

    public function show(Request $request)
    {
        $contexto = $this->inscricaoDoAluno($request);
        if ($contexto === null) {
            return $this->semAcesso();
        }

        $inscricao = $contexto['inscricao'];
        $conteudo = (new ConteudoCursoService())->listarConteudoPublicadoAluno(
            (int) $contexto['curso_id'],
            $this->usuarioId(),
            (int) $inscricao['id'],
            (int) $contexto['turma_id'] > 0 ? (int) $contexto['turma_id'] : null
        );
        if (empty($conteudo['ok'])) {
            return $this->erro('erro_interno', 'Não foi possível carregar o conteúdo do curso.', 500);
        }

        $cursos = array();
        $turmas = array();

        return $this->ok(array(
            'inscricao' => $this->apresentar($inscricao, $cursos, $turmas),
            'modulos' => CursoPresenter::modulos((array) ($conteudo['modulos'] ?? array())),
        ));
    }

    private function apresentar(array $inscricao, array &$cursos, array &$turmas)
    {
        $cursoId = (int) $inscricao['curso_evento_id'];
        if (!array_key_exists($cursoId, $cursos)) {
            $cursos[$cursoId] = (new CursoEvento())->findById($cursoId);
        }
        $turmaId = !empty($inscricao['turma_id']) ? (int) $inscricao['turma_id'] : 0;
        if ($turmaId > 0 && !array_key_exists($turmaId, $turmas)) {
            $turmas[$turmaId] = (new Turma())->findById($turmaId);
        }

        return CursoPresenter::inscricao($inscricao, $cursos[$cursoId] ?: null, $turmaId > 0 ? ($turmas[$turmaId] ?: null) : null);
    }
}

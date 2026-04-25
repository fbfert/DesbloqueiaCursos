<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Services/ProfessorAcademicScopeService.php';

use App\Services\ProfessorAcademicScopeService;

class FakeCursoModel
{
    private $records;

    public function __construct(array $records)
    {
        $this->records = $records;
    }

    public function findById($id)
    {
        return isset($this->records[$id]) ? $this->records[$id] : null;
    }
}

class FakeTurmaModel extends FakeCursoModel {}
class FakeInscricaoModel extends FakeCursoModel {}
class FakeAvaliacaoModel extends FakeCursoModel {}
class FakePerguntaModel extends FakeCursoModel {}
class FakeAulaModel extends FakeCursoModel {}

function assertOk(array $result, string $message): void
{
    if (empty($result['ok'])) {
        throw new RuntimeException($message . ' | retorno: ' . json_encode($result));
    }
}

function assertFail(array $result, string $expectedMessage): void
{
    if (!empty($result['ok'])) {
        throw new RuntimeException('Falha esperada, mas retorno foi OK.');
    }

    if (!isset($result['message']) || $result['message'] !== $expectedMessage) {
        throw new RuntimeException('Mensagem inesperada. Esperado: ' . $expectedMessage . ' | obtido: ' . json_encode($result));
    }
}

$service = new ProfessorAcademicScopeService(array(
    'cursoModel' => new FakeCursoModel(array(
        10 => array('id' => 10),
    )),
    'turmaModel' => new FakeTurmaModel(array(
        20 => array('id' => 20, 'curso_evento_id' => 10),
        21 => array('id' => 21, 'curso_evento_id' => 11),
    )),
    'inscricaoModel' => new FakeInscricaoModel(array(
        30 => array('id' => 30, 'curso_evento_id' => 10, 'turma_id' => 20),
        31 => array('id' => 31, 'curso_evento_id' => 10, 'turma_id' => null),
    )),
    'avaliacaoModel' => new FakeAvaliacaoModel(array(
        40 => array('id' => 40, 'curso_evento_id' => 10, 'turma_id' => 20),
        41 => array('id' => 41, 'curso_evento_id' => 10, 'turma_id' => null),
    )),
    'perguntaModel' => new FakePerguntaModel(array(
        50 => array('id' => 50, 'avaliacao_id' => 40),
        51 => array('id' => 51, 'avaliacao_id' => 41),
    )),
    'aulaModel' => new FakeAulaModel(array(
        60 => array('id' => 60, 'curso_evento_id' => 10, 'turma_id' => 20),
        61 => array('id' => 61, 'curso_evento_id' => 10, 'turma_id' => null),
    )),
));

assertOk($service->validarContexto(10, 20), 'Contexto valido deveria passar.');
assertFail($service->validarContexto(10, 21), 'Turma informada nao pertence ao curso selecionado.');

assertOk($service->validarInscricaoNoContexto(30, 10, 20), 'Inscricao no contexto deveria passar.');
assertFail($service->validarInscricaoNoContexto(30, 10, null), 'A inscricao informada nao pertence ao contexto selecionado.');

assertOk($service->validarAvaliacaoNoContexto(40, 10, 20), 'Avaliacao no contexto deveria passar.');
assertFail($service->validarAvaliacaoNoContexto(40, 10, null), 'A avaliacao informada nao pertence ao contexto selecionado.');

assertOk($service->validarPerguntaNoContexto(50, 10, 20), 'Pergunta no contexto deveria passar.');
assertFail($service->validarPerguntaNoContexto(50, 10, null), 'A pergunta informada nao pertence ao contexto selecionado.');

assertOk($service->validarAvaliacaoInscricaoConsistentes(40, 30), 'Avaliacao e inscricao consistentes deveriam passar.');
assertFail($service->validarAvaliacaoInscricaoConsistentes(41, 30), 'A avaliacao informada nao pertence a turma da inscricao.');

assertOk($service->validarAulaInscricaoConsistentes(60, 30), 'Aula e inscricao consistentes deveriam passar.');
assertFail($service->validarAulaInscricaoConsistentes(61, 30), 'A aula informada nao pertence a turma da inscricao.');

assertOk($service->validarPerguntaAvaliacaoConsistentes(50, 40, 10, 20), 'Pergunta e avaliacao consistentes deveriam passar.');
assertFail($service->validarPerguntaAvaliacaoConsistentes(51, 40, 10, 20), 'A pergunta informada nao pertence a avaliacao selecionada.');

echo "Professor academic scope tests passed.\n";

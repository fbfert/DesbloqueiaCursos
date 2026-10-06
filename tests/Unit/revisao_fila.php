<?php

/**
 * Fila de triagem dos apontamentos de revisão no admin
 * (openspec/changes/fila-revisao-admin).
 *
 * Cobre o que a fila promete ao gestor: ordem de tratamento, filtros que não
 * quebram com valor inválido, identificação do alvo mesmo depois de excluído e
 * triagem que não sobrescreve apontamento já triado.
 *
 * Roda dentro de uma transação desfeita no fim: não deixa massa no banco.
 *
 * Execução: php tests/Unit/revisao_fila.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\RevisaoComentario;
use App\Services\RevisaoComentarioService;

$pdo = testes_conectar_banco();
$pdo->beginTransaction();

$agora = date('Y-m-d H:i:s');
$sufixo = substr(md5(uniqid('fila', true)), 0, 10);

// --- massa -------------------------------------------------------------
$pdo->prepare('INSERT INTO usuarios (nome, email, cpf, senha_hash, status, created_at, updated_at)
               VALUES (?,?,?,?,?,?,?)')
    ->execute(array('REVISOR FILA', "fila-{$sufixo}@teste.local", substr($sufixo . '00000000000', 0, 11), 'x', 'ativo', $agora, $agora));
$revisorId = (int) $pdo->lastInsertId();

$criarCurso = function ($nome) use ($pdo, $agora, $sufixo) {
    $pdo->prepare('INSERT INTO cursos_eventos (nome, slug, tipo, modalidade, valor, status, created_at)
                   VALUES (?,?,?,?,?,?,?)')
        ->execute(array($nome, strtolower(str_replace(' ', '-', $nome)) . '-' . $sufixo, 'curso', 'online', 0, 'rascunho', $agora));
    return (int) $pdo->lastInsertId();
};
$cursoA = $criarCurso('Curso Fila A');
$cursoB = $criarCurso('Curso Fila B');

$pdo->prepare('INSERT INTO conteudo_modulos (curso_evento_id, titulo, ordem, status, created_at, updated_at)
               VALUES (?,?,?,?,?,?)')
    ->execute(array($cursoA, 'Módulo da fila', 1, 'publicado', $agora, $agora));
$moduloA = (int) $pdo->lastInsertId();

$criarItem = function ($tipo, $titulo) use ($pdo, $agora, $cursoA, $moduloA) {
    $pdo->prepare('INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, ordem, status, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute(array($cursoA, $moduloA, $tipo, $titulo, 1, 1, 'publicado', $agora, $agora));
    return (int) $pdo->lastInsertId();
};
$itemA = $criarItem('html', 'Aula sobre prazos');
$itemRemovido = $criarItem('html', 'Aula que será excluída');
$itemQuiz = $criarItem('quiz', 'Quiz da fila');

$pdo->prepare('INSERT INTO conteudo_quizzes (item_id, created_at, updated_at) VALUES (?,?,?)')
    ->execute(array($itemQuiz, $agora, $agora));
$quizId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO conteudo_quiz_perguntas (quiz_id, enunciado, created_at, updated_at) VALUES (?,?,?,?)')
    ->execute(array($quizId, '<p>Qual o prazo para <strong>recurso</strong> &amp; contrarrazões?</p>', $agora, $agora));
$perguntaId = (int) $pdo->lastInsertId();

$model = new RevisaoComentario();
$comentar = function ($cursoId, $alvoTipo, $alvoId, $severidade) use ($model, $revisorId) {
    return $model->create(array(
        'curso_evento_id' => $cursoId,
        'alvo_tipo' => $alvoTipo,
        'alvo_id' => $alvoId,
        'comentario' => "Apontamento {$severidade}",
        'severidade' => $severidade,
        'autor_id' => $revisorId,
    ));
};

// Ordem de criação proposital: a sugestão é a mais antiga, o erro o mais novo.
$sugestao = $comentar($cursoA, 'conteudo_item', $itemA, 'sugestao');
$jaAceito = $comentar($cursoA, 'conteudo_modulo', $moduloA, 'erro');
$naPergunta = $comentar($cursoA, 'quiz_pergunta', $perguntaId, 'impreciso');
$noRemovido = $comentar($cursoA, 'conteudo_item', $itemRemovido, 'duvida');
$erroAberto = $comentar($cursoA, 'conteudo_item', $itemA, 'erro');
$doOutroCurso = $comentar($cursoB, 'conteudo_item', $itemA, 'erro');

$pdo->prepare("UPDATE revisao_comentarios SET status = 'aceito' WHERE id = ?")->execute(array($jaAceito));
$pdo->prepare('UPDATE conteudo_itens SET deleted_at = ? WHERE id = ?')->execute(array($agora, $itemRemovido));

$service = new RevisaoComentarioService();
$ids = function (array $fila) {
    return array_map(function ($linha) { return (int) $linha['id']; }, $fila['itens']);
};

// -----------------------------------------------------------------------
describe('Ordem de tratamento');

it('abertos primeiro, o mais grave no topo, o aceito por último', function () use ($service, $ids, $cursoA, $erroAberto, $naPergunta, $noRemovido, $sugestao, $jaAceito) {
    $fila = $service->fila(array('curso_evento_id' => $cursoA));
    expect($ids($fila))->toEqual(array($erroAberto, $naPergunta, $noRemovido, $sugestao, $jaAceito));
    expect($fila['truncado'])->toBeFalse();
});

it('apontamento excluído não aparece', function () use ($service, $ids, $model, $cursoA, $sugestao) {
    $model->softDelete($sugestao);
    expect(in_array($sugestao, $ids($service->fila(array('curso_evento_id' => $cursoA))), true))->toBeFalse();
});

// -----------------------------------------------------------------------
describe('Filtros');

it('curso e severidade combinados', function () use ($service, $ids, $cursoA, $erroAberto, $jaAceito) {
    $fila = $service->fila(array('curso_evento_id' => $cursoA, 'severidade' => 'erro'));
    expect($ids($fila))->toEqual(array($erroAberto, $jaAceito));
});

it('filtro por situação', function () use ($service, $ids, $cursoA, $jaAceito) {
    expect($ids($service->fila(array('curso_evento_id' => $cursoA, 'status' => 'aceito'))))->toEqual(array($jaAceito));
});

it('o filtro de curso separa os cursos', function () use ($service, $ids, $cursoB, $doOutroCurso) {
    expect($ids($service->fila(array('curso_evento_id' => $cursoB))))->toEqual(array($doOutroCurso));
});

it('valor inválido é ignorado, sem erro', function () use ($service, $ids, $cursoA) {
    $semFiltro = $ids($service->fila(array('curso_evento_id' => $cursoA)));
    $fila = $service->fila(array('curso_evento_id' => $cursoA, 'severidade' => 'qualquer', 'status' => "x' OR 1=1"));
    expect($ids($fila))->toEqual($semFiltro);
    expect($fila['filtros']['severidade'])->toBe('');
    expect($fila['filtros']['status'])->toBe('');
});

it('filtro em formato de array é ignorado, sem warning', function () use ($service, $ids, $cursoA) {
    $semFiltro = $ids($service->fila(array('curso_evento_id' => $cursoA)));
    $fila = $service->fila(array('curso_evento_id' => $cursoA, 'severidade' => array('erro')));
    expect($ids($fila))->toEqual($semFiltro);
});

it('a volta para a fila preserva só filtros válidos, com curso_id', function () {
    expect(RevisaoComentarioService::queryStringFila(array('curso_evento_id' => '125', 'severidade' => 'erro', 'status' => 'xyz')))
        ->toBe('?curso_id=125&severidade=erro');
    expect(RevisaoComentarioService::queryStringFila(array()))->toBe('');
});

// -----------------------------------------------------------------------
describe('Identificação do alvo');

$porId = function ($fila) {
    $mapa = array();
    foreach ($fila['itens'] as $linha) {
        $mapa[(int) $linha['id']] = $linha;
    }
    return $mapa;
};

it('pergunta de quiz mostra o enunciado em texto puro', function () use ($service, $porId, $cursoA, $naPergunta) {
    $linha = $porId($service->fila(array('curso_evento_id' => $cursoA)))[$naPergunta];
    expect($linha['alvo_descricao'])->toBe('Qual o prazo para recurso & contrarrazões?');
    expect($linha['alvo_removido'])->toBeFalse();
});

it('item e módulo mostram o título, e a linha traz o nome do curso', function () use ($service, $porId, $cursoA, $erroAberto, $jaAceito) {
    $fila = $porId($service->fila(array('curso_evento_id' => $cursoA)));
    expect($fila[$erroAberto]['alvo_descricao'])->toBe('Aula sobre prazos');
    expect($fila[$jaAceito]['alvo_descricao'])->toBe('Módulo da fila');
    expect($fila[$erroAberto]['curso_nome'])->toBe('Curso Fila A');
});

it('alvo excluído aparece como removido, sem falhar', function () use ($service, $porId, $cursoA, $noRemovido) {
    $linha = $porId($service->fila(array('curso_evento_id' => $cursoA)))[$noRemovido];
    expect($linha['alvo_removido'])->toBeTrue();
    expect($linha['alvo_descricao'])->toBe('Alvo removido');
});

// -----------------------------------------------------------------------
describe('Triagem');

it('recusa sem resposta não altera o apontamento', function () use ($service, $model, $erroAberto) {
    $r = $service->triar($erroAberto, 'recusado', '   ', 1);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('resposta');
    expect($model->findById($erroAberto)['status'])->toBe('aberto');
});

it('situação fora da triagem é recusada', function () use ($service, $erroAberto) {
    $r = $service->triar($erroAberto, 'aberto', '', 1);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('status');
});

it('resolver registra gestor e data', function () use ($service, $model, $erroAberto) {
    expect($service->triar($erroAberto, 'resolvido', 'Corrigido na aula.', 1)['ok'])->toBeTrue();
    $c = $model->findById($erroAberto);
    expect($c['status'])->toBe('resolvido');
    expect((int) $c['triado_por'])->toBe(1);
    expect($c['triado_em'])->notToBeNull();
});

it('apontamento já triado não é triado de novo', function () use ($service, $model, $erroAberto) {
    $r = $service->triar($erroAberto, 'recusado', 'Mudei de ideia.', 1);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('status');
    $c = $model->findById($erroAberto);
    expect($c['status'])->toBe('resolvido');
    expect($c['resposta'])->toBe('Corrigido na aula.');
});

it('o banco também barra: UPDATE em apontamento triado não afeta linha', function () use ($model, $jaAceito) {
    expect($model->updateTriagem($jaAceito, 'recusado', 'x', 1))->toBe(0);
    expect($model->findById($jaAceito)['status'])->toBe('aceito');
});

$pdo->rollBack();

exit(testes_resumo());

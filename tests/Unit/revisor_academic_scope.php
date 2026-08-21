<?php

/**
 * Escopo academico do revisor (spec 0002-perfil-revisor).
 *
 * Cobre o risco central da feature: um erro na validacao de contexto exporia
 * cursos que nao foram atribuidos ao revisor, ou permitiria comentar em item
 * de outro curso — o alvo do comentario e polimorfico e nao tem chave
 * estrangeira que o impeca.
 *
 * A massa e criada e desfeita numa transacao revertida: o banco fica como
 * estava, mesmo se o teste falhar no meio.
 *
 * Execução: php tests/Unit/revisor_academic_scope.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\RevisaoComentarioService;
use App\Services\RevisorAcademicScopeService;

$pdo = testes_conectar_banco();
$pdo->beginTransaction();

$agora = date('Y-m-d H:i:s');
$sufixo = substr(md5(uniqid('rev', true)), 0, 10);

// --- massa -------------------------------------------------------------
$pdo->prepare('INSERT INTO usuarios (nome, email, cpf, senha_hash, status, created_at, updated_at)
               VALUES (?,?,?,?,?,?,?)')
    ->execute(array('REVISOR TESTE', "rev-{$sufixo}@teste.local", substr($sufixo . '00000000000', 0, 11), 'x', 'ativo', $agora, $agora));
$revisorId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO usuarios (nome, email, cpf, senha_hash, status, created_at, updated_at)
               VALUES (?,?,?,?,?,?,?)')
    ->execute(array('OUTRO TESTE', "out-{$sufixo}@teste.local", substr('9' . $sufixo . '0000000000', 0, 11), 'x', 'ativo', $agora, $agora));
$outroId = (int) $pdo->lastInsertId();

$criarCurso = function ($nome) use ($pdo, $agora, $sufixo) {
    $pdo->prepare('INSERT INTO cursos_eventos (nome, slug, tipo, modalidade, valor, status, created_at)
                   VALUES (?,?,?,?,?,?,?)')
        ->execute(array($nome, strtolower(str_replace(' ', '-', $nome)) . '-' . $sufixo, 'curso', 'online', 0, 'rascunho', $agora));
    return (int) $pdo->lastInsertId();
};
$cursoA = $criarCurso('Curso Revisor A');
$cursoB = $criarCurso('Curso Revisor B');

$vincular = function ($usuarioId, $cursoId, $tipo, $status) use ($pdo, $agora) {
    $pdo->prepare('INSERT INTO curso_pessoas_vinculadas (curso_evento_id, usuario_id, nome, tipo_pessoa, ordem, status, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute(array($cursoId, $usuarioId, 'TESTE', $tipo, 1, $status, $agora, $agora));
};

// Item de conteudo em cada curso, para testar o alvo do comentario.
$criarItem = function ($cursoId) use ($pdo, $agora) {
    $pdo->prepare('INSERT INTO conteudo_modulos (curso_evento_id, titulo, ordem, status, created_at, updated_at)
                   VALUES (?,?,?,?,?,?)')
        ->execute(array($cursoId, 'Módulo de teste', 1, 'publicado', $agora, $agora));
    $moduloId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, ordem, status, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute(array($cursoId, $moduloId, 'html', 'Aula de teste', 1, 1, 'publicado', $agora, $agora));
    return array((int) $pdo->lastInsertId(), $moduloId);
};
list($itemA, $moduloA) = $criarItem($cursoA);
list($itemB, ) = $criarItem($cursoB);

$escopo = new RevisorAcademicScopeService();

// -----------------------------------------------------------------------
describe('Vínculo de revisor define o que ele enxerga');

it('sem vínculo nenhum, não acessa curso algum', function () use ($escopo, $revisorId, $cursoA) {
    expect($escopo->podeAcessarCurso($revisorId, $cursoA))->toBeFalse();
    expect($escopo->cursosDoRevisor($revisorId))->toEqual(array());
});

$vincular($revisorId, $cursoA, 'revisor', 'ativo');

it('com vínculo ativo, acessa o curso atribuído', function () use ($escopo, $revisorId, $cursoA) {
    expect($escopo->podeAcessarCurso($revisorId, $cursoA))->toBeTrue();
});

it('não acessa curso em que não foi vinculado', function () use ($escopo, $revisorId, $cursoB) {
    expect($escopo->podeAcessarCurso($revisorId, $cursoB))->toBeFalse();
});

it('lista exatamente os cursos atribuídos', function () use ($escopo, $revisorId, $cursoA) {
    $cursos = $escopo->cursosDoRevisor($revisorId);
    expect(count($cursos))->toBe(1);
    expect((int) $cursos[0]['id'])->toBe($cursoA);
});

it('vínculo de professor não vale como vínculo de revisor', function () use ($escopo, $outroId, $cursoA, $vincular) {
    $vincular($outroId, $cursoA, 'professor', 'ativo');
    expect($escopo->podeAcessarCurso($outroId, $cursoA))->toBeFalse();
});

it('vínculo inativo não dá acesso', function () use ($escopo, $outroId, $cursoB, $vincular) {
    $vincular($outroId, $cursoB, 'revisor', 'inativo');
    expect($escopo->podeAcessarCurso($outroId, $cursoB))->toBeFalse();
});

it('usuário inexistente ou id inválido não acessa nada', function () use ($escopo, $cursoA) {
    expect($escopo->podeAcessarCurso(0, $cursoA))->toBeFalse();
    expect($escopo->podeAcessarCurso(-1, $cursoA))->toBeFalse();
    expect($escopo->podeAcessarCurso(999999999, $cursoA))->toBeFalse();
});

// -----------------------------------------------------------------------
describe('Alvo do comentário precisa pertencer ao curso');

it('aceita item de conteúdo do próprio curso', function () use ($escopo, $itemA, $cursoA) {
    expect($escopo->alvoPertenceAoCurso('conteudo_item', $itemA, $cursoA))->toBeTrue();
});

it('recusa item de conteúdo de outro curso', function () use ($escopo, $itemB, $cursoA) {
    expect($escopo->alvoPertenceAoCurso('conteudo_item', $itemB, $cursoA))->toBeFalse();
});

it('aceita módulo do próprio curso', function () use ($escopo, $moduloA, $cursoA) {
    expect($escopo->alvoPertenceAoCurso('conteudo_modulo', $moduloA, $cursoA))->toBeTrue();
});

it('recusa tipo de alvo desconhecido', function () use ($escopo, $itemA, $cursoA) {
    expect($escopo->alvoPertenceAoCurso('usuario', $itemA, $cursoA))->toBeFalse();
    expect($escopo->alvoPertenceAoCurso('', $itemA, $cursoA))->toBeFalse();
});

// -----------------------------------------------------------------------
describe('Serviço de comentários respeita o escopo');

$service = new RevisaoComentarioService();

it('recusa comentar em curso não atribuído', function () use ($service, $revisorId, $cursoB, $itemB) {
    $r = $service->criar(array(
        'curso_evento_id' => $cursoB, 'alvo_tipo' => 'conteudo_item',
        'alvo_id' => $itemB, 'comentario' => 'teste', 'severidade' => 'erro',
    ), $revisorId);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('curso_evento_id');
});

it('recusa comentar em item de outro curso, mesmo com o curso certo no contexto', function () use ($service, $revisorId, $cursoA, $itemB) {
    $r = $service->criar(array(
        'curso_evento_id' => $cursoA, 'alvo_tipo' => 'conteudo_item',
        'alvo_id' => $itemB, 'comentario' => 'teste', 'severidade' => 'erro',
    ), $revisorId);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('alvo_id');
});

it('recusa severidade inválida', function () use ($service, $revisorId, $cursoA, $itemA) {
    $r = $service->criar(array(
        'curso_evento_id' => $cursoA, 'alvo_tipo' => 'conteudo_item',
        'alvo_id' => $itemA, 'comentario' => 'teste', 'severidade' => 'gravissimo',
    ), $revisorId);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('severidade');
});

it('recusa comentário vazio', function () use ($service, $revisorId, $cursoA, $itemA) {
    $r = $service->criar(array(
        'curso_evento_id' => $cursoA, 'alvo_tipo' => 'conteudo_item',
        'alvo_id' => $itemA, 'comentario' => '   ', 'severidade' => 'erro',
    ), $revisorId);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('comentario');
});

$criado = null;
it('cria o apontamento válido, sempre com status aberto', function () use ($service, $revisorId, $cursoA, $itemA, &$criado) {
    $r = $service->criar(array(
        'curso_evento_id' => $cursoA, 'alvo_tipo' => 'conteudo_item', 'alvo_id' => $itemA,
        'comentario' => 'O gabarito desta questão não se sustenta.', 'severidade' => 'erro',
        'status' => 'aceito', // tentativa de definir o desfecho: deve ser ignorada
    ), $revisorId);
    expect($r['ok'])->toBeTrue();
    $criado = $r['id'];
    $model = new \App\Models\RevisaoComentario();
    expect($model->findById($criado)['status'])->toBe('aberto');
});

// -----------------------------------------------------------------------
describe('Edição e triagem');

it('o autor edita enquanto está aberto', function () use ($service, $revisorId, &$criado) {
    $r = $service->editar($criado, array('comentario' => 'Texto revisado.', 'severidade' => 'impreciso'), $revisorId);
    expect($r['ok'])->toBeTrue();
});

it('outro usuário não edita o apontamento alheio', function () use ($service, $outroId, &$criado) {
    $r = $service->editar($criado, array('comentario' => 'invasão'), $outroId);
    expect($r['ok'])->toBeFalse();
});

it('recusar exige resposta', function () use ($service, &$criado) {
    $r = $service->triar($criado, 'recusado', '   ', 1);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('resposta');
});

it('o revisor não pode triar para status inválido', function () use ($service, &$criado) {
    expect($service->triar($criado, 'aberto', 'x', 1)['ok'])->toBeFalse();
    expect($service->triar($criado, 'arquivado', 'x', 1)['ok'])->toBeFalse();
});

it('aceita a triagem válida e registra quem triou', function () use ($service, &$criado) {
    $r = $service->triar($criado, 'aceito', 'Corrigido no banco.', 1);
    expect($r['ok'])->toBeTrue();
    $c = (new \App\Models\RevisaoComentario())->findById($criado);
    expect($c['status'])->toBe('aceito');
    expect((int) $c['triado_por'])->toBe(1);
    expect($c['triado_em'])->notToBeNull();
});

it('depois de triado, o autor não edita mais', function () use ($service, $revisorId, &$criado) {
    $r = $service->editar($criado, array('comentario' => 'tarde demais'), $revisorId);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('status');
});

it('exclusão sem justificativa é recusada', function () use ($service, $revisorId, &$criado) {
    $r = $service->excluir($criado, '  ', $revisorId);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'])->toHaveKey('justificativa');
    expect((new \App\Models\RevisaoComentario())->findById($criado))->notToBeNull();
});

it('exclusão com justificativa remove logicamente', function () use ($service, $revisorId, &$criado) {
    $r = $service->excluir($criado, 'Apontamento duplicado.', $revisorId);
    expect($r['ok'])->toBeTrue();
    expect((new \App\Models\RevisaoComentario())->findById($criado))->toBeNull();
});

$pdo->rollBack();

exit(testes_resumo());

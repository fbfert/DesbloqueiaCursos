<?php

/**
 * NorminhaHints — contrato de pistas de contexto (Etapa 7).
 *
 * O que este teste protege: que o DOM não afirme mais do que sabe. Um id
 * inválido não pode virar zero nem string; uma área geral não pode alegar ter
 * aula atual; e nenhum campo pessoal pode entrar no contrato.
 *
 * Execução: php tests/Unit/norminha_hints.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Support\NorminhaHints;

describe('Normalização de ids');

it('mantém apenas inteiro positivo', function () {
    $h = NorminhaHints::montar('aula', array(
        'inscricao_id' => 17, 'curso_id' => '9', 'turma_id' => 8,
        'modulo_id' => 4, 'item_id' => 991,
    ), '/v2/aula/');

    expect($h['inscricao_id'])->toBe(17);
    expect($h['curso_id'])->toBe(9);      // string numerica vira int
    expect($h['item_id'])->toBe(991);
});

it('descarta lixo em vez de convertê-lo para zero', function () {
    foreach (array(0, -3, '0', 'abc', '1 OR 1=1', '', null, '3.7', array(), true) as $lixo) {
        $h = NorminhaHints::montar('aula', array('item_id' => $lixo), '/v2/aula/');
        if ($h['item_id'] !== null) {
            throw new RuntimeException('item_id aceitou ' . var_export($lixo, true)
                . ' e virou ' . var_export($h['item_id'], true));
        }
    }
    expect(true)->toBeTrue();
});

it('ausência de chave vira null, não zero', function () {
    $h = NorminhaHints::montar('area_aluno', array());
    foreach (array('inscricao_id', 'curso_id', 'turma_id', 'modulo_id', 'item_id') as $c) {
        expect($h[$c])->toBeNull();
    }
});

describe('O contexto não afirma mais do que sabe');

it('sem item, "aula" é rebaixado', function () {
    // sem item mas com curso -> curso
    $h = NorminhaHints::montar('aula', array('inscricao_id' => 17, 'curso_id' => 9), '/v2/aula/');
    expect($h['contexto'])->toBe('curso');

    // sem item e sem curso -> area do aluno
    $h2 = NorminhaHints::montar('aula', array('inscricao_id' => 17), '/v2/aula/');
    expect($h2['contexto'])->toBe('area_aluno');
});

it('com item, aula continua aula', function () {
    $h = NorminhaHints::montar('aula', array('curso_id' => 9, 'item_id' => 991), '/v2/aula/');
    expect($h['contexto'])->toBe('aula');
});

it('avaliação é preservada, porque o guardrail depende dela', function () {
    $h = NorminhaHints::montar('avaliacao', array('curso_id' => 9, 'item_id' => 1437), '/v2/quiz');
    expect($h['contexto'])->toBe('avaliacao');
});

it('contexto desconhecido cai no mais conservador', function () {
    foreach (array('admin', 'gabarito', '', null, 'AULA') as $invalido) {
        $h = NorminhaHints::montar($invalido, array('item_id' => 991));
        expect($h['contexto'])->toBe('area_aluno');
    }
});

describe('Rota');

it('guarda só o caminho, nunca a querystring', function () {
    $h = NorminhaHints::montar('aula', array(), '/v2/aula/?inscricao_id=17&token=segredo');
    expect($h['rota'])->toBe('/v2/aula/');
    expect(strpos($h['rota'], 'token') === false)->toBeTrue();
});

it('recusa rota que não é caminho interno', function () {
    foreach (array('https://evil.tld/x', 'javascript:alert(1)', 'sem-barra', '') as $ruim) {
        expect(NorminhaHints::montar('aula', array(), $ruim)['rota'])->toBeNull();
    }
});

describe('Reserva pela query string');

it('reconhece conteudo_id, item_id e aula_id', function () {
    foreach (array('conteudo_id', 'item_id', 'aula_id') as $chave) {
        $h = NorminhaHints::daQuery(array($chave => '991'), '/v2/aula/');
        expect($h['item_id'])->toBe(991);
    }
});

it('deduz o contexto pela rota', function () {
    expect(NorminhaHints::daQuery(array('conteudo_id' => 1), '/v2/aula/')['contexto'])->toBe('aula');
    expect(NorminhaHints::daQuery(array('curso_id' => 9), '/v2/aula/')['contexto'])->toBe('curso');
    expect(NorminhaHints::daQuery(array('conteudo_id' => 1), '/v2/quiz')['contexto'])->toBe('avaliacao');
    expect(NorminhaHints::daQuery(array('conteudo_id' => 1), '/v2/atividade')['contexto'])->toBe('avaliacao');
    expect(NorminhaHints::daQuery(array(), '/v2/aluno/')['contexto'])->toBe('area_aluno');
});

it('avaliação sem item é rebaixada — a flag não pode apontar para nada', function () {
    expect(NorminhaHints::montar('avaliacao', array('curso_id' => 9))['contexto'])->toBe('curso');
    expect(NorminhaHints::montar('avaliacao', array())['contexto'])->toBe('area_aluno');
});

it('curso sem curso_id é rebaixado', function () {
    expect(NorminhaHints::montar('curso', array('inscricao_id' => 17))['contexto'])->toBe('area_aluno');
});

it('query com lixo não produz contexto falso', function () {
    $h = NorminhaHints::daQuery(array('conteudo_id' => 'abc', 'inscricao_id' => '-1'), '/v2/aula/');
    expect($h['item_id'])->toBeNull();
    expect($h['inscricao_id'])->toBeNull();
    expect($h['contexto'])->toBe('area_aluno');   // sem item e sem curso
});

describe('Privacidade do contrato');

it('só existem as sete chaves previstas', function () {
    $h = NorminhaHints::montar('aula', array(
        'inscricao_id' => 17, 'curso_id' => 9, 'turma_id' => 8,
        'modulo_id' => 4, 'item_id' => 991,
        // campos que alguem poderia passar por engano:
        'aluno_nome' => 'Fulano', 'cpf' => '00000000000', 'email' => 'x@y.z',
        'nota' => 9.5, 'gabarito' => 'B', 'senha_hash' => 'abc',
    ), '/v2/aula/');

    $chaves = array_keys($h);
    sort($chaves);
    expect($chaves)->toEqual(array('contexto', 'curso_id', 'inscricao_id', 'item_id', 'modulo_id', 'rota', 'turma_id'));
});

it('nada pessoal sobrevive à serialização', function () {
    $h = NorminhaHints::montar('avaliacao', array(
        'inscricao_id' => 17, 'item_id' => 1437,
        'gabarito' => 'C', 'resposta_correta' => 'C', 'cpf' => '123',
    ), '/v2/quiz');

    $json = strtolower(json_encode($h));
    foreach (array('gabarito', 'resposta', 'correta', 'cpf', 'senha', 'email', 'nota') as $proibido) {
        if (strpos($json, $proibido) !== false) {
            throw new RuntimeException("contrato vazou: {$proibido}");
        }
    }
    expect(true)->toBeTrue();
});

exit(testes_resumo());

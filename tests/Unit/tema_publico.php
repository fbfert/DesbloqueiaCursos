<?php

/**
 * Seletor do tema público e prévia administrativa (openspec/changes/tema-caderno).
 *
 * Sem banco: simula a sessão com $_SESSION e usa diretório temporário para as views.
 *
 * Execução: php tests/Unit/tema_publico.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Support\TemaPublico;

describe('TemaPublico::decidir');

it('usa a chave quando não há prévia', function () {
    expect(TemaPublico::decidir('caderno', null))->toBe('caderno');
    expect(TemaPublico::decidir('v2', null))->toBe('v2');
    expect(TemaPublico::decidir('qualquer', null))->toBe('v2');
    expect(TemaPublico::decidir('', null))->toBe('v2');
});

it('a prévia vence a chave', function () {
    expect(TemaPublico::decidir('v2', 'caderno'))->toBe('caderno');
    expect(TemaPublico::decidir('caderno', 'v2'))->toBe('v2');
});

describe('TemaPublico::caminhoView');

$dirTmp = sys_get_temp_dir() . '/tema_publico_' . uniqid();
mkdir($dirTmp . '/caderno', 0777, true);

it('usa a view do caderno só se o arquivo existir', function () use ($dirTmp) {
    expect(TemaPublico::caminhoView('caderno', 'home', $dirTmp))->toBe('v2/home');
    file_put_contents($dirTmp . '/caderno/home.php', '<?php');
    expect(TemaPublico::caminhoView('caderno', 'home', $dirTmp))->toBe('caderno/home');
    expect(TemaPublico::caminhoView('v2', 'home', $dirTmp))->toBe('v2/home');
});

describe('TemaPublico::aplicarPrevia');

it('grava a prévia para quem tem permissão', function () {
    $_SESSION = array();
    TemaPublico::aplicarPrevia('caderno', 7, function ($id) { return true; });
    expect($_SESSION['tema_previa'])->toBe('caderno');
    expect(TemaPublico::emPrevia())->toBeTrue();
});

it('sem permissão não grava e remove prévia existente', function () {
    $_SESSION = array();
    TemaPublico::aplicarPrevia('caderno', 7, function ($id) { return false; });
    expect(isset($_SESSION['tema_previa']))->toBeFalse();
    $_SESSION = array('tema_previa' => 'caderno');
    TemaPublico::aplicarPrevia('caderno', 7, function ($id) { return false; });
    expect(isset($_SESSION['tema_previa']))->toBeFalse();
    expect(TemaPublico::emPrevia())->toBeFalse();
});

it('valor desconhecido não altera a prévia', function () {
    $_SESSION = array('tema_previa' => 'caderno');
    TemaPublico::aplicarPrevia('qualquer', 7, function ($id) { return true; });
    expect($_SESSION['tema_previa'])->toBe('caderno');
});

it('v2 remove a prévia', function () {
    $_SESSION = array('tema_previa' => 'caderno');
    TemaPublico::aplicarPrevia('v2', 7, function ($id) { return true; });
    expect(isset($_SESSION['tema_previa']))->toBeFalse();
});

@unlink($dirTmp . '/caderno/home.php');
@rmdir($dirTmp . '/caderno');
@rmdir($dirTmp);

testes_resumo();

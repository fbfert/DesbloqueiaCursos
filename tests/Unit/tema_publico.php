<?php

/**
 * Tema público único (openspec/changes/tema-caderno-unico): o caderno vale sempre,
 * sem chave de configuração nem prévia.
 *
 * Sem banco: simula a sessão com $_SESSION e usa diretório temporário para as views.
 *
 * Execução: php tests/Unit/tema_publico.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Support\TemaPublico;

describe('TemaPublico::ativo');

it('é sempre o caderno, com qualquer TEMA_PUBLICO', function () {
    foreach (array('v2', 'caderno', 'qualquer', '') as $valor) {
        putenv('TEMA_PUBLICO=' . $valor);
        $_ENV['TEMA_PUBLICO'] = $valor;
        expect(TemaPublico::ativo())->toBe('caderno');
    }
});

it('ignora uma prévia antiga guardada na sessão', function () {
    $_SESSION = array('tema_previa' => 'v2');
    expect(TemaPublico::ativo())->toBe('caderno');
    expect(TemaPublico::emPrevia())->toBeFalse();
});

describe('TemaPublico::caminhoView');

$dirTmp = sys_get_temp_dir() . '/tema_publico_' . uniqid();
mkdir($dirTmp . '/caderno', 0777, true);

it('usa a view do caderno só se o arquivo existir', function () use ($dirTmp) {
    expect(TemaPublico::caminhoView('caderno', 'home', $dirTmp))->toBe('v2/home');
    file_put_contents($dirTmp . '/caderno/home.php', '<?php');
    expect(TemaPublico::caminhoView('caderno', 'home', $dirTmp))->toBe('caderno/home');
});

it('toda view V2 tem par no caderno', function () {
    $base = BASE_PATH . '/resources/views';
    $faltando = array();
    foreach (glob($base . '/v2/*.php') as $arquivo) {
        $nome = basename($arquivo, '.php');
        if (TemaPublico::view($nome) !== 'caderno/' . $nome) {
            $faltando[] = $nome;
        }
    }
    foreach (glob($base . '/v2/checkout/*.php') as $arquivo) {
        $nome = 'checkout/' . basename($arquivo, '.php');
        if (TemaPublico::view($nome) !== 'caderno/' . $nome) {
            $faltando[] = $nome;
        }
    }
    expect($faltando)->toBe(array());
});

@unlink($dirTmp . '/caderno/home.php');
@rmdir($dirTmp . '/caderno');
@rmdir($dirTmp);

testes_resumo();

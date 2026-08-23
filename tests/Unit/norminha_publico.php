<?php

/**
 * Atendimento a quem ainda não tem conta (23/08/2026).
 *
 * Execução: php tests/Unit/norminha_publico.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\NorminhaPublicoService;

testes_conectar_banco();

function publico()
{
    return new NorminhaPublicoService();
}

describe('Entende o que o visitante quer');

it('reconhece as intenções que travam quem ainda não é aluno', function () {
    $esperado = array(
        'quero criar uma conta' => 'quero_me_cadastrar',
        'como faço meu cadastro' => 'quero_me_cadastrar',
        'já tenho cadastro' => 'ja_tenho_conta',
        'não consigo entrar na minha conta' => 'ja_tenho_conta',
        'esqueci minha senha' => 'esqueci_senha',
        'quanto custa' => 'preco',
        'qual o valor dos cursos' => 'preco',
        'quais cursos vocês têm' => 'escolher_curso',
        'como faço para comprar' => 'como_comprar',
        'aceita pix' => 'como_comprar',
        'tem certificado' => 'certificado',
        'quero falar com uma pessoa' => 'contato',
    );

    foreach ($esperado as $texto => $intencao) {
        $r = publico()->responder(null, $texto);
        if ($r['intent'] !== $intencao) {
            throw new RuntimeException("\"{$texto}\" virou {$r['intent']}, esperado {$intencao}");
        }
    }
    expect(true)->toBeTrue();
});

it('não inventa resposta para o que não entende', function () {
    foreach (array('qual a capital da frança', 'me conta uma piada', 'asdfgh') as $texto) {
        $r = publico()->responder(null, $texto);
        expect($r['intent'])->toBe('duvida_generica');
    }
});

describe('As sugestões aparecem em momentos oportunos');

/**
 * Pedido do responsável: repetir "já tenho cadastro / quero me cadastrar /
 * vamos escolher um curso / como faço para comprar" a cada troca empilha a
 * mesma lista na tela e faz a conversa parecer um menu que não sai do lugar.
 */

it('a abertura oferece os atalhos', function () {
    $r = publico()->responder(null, null);
    expect(isset($r['sugestoes']))->toBeTrue();
    expect(count($r['sugestoes']))->toBe(4);
});

it('uma resposta que entendeu NÃO repete os atalhos', function () {
    foreach (array('quanto custa', 'esqueci minha senha', 'quais cursos vocês têm',
                   'como faço para comprar') as $texto) {
        $r = publico()->responder(null, $texto);
        if (isset($r['sugestoes'])) {
            throw new RuntimeException("\"{$texto}\" repetiu os atalhos sem necessidade");
        }
        // Mas oferece o caminho certo para aquela dúvida.
        if (empty($r['actions'])) {
            throw new RuntimeException("\"{$texto}\" não ofereceu para onde ir");
        }
    }
    expect(true)->toBeTrue();
});

it('quando não entende, os atalhos voltam — ali eles são a saída', function () {
    $r = publico()->responder(null, 'qual a capital da frança');
    expect(isset($r['sugestoes']))->toBeTrue();
});

it('o atalho clicado não devolve a lista de atalhos', function () {
    foreach (array('ja_tenho_conta', 'quero_me_cadastrar', 'escolher_curso', 'como_comprar') as $acao) {
        $r = publico()->responder($acao, null);
        if (isset($r['sugestoes'])) {
            throw new RuntimeException("o atalho {$acao} devolveu a propria lista");
        }
    }
    expect(true)->toBeTrue();
});

it('o JavaScript esconde a barra fixa depois da primeira troca', function () {
    $js = (string) file_get_contents(BASE_PATH . '/assets/js/tutor-norminha.js');

    if (strpos($js, 'quick.hidden = true') === false) {
        throw new RuntimeException('a barra de atalhos nunca some');
    }
    if (strpos($js, 'adicionarSugestoes') === false) {
        throw new RuntimeException('o JS não desenha as sugestões reoferecidas');
    }
    expect(true)->toBeTrue();
});

describe('Só fala de coisa pública');

it('todo destino oferecido é caminho interno', function () {
    $textos = array(null, 'quanto custa', 'quero me cadastrar', 'asdfgh', 'tem certificado');
    foreach ($textos as $t) {
        $r = publico()->responder(null, $t);
        foreach ($r['actions'] as $acao) {
            if (strpos($acao['url'], '/') !== 0 || strpos($acao['url'], '//') === 0) {
                throw new RuntimeException('destino externo: ' . $acao['url']);
            }
        }
    }
    expect(true)->toBeTrue();
});

it('nenhuma resposta carrega dado de aluno', function () {
    foreach (array(null, 'meu progresso', 'meu certificado', 'minha matricula') as $t) {
        $r = publico()->responder(null, $t);
        $json = strtolower(json_encode($r, JSON_UNESCAPED_UNICODE));
        foreach (array('inscricao_id', 'usuario_id', 'percentual', 'cpf') as $proibido) {
            if (strpos($json, $proibido) !== false) {
                throw new RuntimeException("a resposta publica carrega {$proibido}");
            }
        }
    }
    expect(true)->toBeTrue();
});

exit(testes_resumo());

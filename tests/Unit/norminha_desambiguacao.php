<?php

/**
 * Escolha de curso quando o aluno tem mais de uma matrícula (23/08/2026).
 *
 * O QUE ACONTECEU: a Norminha perguntava "Você tem mais de um curso ativo.
 * Sobre qual deles quer falar?", listava os nomes como texto e não oferecia
 * NENHUMA forma de responder. As ações rápidas devolviam a mesma pergunta;
 * escrever o nome do curso, idem. Um beco sem saída, e o chat inteiro ficava
 * inútil para quem tem mais de um curso — que é a maioria dos alunos da casa.
 *
 * Eram dois defeitos somados:
 *
 *   1. O servidor SEMPRE mandou a lista em `opcoes`, e o JavaScript a
 *      descartava. Corrigido em assets/js/tutor-norminha.js, que agora desenha
 *      um botão por curso e grava a escolha em data-inscricao-id — de onde
 *      hints() lê, o que faz a escolha valer para as mensagens seguintes.
 *   2. O texto do aluno não era considerado. Corrigido aqui.
 *
 * O QUE ESTE TESTE PROTEGE: que reconhecer o curso pelo texto seja generoso com
 * a forma de escrever e severo com a dúvida. Um empate não pode virar escolha:
 * responder sobre o curso errado é pior que perguntar de novo.
 *
 * Execução: php tests/Unit/norminha_desambiguacao.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\NorminhaService;

testes_conectar_banco();

/** Chama o reconhecedor privado com opções sintéticas. */
function reconhecer($texto, array $opcoes)
{
    $servico = new NorminhaService();
    $metodo = new ReflectionMethod($servico, 'inscricaoPeloTexto');
    $metodo->setAccessible(true);

    return $metodo->invoke($servico, array('message' => $texto), $opcoes);
}

$CURSOS = array(
    array('inscricao_id' => 11, 'curso_titulo' => 'OAB 1ª Fase Completo: curso preparatório para o Exame de Ordem', 'turma_nome' => 'OAB1F01-2026A'),
    array('inscricao_id' => 22, 'curso_titulo' => 'PND na prática - CIÊNCIAS BIOLÓGICAS: preparatório', 'turma_nome' => 'PNDBIO01-2026A'),
    array('inscricao_id' => 33, 'curso_titulo' => 'PND na prática - EDUCAÇÃO FÍSICA: preparatório', 'turma_nome' => 'PNDEDF01-2026A'),
    array('inscricao_id' => 44, 'curso_titulo' => 'Libras para o ambiente escolar', 'turma_nome' => 'LIB01-2026A'),
);

describe('Reconhece o curso pelo que o aluno escreveu');

it('aceita o título escrito com o ordinal comum do teclado', function () use ($CURSOS) {
    // O título traz "1ª Fase"; ninguém digita "ª".
    expect(reconhecer('OAB 1a Fase Completo', $CURSOS))->toBe(11);
    expect(reconhecer('oab 1o fase', $CURSOS))->toBe(11);
});

it('aceita frase natural, não só o título cru', function () use ($CURSOS) {
    expect(reconhecer('quero falar do OAB 1a fase', $CURSOS))->toBe(11);
    expect(reconhecer('me mostra como estou em libras para o ambiente escolar', $CURSOS))->toBe(44);
});

it('aceita as palavras fora de ordem', function () use ($CURSOS) {
    expect(reconhecer('completo fase oab', $CURSOS))->toBe(11);
});

it('atravessa a flexão da palavra', function () use ($CURSOS) {
    // O aluno escreve "biologia"; o curso se chama "CIÊNCIAS BIOLÓGICAS".
    expect(reconhecer('quero ver o PND de biologia', $CURSOS))->toBe(22);
});

it('reconhece pelo código da turma', function () use ($CURSOS) {
    expect(reconhecer('turma PNDEDF01 2026A', $CURSOS))->toBe(33);
});

describe('Na dúvida, não escolhe');

it('uma palavra só nunca decide', function () use ($CURSOS) {
    expect(reconhecer('PND', $CURSOS))->toBeNull();
    expect(reconhecer('OAB', $CURSOS))->toBeNull();
});

it('empate entre cursos mantém a pergunta', function () use ($CURSOS) {
    // "PND na pratica" descreve dois cursos igualmente bem.
    expect(reconhecer('PND na pratica', $CURSOS))->toBeNull();
    expect(reconhecer('quero o PND na pratica preparatorio', $CURSOS))->toBeNull();
});

it('texto sem relação com curso nenhum não decide', function () use ($CURSOS) {
    foreach (array('bom dia tudo bem', 'como emito meu certificado', 'quanto falta pra eu terminar',
                   'me fala do curso de matematica', '') as $texto) {
        if (reconhecer($texto, $CURSOS) !== null) {
            throw new RuntimeException('escolheu curso a partir de: ' . var_export($texto, true));
        }
    }
    expect(true)->toBeTrue();
});

it('sem opções não há o que escolher', function () {
    expect(reconhecer('OAB 1a fase completo', array()))->toBeNull();
});

it('palavras curtas não pontuam sozinhas', function () {
    // "de", "na", "o" descrevem todos os cursos e não distinguem nenhum.
    $opcoes = array(
        array('inscricao_id' => 1, 'curso_titulo' => 'Curso de Alfa na prática', 'turma_nome' => 'A1'),
        array('inscricao_id' => 2, 'curso_titulo' => 'Curso de Beta na prática', 'turma_nome' => 'B1'),
    );
    expect(reconhecer('curso de na', $opcoes))->toBeNull();
});

describe('O front recebe o que precisa para perguntar');

it('a resposta ambígua carrega as opções, com id e título', function () {
    // Sem isso o JavaScript não teria como desenhar os botões — foi exatamente
    // a metade do defeito que vivia no servidor.
    $reflexo = new ReflectionMethod(new NorminhaService(), 'respostaSimples');
    $reflexo->setAccessible(true);

    $contexto = array(
        'estado' => 'ambiguo',
        'opcoes' => array(array('inscricao_id' => 7, 'curso_titulo' => 'Curso X', 'turma_nome' => 'T1')),
    );
    $r = $reflexo->invoke(new NorminhaService(), 1, array(), $contexto, 'texto', null, 'doubt', array());

    expect(isset($r['opcoes']))->toBeTrue();
    expect($r['opcoes'][0]['inscricao_id'])->toBe(7);
    expect($r['opcoes'][0]['curso_titulo'])->toBe('Curso X');
});

it('o JavaScript desenha as opções — não as descarta', function () {
    $js = (string) file_get_contents(BASE_PATH . '/assets/js/tutor-norminha.js');

    if (strpos($js, 'adicionarEscolhas') === false) {
        throw new RuntimeException('o JS não tem como desenhar as opções de curso');
    }
    if (strpos($js, 'dados.opcoes') === false) {
        throw new RuntimeException('o JS não lê `opcoes` da resposta');
    }
    // A escolha precisa virar hint, senão ela se perde na mensagem seguinte.
    if (strpos($js, "setAttribute('data-inscricao-id'") === false) {
        throw new RuntimeException('a escolha não é gravada onde hints() lê');
    }
    expect(true)->toBeTrue();
});

exit(testes_resumo());

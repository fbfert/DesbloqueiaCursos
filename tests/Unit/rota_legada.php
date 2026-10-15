<?php

/**
 * Mapa das rotas legadas para o tema (openspec/changes/tema-caderno-unico).
 *
 * Sem banco. Execução: php tests/Unit/rota_legada.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Support\RotaLegada;

$destino = function ($path, array $query = array()) {
    return RotaLegada::destino($path, $query);
};

describe('RotaLegada: páginas públicas (301)');

it('autenticação', function () use ($destino) {
    expect($destino('/login'))->toBe(array('url' => '/v2/login', 'status' => 301));
    expect($destino('/login', array('redirect' => '/v2/aluno/', 'x' => '1'))['url'])->toBe('/v2/login?redirect=%2Fv2%2Faluno%2F');
    expect($destino('/cadastro')['url'])->toBe('/v2/cadastro');
    expect($destino('/recuperar-senha')['url'])->toBe('/v2/recuperar-senha');
    expect($destino('/esqueci-minha-senha')['url'])->toBe('/v2/recuperar-senha');
    expect($destino('/recuperar-senha/redefinir', array('token' => 'abc')))->toBe(array('url' => '/v2/recuperar-senha/redefinir?token=abc', 'status' => 302));
    expect($destino('/esqueci-minha-senha/redefinir', array('token' => 'abc'))['url'])->toBe('/v2/recuperar-senha/redefinir?token=abc');
});

it('vitrine', function () use ($destino) {
    expect($destino('/cursos', array('busca' => 'nr 10', 'categoria' => 'seguranca', 'lixo' => 'x'))['url'])->toBe('/v2/catalogo?busca=nr+10&categoria=seguranca');
    expect($destino('/cursos/detalhe', array('curso_id' => '12', 'turma_id' => '3')))->toBe(array('url' => '/v2/curso?curso_id=12&turma_id=3', 'status' => 301));
    expect($destino('/categorias')['url'])->toBe('/v2/categorias');
    expect($destino('/categorias/saude-mental/cursos'))->toBe(array('url' => '/v2/catalogo/?categoria=saude-mental', 'status' => 301));
    expect($destino('/sobre')['url'])->toBe('/v2/quem-somos');
    expect($destino('/contato')['url'])->toBe('/v2/onde-estamos');
    expect($destino('/como-funciona')['url'])->toBe('/v2/como-funciona-a-sala-virtual');
});

it('certificados', function () use ($destino) {
    expect($destino('/certificados/validar', array('codigo' => 'ABC123')))->toBe(array('url' => '/v2/certificados/validar?codigo=ABC123', 'status' => 301));
    expect($destino('/certificados', array('codigo' => 'ABC123'))['url'])->toBe('/v2/certificados/validar?codigo=ABC123');
    expect($destino('/certificados/show', array('codigo' => 'ABC123'))['url'])->toBe('/v2/certificados/validar?codigo=ABC123');
    expect($destino('/certificados/versao-online', array('codigo' => 'ABC123'))['url'])->toBe('/v2/certificados/versao-online?codigo=ABC123');
});

describe('RotaLegada: checkout e área do aluno (302)');

it('checkout preserva a query inteira', function () use ($destino) {
    expect($destino('/inscricao', array('curso_id' => '1', 'turma_id' => '2')))->toBe(array('url' => '/v2/checkout/inscricao?curso_id=1&turma_id=2', 'status' => 302));
    expect($destino('/checkout/inscricao')['url'])->toBe('/v2/checkout/inscricao');
    expect($destino('/checkout/participantes', array('pedido_id' => '9'))['url'])->toBe('/v2/checkout/participantes?pedido_id=9');
    expect($destino('/checkout/resumo', array('pedido_id' => '9'))['url'])->toBe('/v2/checkout/resumo?pedido_id=9');
    expect($destino('/checkout/cupom', array('pedido_id' => '9'))['url'])->toBe('/v2/checkout/resumo?pedido_id=9');
    expect($destino('/checkout/comprovante', array('pedido_id' => '9'))['url'])->toBe('/v2/checkout/comprovante?pedido_id=9');
    expect($destino('/checkout/sucesso', array('pedido_id' => '40')))->toBe(array('url' => '/v2/checkout/sucesso?pedido_id=40', 'status' => 302));
});

it('área do aluno e conta', function () use ($destino) {
    expect($destino('/meus-cursos'))->toBe(array('url' => '/v2/aluno/', 'status' => 302));
    expect($destino('/aluno/meus-cursos')['url'])->toBe('/v2/aluno/');
    expect($destino('/pedidos')['url'])->toBe('/v2/aluno/?aba=pedidos');
    expect($destino('/minha-conta')['url'])->toBe('/v2/minha-conta');
});

it('aula pela URL legada', function () use ($destino) {
    expect($destino('/aluno/curso/5/12/30'))->toBe(array('url' => '/v2/aula/?inscricao_id=5&curso_id=12&turma_id=30', 'status' => 302));
    expect($destino('/aluno/curso/5/12/30/modulo/7')['url'])->toBe('/v2/aula/?inscricao_id=5&curso_id=12&turma_id=30&modulo_id=7');
    expect($destino('/area-curso', array('inscricao_id' => '5', 'curso_id' => '12', 'turma_id' => '30'))['url'])->toBe('/v2/aula/?inscricao_id=5&curso_id=12&turma_id=30');
    expect($destino('/aluno/cursos/modulo', array('inscricao_id' => '5', 'curso_id' => '12', 'turma_id' => '30', 'modulo_id' => '7'))['url'])->toBe('/v2/aula/?inscricao_id=5&curso_id=12&turma_id=30&modulo_id=7');
    expect($destino('/area-curso')['url'])->toBe('/v2/aluno/');
});

it('item de conteúdo e resultado de quiz pedem resolução', function () use ($destino) {
    $r = $destino('/aluno/curso/5/12/30/modulo/7/conteudo/99');
    expect($r['resolver'])->toBe('conteudo');
    expect($r['conteudo_id'])->toBe(99);
    expect($r['contexto']['modulo_id'])->toBe(7);
    expect($destino('/aluno/cursos/conteudo/item', array('id' => '99', 'inscricao_id' => '5'))['conteudo_id'])->toBe(99);
    expect($destino('/aluno/cursos/quiz/resultado', array('tentativa_id' => '8')))->toBe(array('resolver' => 'quiz_resultado', 'tentativa_id' => 8));
});

describe('RotaLegada::urlEstudo');

it('escolhe a tela do tema pelo tipo do item', function () {
    $ctx = array('inscricao_id' => 5, 'curso_id' => 12, 'turma_id' => 30, 'modulo_id' => 7);
    expect(RotaLegada::urlEstudo('quiz', $ctx, 99))->toBe('/v2/quiz?inscricao_id=5&curso_id=12&turma_id=30&modulo_id=7&conteudo_id=99');
    expect(RotaLegada::urlEstudo('avaliacao_textual', $ctx, 99))->toBe('/v2/atividade?inscricao_id=5&curso_id=12&turma_id=30&modulo_id=7&conteudo_id=99');
    expect(RotaLegada::urlEstudo('texto', $ctx, 99))->toBe('/v2/aula/?inscricao_id=5&curso_id=12&turma_id=30&modulo_id=7&conteudo_id=99');
    expect(RotaLegada::urlEstudo('quiz', array('inscricao_id' => 0), 99))->toBe('/v2/aluno/');
});

it('não mapeia rotas fora do escopo', function () use ($destino) {
    foreach (array('/admin/dashboard', '/v2/catalogo', '/aluno/cursos/conteudo/arquivo/download', '/certificados/pdf', '/comprar', '/logout') as $p) {
        expect($destino($p))->toBeNull();
    }
});

testes_resumo();

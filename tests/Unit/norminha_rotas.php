<?php

/**
 * Onde a Norminha aparece — mapa de rotas e o resgate por contexto.
 *
 * O QUE ACONTECEU (23/08/2026): com tudo ligado e o roteador reconhecendo
 * /v2/*, a Norminha não aparecia em NENHUMA página pública da V2 — o site que o
 * visitante de fato navega. A causa não estava no código: as seis falas
 * cadastradas estavam todas presas a rotas da V1, e o último recurso de
 * TutorFala::buscarAtiva() exige `rota IS NULL`. Sem fala, sem componente.
 *
 * As áreas de estudo escapavam por causa da saudação padrão da Etapa 6, o que
 * fez o problema parecer menor do que era: quem testava logado como aluno via a
 * Norminha funcionando e não percebia o site público mudo.
 *
 * O QUE ESTE TESTE PROTEGE:
 *   - o mapa rota -> contexto, incluindo /v2/* e a exclusão de admin/professor;
 *   - que todo contexto que o roteador consegue emitir seja escrevível pelo
 *     administrador — foi assim que 'avaliacao' ficou de fora da lista;
 *   - que uma fala com rota nula valha para o contexto inteiro, que é o
 *     mecanismo do qual a correção depende.
 *
 * Execução: php tests/Unit/norminha_rotas.php
 * A parte que escreve roda em transação e é revertida.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\TutorFala;
use App\Services\TutorNorminhaService;
use App\Services\TutorVirtualService;

$servico = new TutorVirtualService();
$reflexo = new ReflectionClass($servico);
$resolver = $reflexo->getMethod('resolverContexto');
$resolver->setAccessible(true);

function contexto_de($rota)
{
    global $servico, $resolver;
    $c = $resolver->invoke($servico, $rota, array());
    return empty($c) ? null : $c['contexto'];
}

describe('Mapa de rotas da V2');

it('reconhece cada área da V2', function () {
    $esperado = array(
        '/v2' => 'home',
        '/v2/' => 'home',
        '/v2/catalogo' => 'cursos',
        '/v2/categorias' => 'cursos',
        '/v2/curso/1' => 'curso',
        '/v2/checkout' => 'checkout',
        '/v2/checkout/pagamento' => 'checkout',
        '/v2/aluno' => 'area_aluno',
        '/v2/minha-conta' => 'area_aluno',
        '/v2/aula' => 'aula',
        '/v2/quiz' => 'avaliacao',
        '/v2/atividade' => 'avaliacao',
        '/v2/quem-somos' => 'institucional',
        '/v2/contato' => 'institucional',
        '/v2/politica-de-privacidade' => 'institucional',
    );

    foreach ($esperado as $rota => $contexto) {
        $obtido = contexto_de($rota);
        if ($obtido !== $contexto) {
            throw new RuntimeException("{$rota}: esperado {$contexto}, obtido " . var_export($obtido, true));
        }
    }
    expect(true)->toBeTrue();
});

it('mantém o mapa da V1 intacto', function () {
    $esperado = array(
        '/' => 'home',
        '/cursos' => 'cursos',
        '/curso/9' => 'curso',
        '/checkout' => 'checkout',
        '/aluno/cursos' => 'area_aluno',
        '/aluno/curso/3' => 'aula',
        '/area-curso' => 'area_aluno',
        '/como-funciona' => 'institucional',
    );

    foreach ($esperado as $rota => $contexto) {
        $obtido = contexto_de($rota);
        if ($obtido !== $contexto) {
            throw new RuntimeException("{$rota}: esperado {$contexto}, obtido " . var_export($obtido, true));
        }
    }
    expect(true)->toBeTrue();
});

it('nunca entra em admin nem em professor', function () {
    foreach (array('/admin', '/admin/', '/admin/tutor-norminha', '/admin/financeiro',
                   '/professor', '/professor/turmas') as $rota) {
        if (contexto_de($rota) !== null) {
            throw new RuntimeException("a Norminha entrou em {$rota}");
        }
    }
    expect(true)->toBeTrue();
});

describe('Contextos autoráveis');

it('todo contexto que o roteador emite pode ser escrito pelo administrador', function () {
    $rotas = array('/', '/v2', '/v2/catalogo', '/v2/curso/1', '/v2/checkout', '/v2/aluno',
                   '/v2/aula', '/v2/quiz', '/v2/atividade', '/v2/quem-somos', '/cursos',
                   '/aluno/cursos', '/aluno/curso/3', '/como-funciona');

    $permitidos = (new TutorNorminhaService())->contextosPermitidos();
    $emitidos = array();
    foreach ($rotas as $r) {
        $c = contexto_de($r);
        if ($c !== null && $c !== 'publico') {
            $emitidos[$c] = true;
        }
    }

    $faltando = array_diff(array_keys($emitidos), $permitidos);
    if ($faltando) {
        throw new RuntimeException('o roteador emite contexto que o admin não pode escrever: '
            . implode(', ', $faltando));
    }
    expect(count($emitidos) > 0)->toBeTrue();
});

describe('Fala com rota nula vale para o contexto inteiro');

$pdo = testes_conectar_banco();
$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($banco === 'desbloqueiacursos') {
    fwrite(STDERR, "\nRECUSADO: esta parte escreve.\n");
    exit(1);
}

$pdo->beginTransaction();
$pdo->exec('DELETE FROM tutor_falas WHERE contexto = "institucional"');
$pdo->exec('INSERT INTO tutor_falas (titulo, contexto, rota, texto, estado_avatar, ativo, criado_em)
            VALUES ("Geral", "institucional", NULL, "TEXTO-GERAL", "speaking", 1, NOW())');

it('atende uma rota que não tem fala própria', function () {
    $fala = (new TutorFala())->buscarAtiva(array('contexto' => 'institucional', 'rota' => '/v2/quem-somos'));
    expect($fala !== null)->toBeTrue();
    expect($fala['texto'])->toBe('TEXTO-GERAL');
});

it('cede a vez para a fala da rota exata', function () use ($pdo) {
    $pdo->exec('INSERT INTO tutor_falas (titulo, contexto, rota, texto, estado_avatar, ativo, criado_em)
                VALUES ("Especifica", "institucional", "/v2/contato", "TEXTO-DA-ROTA", "speaking", 1, NOW())');

    $naRota = (new TutorFala())->buscarAtiva(array('contexto' => 'institucional', 'rota' => '/v2/contato'));
    expect($naRota['texto'])->toBe('TEXTO-DA-ROTA');

    $foraDaRota = (new TutorFala())->buscarAtiva(array('contexto' => 'institucional', 'rota' => '/v2/sobre'));
    expect($foraDaRota['texto'])->toBe('TEXTO-GERAL');
});

it('não atravessa a fronteira do contexto', function () {
    $outro = (new TutorFala())->buscarAtiva(array('contexto' => 'checkout', 'rota' => '/v2/quem-somos'));
    if ($outro !== null && $outro['texto'] === 'TEXTO-GERAL') {
        throw new RuntimeException('fala de institucional vazou para checkout');
    }
    expect(true)->toBeTrue();
});

$pdo->rollBack();

exit(testes_resumo());

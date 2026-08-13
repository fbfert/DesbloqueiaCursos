<?php

/**
 * Testes do sorteio de questoes por blocos (dominio puro, sem banco).
 * Execução: php tests/Unit/quiz_sorteio.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\Quiz\QuizRandomizerSemente;
use App\Services\Quiz\QuizSorteioService;

/**
 * Gera um banco de questoes com dificuldades controladas.
 */
function bancoDeQuestoes($idInicial, $facil, $media, $dificil, $tema = 'Tema')
{
    $perguntas = array();
    $id = $idInicial;
    foreach (array('facil' => $facil, 'media' => $media, 'dificil' => $dificil) as $dificuldade => $quantidade) {
        for ($i = 0; $i < $quantidade; $i++) {
            $perguntas[] = array(
                'id'          => $id++,
                'enunciado'   => 'Questão ' . $id,
                'tipo'        => 'multipla_escolha',
                'dificuldade' => $dificuldade,
                'tema'        => $tema,
                'peso'        => 1,
                'obrigatoria' => 1,
            );
        }
    }
    return $perguntas;
}

function blocoPnd()
{
    return array(
        array(
            'id' => 1, 'codigo' => 'FGD', 'titulo' => 'Formação Geral Docente',
            'tipo_questao' => 'multipla_escolha', 'quantidade_sortear' => 30,
            'distribuicao_dificuldade' => array('facil' => 20, 'media' => 60, 'dificil' => 20),
            'conta_para_percentual' => 1, 'obrigatorio_para_envio' => 1, 'ordem' => 1,
        ),
        array(
            'id' => 2, 'codigo' => 'PEDAGOGIA', 'titulo' => 'Componente Específico - Pedagogia',
            'tipo_questao' => 'multipla_escolha', 'quantidade_sortear' => 50,
            'distribuicao_dificuldade' => array('facil' => 20, 'media' => 60, 'dificil' => 20),
            'conta_para_percentual' => 1, 'obrigatorio_para_envio' => 1, 'ordem' => 2,
        ),
        array(
            'id' => 3, 'codigo' => 'DISCURSIVA', 'titulo' => 'Questão discursiva',
            'tipo_questao' => 'discursiva', 'quantidade_sortear' => 1,
            'distribuicao_dificuldade' => null,
            'conta_para_percentual' => 0, 'obrigatorio_para_envio' => 1, 'ordem' => 3,
        ),
    );
}

function poolsPnd()
{
    $discursivas = array();
    for ($i = 0; $i < 5; $i++) {
        $discursivas[] = array(
            'id' => 900 + $i, 'enunciado' => 'Discursiva ' . $i, 'tipo' => 'discursiva',
            'dificuldade' => 'media', 'tema' => 'Prática docente', 'peso' => 1, 'obrigatoria' => 1,
        );
    }

    return array(
        // 90 de Formação Geral Docente: 18 fáceis, 54 médias, 18 difíceis
        1 => bancoDeQuestoes(1, 18, 54, 18, 'FGD'),
        // 150 de Pedagogia: 30 fáceis, 90 médias, 30 difíceis
        2 => bancoDeQuestoes(200, 30, 90, 30, 'Pedagogia'),
        3 => $discursivas,
    );
}

function idsDoSorteio(array $resultado)
{
    $ids = array();
    foreach ($resultado['perguntas'] as $item) {
        $ids[] = (int) $item['pergunta']['id'];
    }
    return $ids;
}

function contarPorBloco(array $resultado)
{
    $contagem = array();
    foreach ($resultado['perguntas'] as $item) {
        $codigo = $item['bloco']['codigo'];
        $contagem[$codigo] = (isset($contagem[$codigo]) ? $contagem[$codigo] : 0) + 1;
    }
    return $contagem;
}

echo "=== Testes do sorteio de questões por blocos ===\n";

// ----------------------------------------------------------------
describe('Distribuição de cotas por dificuldade (arredondamento determinista)');
// ----------------------------------------------------------------

it('30 questões com 20/60/20 viram 6 fáceis, 18 médias e 6 difíceis', function () {
    $servico = new QuizSorteioService(new QuizRandomizerSemente(1));
    $cotas = $servico->distribuirCotas(30, array('facil' => 20, 'media' => 60, 'dificil' => 20));
    expect($cotas['facil'])->toBe(6);
    expect($cotas['media'])->toBe(18);
    expect($cotas['dificil'])->toBe(6);
});

it('50 questões com 20/60/20 viram 10 fáceis, 30 médias e 10 difíceis', function () {
    $servico = new QuizSorteioService(new QuizRandomizerSemente(1));
    $cotas = $servico->distribuirCotas(50, array('facil' => 20, 'media' => 60, 'dificil' => 20));
    expect($cotas['facil'])->toBe(10);
    expect($cotas['media'])->toBe(30);
    expect($cotas['dificil'])->toBe(10);
});

it('nunca perde nem cria vagas no arredondamento', function () {
    $servico = new QuizSorteioService(new QuizRandomizerSemente(7));
    foreach (array(1, 7, 13, 29, 31, 47, 81, 100) as $total) {
        $cotas = $servico->distribuirCotas($total, array('facil' => 20, 'media' => 60, 'dificil' => 20));
        expect(array_sum($cotas))->toBe($total);
    }
});

it('arredondamento é determinista entre chamadas', function () {
    $servico = new QuizSorteioService(new QuizRandomizerSemente(3));
    $a = $servico->distribuirCotas(7, array('facil' => 33.34, 'media' => 33.33, 'dificil' => 33.33));
    $b = $servico->distribuirCotas(7, array('facil' => 33.34, 'media' => 33.33, 'dificil' => 33.33));
    expect($a)->toEqual($b);
    expect(array_sum($a))->toBe(7);
});

it('sem distribuição configurada, todas as vagas ficam em uma faixa única', function () {
    $servico = new QuizSorteioService(new QuizRandomizerSemente(1));
    expect($servico->normalizarDistribuicao(null))->toBeNull();
    expect($servico->normalizarDistribuicao('{}'))->toBeNull();
    expect($servico->normalizarDistribuicao('{"facil":20,"media":60,"dificil":20}'))->toEqual(
        array('facil' => 20.0, 'media' => 60.0, 'dificil' => 20.0)
    );
});

// ----------------------------------------------------------------
describe('Composição do simulado PND (30 + 50 + 1)');
// ----------------------------------------------------------------

it('sorteia exatamente a quantidade configurada em cada bloco', function () {
    $servico   = new QuizSorteioService(new QuizRandomizerSemente(42));
    $resultado = $servico->sortear(blocoPnd(), poolsPnd());

    $contagem = contarPorBloco($resultado);
    expect($contagem['FGD'])->toBe(30);
    expect($contagem['PEDAGOGIA'])->toBe(50);
    expect($contagem['DISCURSIVA'])->toBe(1);
    expect(count($resultado['perguntas']))->toBe(81);
    expect($resultado['completo'])->toBeTrue();
    expect($resultado['com_repeticao'])->toBeFalse();
});

it('as quantidades vêm da configuração, não de números fixos no serviço', function () {
    $blocos = blocoPnd();
    $blocos[0]['quantidade_sortear'] = 12;   // outra composição
    $blocos[1]['quantidade_sortear'] = 7;
    $blocos[2]['quantidade_sortear'] = 2;

    $servico   = new QuizSorteioService(new QuizRandomizerSemente(9));
    $resultado = $servico->sortear($blocos, poolsPnd());
    $contagem  = contarPorBloco($resultado);

    expect($contagem['FGD'])->toBe(12);
    expect($contagem['PEDAGOGIA'])->toBe(7);
    expect($contagem['DISCURSIVA'])->toBe(2);
});

it('respeita a distribuição de dificuldade dentro de cada bloco', function () {
    $servico   = new QuizSorteioService(new QuizRandomizerSemente(2024));
    $resultado = $servico->sortear(blocoPnd(), poolsPnd());

    $porBloco = array();
    foreach ($resultado['perguntas'] as $item) {
        $codigo = $item['bloco']['codigo'];
        $dif    = $item['pergunta']['dificuldade'];
        if (!isset($porBloco[$codigo])) {
            $porBloco[$codigo] = array('facil' => 0, 'media' => 0, 'dificil' => 0);
        }
        $porBloco[$codigo][$dif]++;
    }

    expect($porBloco['FGD'])->toEqual(array('facil' => 6, 'media' => 18, 'dificil' => 6));
    expect($porBloco['PEDAGOGIA'])->toEqual(array('facil' => 10, 'media' => 30, 'dificil' => 10));
});

it('a ordem de apresentação é sequencial e sem buracos', function () {
    $servico   = new QuizSorteioService(new QuizRandomizerSemente(11));
    $resultado = $servico->sortear(blocoPnd(), poolsPnd());

    $ordens = array();
    foreach ($resultado['perguntas'] as $item) {
        $ordens[] = (int) $item['ordem_apresentacao'];
    }
    expect($ordens)->toEqual(range(1, 81));
});

it('não repete a mesma questão dentro de uma tentativa', function () {
    $servico   = new QuizSorteioService(new QuizRandomizerSemente(5));
    $ids       = idsDoSorteio($servico->sortear(blocoPnd(), poolsPnd()));
    expect(count(array_unique($ids)))->toBe(count($ids));
});

// ----------------------------------------------------------------
describe('Aleatoriedade determinista por semente');
// ----------------------------------------------------------------

it('a mesma semente produz o mesmo sorteio', function () {
    $a = (new QuizSorteioService(new QuizRandomizerSemente(77)))->sortear(blocoPnd(), poolsPnd());
    $b = (new QuizSorteioService(new QuizRandomizerSemente(77)))->sortear(blocoPnd(), poolsPnd());
    expect(idsDoSorteio($a))->toEqual(idsDoSorteio($b));
});

it('sementes diferentes produzem sorteios diferentes', function () {
    $a = (new QuizSorteioService(new QuizRandomizerSemente(1)))->sortear(blocoPnd(), poolsPnd());
    $b = (new QuizSorteioService(new QuizRandomizerSemente(2)))->sortear(blocoPnd(), poolsPnd());
    if (idsDoSorteio($a) === idsDoSorteio($b)) {
        throw new RuntimeException('Sementes diferentes geraram o mesmo conjunto.');
    }
});

// ----------------------------------------------------------------
describe('Três tentativas sem repetição quando o banco comporta');
// ----------------------------------------------------------------

it('as três tentativas do PND não repetem questões objetivas', function () {
    $servico = new QuizSorteioService(new QuizRandomizerSemente(2026));
    $pools   = poolsPnd();
    $usados  = array();
    $todos   = array();

    for ($tentativa = 1; $tentativa <= 3; $tentativa++) {
        $resultado = $servico->sortear(blocoPnd(), $pools, $usados);
        expect($resultado['com_repeticao'])->toBeFalse();

        foreach ($resultado['perguntas'] as $item) {
            $id = (int) $item['pergunta']['id'];
            if ($item['bloco']['codigo'] === 'DISCURSIVA') {
                continue;
            }
            if (in_array($id, $todos, true)) {
                throw new RuntimeException("Questão {$id} repetida na tentativa {$tentativa}.");
            }
            $todos[] = $id;
        }

        foreach ($resultado['perguntas'] as $item) {
            $usados[] = (int) $item['pergunta']['id'];
        }
    }

    // 3 x 80 objetivas inéditas
    expect(count($todos))->toBe(240);
});

// ----------------------------------------------------------------
describe('Fallback auditável quando faltam questões inéditas');
// ----------------------------------------------------------------

it('completa com itens já usados e marca a ocorrência', function () {
    // Banco pequeno: 10 questões para sortear 8 duas vezes
    $blocos = array(array(
        'id' => 1, 'codigo' => 'FGD', 'titulo' => 'Bloco pequeno',
        'tipo_questao' => 'multipla_escolha', 'quantidade_sortear' => 8,
        'distribuicao_dificuldade' => null,
        'conta_para_percentual' => 1, 'obrigatorio_para_envio' => 1, 'ordem' => 1,
    ));
    $pools = array(1 => bancoDeQuestoes(1, 3, 4, 3));

    $servico = new QuizSorteioService(new QuizRandomizerSemente(31));

    $primeira = $servico->sortear($blocos, $pools);
    expect($primeira['com_repeticao'])->toBeFalse();
    expect(count($primeira['perguntas']))->toBe(8);

    $usados = idsDoSorteio($primeira);
    $segunda = $servico->sortear($blocos, $pools, $usados);

    expect(count($segunda['perguntas']))->toBe(8);
    expect($segunda['com_repeticao'])->toBeTrue();

    // 10 no banco, 8 já usados: 2 inéditos + 6 reaproveitados
    $reutilizadas = 0;
    foreach ($segunda['perguntas'] as $item) {
        if (!empty($item['reutilizada'])) {
            $reutilizadas++;
        }
    }
    expect($reutilizadas)->toBe(6);

    $tipos = array();
    foreach ($segunda['auditoria'] as $ocorrencia) {
        $tipos[] = $ocorrencia['tipo'];
    }
    expect(in_array('reutilizacao_por_falta_de_ineditas', $tipos, true))->toBeTrue();
});

it('registra banco_insuficiente quando nem com repetição dá para completar', function () {
    $blocos = array(array(
        'id' => 1, 'codigo' => 'FGD', 'titulo' => 'Bloco insuficiente',
        'tipo_questao' => 'multipla_escolha', 'quantidade_sortear' => 30,
        'distribuicao_dificuldade' => array('facil' => 20, 'media' => 60, 'dificil' => 20),
        'conta_para_percentual' => 1, 'obrigatorio_para_envio' => 1, 'ordem' => 1,
    ));
    $pools = array(1 => bancoDeQuestoes(1, 2, 3, 2)); // apenas 7 questões

    $servico   = new QuizSorteioService(new QuizRandomizerSemente(13));
    $resultado = $servico->sortear($blocos, $pools);

    expect(count($resultado['perguntas']))->toBe(7);
    expect($resultado['completo'])->toBeFalse();

    $tipos = array();
    foreach ($resultado['auditoria'] as $ocorrencia) {
        $tipos[] = $ocorrencia['tipo'];
    }
    expect(in_array('banco_insuficiente', $tipos, true))->toBeTrue();
});

it('ajusta a distribuição quando falta uma faixa de dificuldade', function () {
    $blocos = array(array(
        'id' => 1, 'codigo' => 'FGD', 'titulo' => 'Sem difíceis',
        'tipo_questao' => 'multipla_escolha', 'quantidade_sortear' => 10,
        'distribuicao_dificuldade' => array('facil' => 20, 'media' => 60, 'dificil' => 20),
        'conta_para_percentual' => 1, 'obrigatorio_para_envio' => 1, 'ordem' => 1,
    ));
    // Nenhuma questão difícil no banco
    $pools = array(1 => bancoDeQuestoes(1, 5, 10, 0));

    $servico   = new QuizSorteioService(new QuizRandomizerSemente(17));
    $resultado = $servico->sortear($blocos, $pools);

    // A prova continua com 10 questões, completadas por outras dificuldades
    expect(count($resultado['perguntas']))->toBe(10);

    $tipos = array();
    foreach ($resultado['auditoria'] as $ocorrencia) {
        $tipos[] = $ocorrencia['tipo'];
    }
    expect(in_array('distribuicao_ajustada', $tipos, true))->toBeTrue();
});

exit(testes_resumo());

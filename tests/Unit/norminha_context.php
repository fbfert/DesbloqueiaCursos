<?php

/**
 * NorminhaContextService — contexto acadêmico validado (Etapa 2).
 *
 * O que este teste protege: que a Norminha nunca fale sobre um curso que o
 * aluno não cursa. Todo id vindo do browser é palpite; o teste insiste em que
 * palpite inválido seja DESCARTADO, e não corrigido ou usado assim mesmo.
 *
 * O service é somente leitura, então o teste não abre transação: não há o que
 * desfazer. As fixtures são descobertas no banco de desenvolvimento, para não
 * depender de ids fixos que mudam a cada restauração do dump.
 *
 * Execução: php tests/Unit/norminha_context.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\Inscricao;
use App\Services\NorminhaContextService;

$pdo = testes_conectar_banco();

$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nBanco: {$banco}\n";

$servico = new NorminhaContextService();
$inscricaoModel = new Inscricao();

// -------------------------------------------------------------------
// Fixtures descobertas
// -------------------------------------------------------------------

$alunoUmCurso = null;
$alunoVariosCursos = null;

$candidatos = $pdo->query(
    'SELECT usuario_id FROM inscricoes WHERE deleted_at IS NULL
     GROUP BY usuario_id ORDER BY COUNT(*) DESC LIMIT 80'
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($candidatos as $usuarioId) {
    $ativas = $inscricaoModel->forUsuarioAprovadas($usuarioId);
    if (count($ativas) === 1 && $alunoUmCurso === null) {
        $alunoUmCurso = array('usuario_id' => (int) $usuarioId, 'inscricao' => $ativas[0]);
    }
    if (count($ativas) > 1 && $alunoVariosCursos === null) {
        $alunoVariosCursos = array('usuario_id' => (int) $usuarioId, 'inscricoes' => $ativas);
    }
    if ($alunoUmCurso && $alunoVariosCursos) {
        break;
    }
}

if (!$alunoUmCurso || !$alunoVariosCursos) {
    fwrite(STDERR, "ERRO: banco sem dados suficientes (preciso de um aluno com 1 matricula e outro com varias).\n");
    exit(1);
}

$cursoDoAluno = (int) $alunoUmCurso['inscricao']['curso_evento_id'];

$itemValido = $pdo->query(
    'SELECT i.id, i.titulo, i.tipo, m.id AS modulo_id
     FROM conteudo_itens i
     INNER JOIN conteudo_modulos m ON m.id = i.modulo_id AND m.status = "publicado" AND m.deleted_at IS NULL
     WHERE i.curso_evento_id = ' . $cursoDoAluno . '
       AND i.status = "publicado" AND i.deleted_at IS NULL AND i.tipo <> "etiqueta"
     LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);

$itemDeOutroCurso = $pdo->query(
    'SELECT id, curso_evento_id FROM conteudo_itens
     WHERE curso_evento_id <> ' . $cursoDoAluno . '
       AND status = "publicado" AND deleted_at IS NULL LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);

// Aluno sem nenhuma matrícula aprovada.
$alunoSemCurso = 0;
foreach ($pdo->query('SELECT id FROM usuarios ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_COLUMN) as $uid) {
    if (!$inscricaoModel->forUsuarioAprovadas($uid)) {
        $alunoSemCurso = (int) $uid;
        break;
    }
}

printf("Fixtures: aluno_um=%d  aluno_varios=%d (%d)  curso=%d  item=%s  aluno_sem_curso=%s\n",
    $alunoUmCurso['usuario_id'], $alunoVariosCursos['usuario_id'],
    count($alunoVariosCursos['inscricoes']), $cursoDoAluno,
    $itemValido ? $itemValido['id'] : 'nenhum', $alunoSemCurso ?: 'nenhum');

function contarQueries(PDO $pdo, callable $fn)
{
    $antes = (int) $pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch()['Value'];
    $fn();
    $depois = (int) $pdo->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch()['Value'];

    // Desconta apenas o segundo SHOW STATUS: o primeiro ja esta refletido em
    // $antes. Calibrado contra a medicao da auditoria, que contou 32 queries
    // em carregarAluno() — se esta conta estivesse errada, o numero publicado
    // no relatorio da etapa estaria errado junto.
    return $depois - $antes - 1;
}

// -------------------------------------------------------------------

describe('Contexto válido com inscrição única e sem palpite');

it('resolve a matrícula sozinha, sem pedir desambiguação', function () use ($servico, $alunoUmCurso) {
    $c = $servico->resolver($alunoUmCurso['usuario_id']);
    expect($c['estado'])->toBe('ok');
    expect($c['inscricao_id'])->toBe((int) $alunoUmCurso['inscricao']['id']);
    expect($c['flags']['tem_inscricao_ativa'])->toBeTrue();
    expect($c['flags']['tem_item_atual'])->toBeFalse();
    expect($c['contexto'])->toBe('area_aluno');
});

it('traz título do curso e progresso da coluna materializada', function () use ($servico, $alunoUmCurso) {
    $c = $servico->resolver($alunoUmCurso['usuario_id']);
    expect($c['curso_titulo'])->notToBeNull();
    // C1: o progresso vem de inscricoes.percentual_progresso, o mesmo numero
    // que a tela do aluno exibe — nao de resumoAluno(), que devolve 0% sempre.
    expect((float) $c['percentual_progresso'])->toEqual((float) $alunoUmCurso['inscricao']['percentual_progresso']);
});

describe('Contexto de aula');

if ($itemValido) {
    it('resolve item e módulo a partir do palpite', function () use ($servico, $alunoUmCurso, $itemValido) {
        $c = $servico->resolver($alunoUmCurso['usuario_id'], array('item_id' => (int) $itemValido['id']));
        expect($c['estado'])->toBe('ok');
        expect($c['item_atual']['id'])->toBe((int) $itemValido['id']);
        expect($c['item_atual']['titulo'])->toBe((string) $itemValido['titulo']);
        expect($c['modulo_atual']['id'])->toBe((int) $itemValido['modulo_id']);
        expect($c['flags']['tem_item_atual'])->toBeTrue();
        expect($c['contexto'])->toBe('aula');
    });

    it('o tipo do item manda sobre a rota', function () use ($servico, $alunoUmCurso, $itemValido) {
        // rota diz "area do aluno", mas ha item de aula: vence o item
        $c = $servico->resolver($alunoUmCurso['usuario_id'], array(
            'item_id' => (int) $itemValido['id'], 'rota' => '/v2/aluno',
        ));
        expect($c['contexto'])->toBe('aula');
    });
}

describe('Palpite inválido é DESCARTADO, nunca forçado');

it('inscrição de outro aluno não é aceita', function () use ($servico, $alunoUmCurso, $alunoVariosCursos) {
    $alheia = (int) $alunoVariosCursos['inscricoes'][0]['id'];
    $c = $servico->resolver($alunoUmCurso['usuario_id'], array('inscricao_id' => $alheia));

    // cai na propria matricula, nao na alheia
    expect($c['inscricao_id'])->toBe((int) $alunoUmCurso['inscricao']['id']);
    expect($c['inscricao_id'] === $alheia)->toBeFalse();

    $motivos = array_column($c['hints_descartados'], 'campo');
    expect(in_array('inscricao_id', $motivos, true))->toBeTrue();
});

it('não revela se a inscrição alheia existe', function () use ($servico, $alunoUmCurso, $alunoVariosCursos) {
    $alheia = (int) $alunoVariosCursos['inscricoes'][0]['id'];
    $existente = $servico->resolver($alunoUmCurso['usuario_id'], array('inscricao_id' => $alheia));
    $inexistente = $servico->resolver($alunoUmCurso['usuario_id'], array('inscricao_id' => 999999999));

    // mesmo motivo nos dois casos: "nao autorizada", nunca "nao encontrada"
    expect($existente['hints_descartados'][0]['motivo'])->toBe('nao_autorizada');
    expect($inexistente['hints_descartados'][0]['motivo'])->toBe('nao_autorizada');
});

if ($itemDeOutroCurso) {
    it('item de outro curso não vira aula atual', function () use ($servico, $alunoUmCurso, $itemDeOutroCurso) {
        $c = $servico->resolver($alunoUmCurso['usuario_id'], array('item_id' => (int) $itemDeOutroCurso['id']));
        expect($c['item_atual'])->toBeNull();
        expect($c['flags']['tem_item_atual'])->toBeFalse();

        $campos = array_column($c['hints_descartados'], 'campo');
        expect(in_array('item_id', $campos, true))->toBeTrue();
    });
}

it('curso sem matrícula é descartado', function () use ($servico, $alunoUmCurso) {
    $c = $servico->resolver($alunoUmCurso['usuario_id'], array('curso_id' => 999999999));
    $campos = array_column($c['hints_descartados'], 'campo');
    expect(in_array('curso_id', $campos, true))->toBeTrue();
});

it('ids absurdos não quebram nem viram consulta', function () use ($servico, $alunoUmCurso) {
    foreach (array(-1, 0, 'abc', "1 OR 1=1", null, '') as $lixo) {
        $c = $servico->resolver($alunoUmCurso['usuario_id'], array('item_id' => $lixo, 'inscricao_id' => $lixo));
        expect($c['estado'])->toBe('ok');
        expect($c['item_atual'])->toBeNull();
    }
});

describe('Aluno com várias matrículas e nenhum palpite');

it('devolve estado ambíguo em vez de escolher sozinho', function () use ($servico, $alunoVariosCursos) {
    $c = $servico->resolver($alunoVariosCursos['usuario_id']);
    expect($c['estado'])->toBe('ambiguo');
    expect($c['inscricao_id'])->toBeNull();
    expect($c['flags']['tem_inscricao_ativa'])->toBeTrue();
    expect(count($c['opcoes']))->toBe(count($alunoVariosCursos['inscricoes']));
});

it('as opções não carregam progresso nem dado pessoal', function () use ($servico, $alunoVariosCursos) {
    $c = $servico->resolver($alunoVariosCursos['usuario_id']);
    $chaves = array_keys($c['opcoes'][0]);
    sort($chaves);
    expect($chaves)->toEqual(array('curso_titulo', 'inscricao_id', 'turma_nome'));
});

it('um palpite válido resolve a ambiguidade', function () use ($servico, $alunoVariosCursos) {
    $escolhida = (int) $alunoVariosCursos['inscricoes'][1]['id'];
    $c = $servico->resolver($alunoVariosCursos['usuario_id'], array('inscricao_id' => $escolhida));
    expect($c['estado'])->toBe('ok');
    expect($c['inscricao_id'])->toBe($escolhida);
});

describe('Aluno sem matrícula');

if ($alunoSemCurso) {
    it('devolve sem_inscricao, sem vazar nada', function () use ($servico, $alunoSemCurso) {
        $c = $servico->resolver($alunoSemCurso);
        expect($c['estado'])->toBe('sem_inscricao');
        expect($c['inscricao_id'])->toBeNull();
        expect($c['curso_titulo'])->toBeNull();
        expect($c['flags']['tem_inscricao_ativa'])->toBeFalse();
    });
}

it('usuário inválido não é tratado como aluno', function () use ($servico) {
    expect($servico->resolver(0)['estado'])->toBe('sem_inscricao');
    expect($servico->resolver(-5)['estado'])->toBe('sem_inscricao');
});

describe('Curso concluído');

it('marca a conclusão pelo status da inscrição', function () use ($servico, $pdo, $inscricaoModel) {
    $achou = false;
    foreach ($pdo->query('SELECT usuario_id, id FROM inscricoes
                          WHERE status IN ("concluida","concluida_sem_certificado","certificado_emitido")
                            AND deleted_at IS NULL LIMIT 40') as $linha) {
        foreach ($inscricaoModel->forUsuarioAprovadas($linha['usuario_id']) as $ativa) {
            if ((int) $ativa['id'] !== (int) $linha['id']) {
                continue;
            }
            $c = $servico->resolver((int) $linha['usuario_id'], array('inscricao_id' => (int) $linha['id']));
            expect($c['flags']['curso_concluido'])->toBeTrue();
            $achou = true;
            break 2;
        }
    }
    if (!$achou) {
        echo "      (nenhuma inscricao concluida ativa no banco — caso nao exercitado)\n";
    }
});

describe('Contexto de avaliação');

it('item que vale nota liga o assessment_context', function () use ($servico, $pdo, $inscricaoModel) {
    $itens = $pdo->query(
        'SELECT i.id, i.tipo, i.curso_evento_id FROM conteudo_itens i
         INNER JOIN conteudo_modulos m ON m.id = i.modulo_id AND m.status = "publicado" AND m.deleted_at IS NULL
         WHERE i.tipo IN ("quiz","avaliacao_textual") AND i.status = "publicado" AND i.deleted_at IS NULL LIMIT 10'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($itens as $item) {
        $alunos = $pdo->query('SELECT usuario_id FROM inscricoes
                               WHERE curso_evento_id = ' . (int) $item['curso_evento_id'] . '
                                 AND deleted_at IS NULL LIMIT 20')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($alunos as $uid) {
            foreach ($inscricaoModel->forUsuarioAprovadas($uid) as $ins) {
                if ((int) $ins['curso_evento_id'] !== (int) $item['curso_evento_id']) {
                    continue;
                }
                $c = $servico->resolver((int) $uid, array(
                    'inscricao_id' => (int) $ins['id'], 'item_id' => (int) $item['id'],
                ));
                expect($c['contexto'])->toBe('avaliacao');
                expect($c['assessment_context']['em_avaliacao'])->toBeTrue();
                expect($c['assessment_context']['tipo'])->toBe((string) $item['tipo']);
                return;
            }
        }
    }
    echo "      (nenhum par avaliacao+aluno no banco — caso nao exercitado)\n";
});

describe('Privacidade — o que o modelo pode ver');

it('o DTO não carrega CPF, e-mail, telefone nem nome do pagador', function () use ($servico, $alunoUmCurso, $itemValido) {
    $c = $servico->resolver($alunoUmCurso['usuario_id'], array(
        'item_id' => $itemValido ? (int) $itemValido['id'] : null,
    ));
    $serializado = strtolower(json_encode($c, JSON_UNESCAPED_UNICODE));

    foreach (array('cpf', 'email', 'telefone', 'pagador', 'senha', 'certificado_codigo', 'pedido_codigo') as $proibido) {
        if (strpos($serializado, $proibido) !== false) {
            throw new RuntimeException("DTO contem campo proibido: {$proibido}");
        }
    }
    expect(true)->toBeTrue();
});

it('paraModelo() remove usuario_id e diagnóstico interno', function () use ($servico, $alunoUmCurso) {
    $c = $servico->resolver($alunoUmCurso['usuario_id'], array('inscricao_id' => 999999999));
    expect(isset($c['usuario_id']))->toBeTrue();

    $enviado = $servico->paraModelo($c);
    expect(isset($enviado['usuario_id']))->toBeFalse();
    expect(isset($enviado['hints_descartados']))->toBeFalse();
    expect(isset($enviado['inscricao_id']))->toBeTrue();
});

describe('Custo por chamada (decisão do item 7 da auditoria)');

it('sem palpite de conteúdo: 1 query', function () use ($pdo, $servico, $alunoUmCurso) {
    $n = contarQueries($pdo, function () use ($servico, $alunoUmCurso) {
        $servico->resolver($alunoUmCurso['usuario_id']);
    });
    echo "      queries: {$n}\n";
    expect($n)->toBeLessThanOrEqual(1);
});

if ($itemValido) {
    it('com palpite de item: 2 queries', function () use ($pdo, $servico, $alunoUmCurso, $itemValido) {
        $n = contarQueries($pdo, function () use ($servico, $alunoUmCurso, $itemValido) {
            $servico->resolver($alunoUmCurso['usuario_id'], array('item_id' => (int) $itemValido['id']));
        });
        echo "      queries: {$n}\n";
        expect($n)->toBeLessThanOrEqual(2);
    });
}

it('muito mais barato que carregarAluno(), medido em 32 queries', function () use ($pdo, $servico, $alunoUmCurso, $itemValido) {
    $norminha = contarQueries($pdo, function () use ($servico, $alunoUmCurso, $itemValido) {
        $servico->resolver($alunoUmCurso['usuario_id'], array('item_id' => $itemValido ? (int) $itemValido['id'] : null));
    });
    $area = contarQueries($pdo, function () use ($alunoUmCurso) {
        (new App\Services\AreaCursoService())->carregarAluno(
            $alunoUmCurso['usuario_id'], (int) $alunoUmCurso['inscricao']['id']
        );
    });
    echo "      contexto: {$norminha} queries | carregarAluno(): {$area} queries\n";
    expect($norminha < $area)->toBeTrue();
});

describe('Somente leitura');

it('nenhuma chamada escreve no banco', function () use ($pdo, $servico, $alunoUmCurso, $itemValido) {
    $antes = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN
        ('Com_insert','Com_update','Com_delete','Com_replace')")->fetchAll(PDO::FETCH_KEY_PAIR);

    $servico->resolver($alunoUmCurso['usuario_id']);
    $servico->resolver($alunoUmCurso['usuario_id'], array('item_id' => $itemValido ? (int) $itemValido['id'] : 1));
    $servico->resolver($alunoUmCurso['usuario_id'], array('inscricao_id' => 999999999));

    $depois = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN
        ('Com_insert','Com_update','Com_delete','Com_replace')")->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($depois as $chave => $valor) {
        if ((int) $valor !== (int) $antes[$chave]) {
            throw new RuntimeException("ESCRITA detectada: {$chave} passou de {$antes[$chave]} para {$valor}");
        }
    }
    expect(true)->toBeTrue();
});

exit(testes_resumo());

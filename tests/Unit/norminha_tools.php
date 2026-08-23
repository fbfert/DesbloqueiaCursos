<?php

/**
 * NorminhaToolsService — funções de leitura da Onda 0 (Etapa 3).
 *
 * Três coisas que este teste existe para impedir:
 *
 * 1. Que uma tool ESCREVA. Nenhuma delas pode alterar nota, progresso,
 *    matrícula ou certificado. Verificado por contador de escritas do MySQL e
 *    por varredura do código-fonte atrás de recalcularInscricao().
 *
 * 2. Que a retomada MINTA. O campo `origem` autoriza o texto: só se diz "você
 *    parou aqui" quando há registro real de acesso. Os três casos são
 *    exercitados contra dados reais.
 *
 * 3. Que uma tool devolva dado de outro aluno, ou payload sem teto.
 *
 * Somente leitura: não abre transação, porque não há o que desfazer.
 *
 * Execução: php tests/Unit/norminha_tools.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\Inscricao;
use App\Services\NorminhaToolsService;

$pdo = testes_conectar_banco();
echo "\nBanco: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";

$tools = new NorminhaToolsService();
$inscricaoModel = new Inscricao();

// -------------------------------------------------------------------
// Fixtures descobertas no banco
// -------------------------------------------------------------------

$comUltimoAcesso = null;   // caso 1: item acessado e não concluído
$semProgresso = null;      // caso 2: nada acessado, mas há itens
$tudoConcluido = null;     // caso 3
$aptoCert = null;
$naoAptoCert = null;
$alunoUnico = null;

$linhas = $pdo->query(
    'SELECT p.aluno_id, p.inscricao_id
     FROM conteudo_progresso_aluno p
     WHERE p.deleted_at IS NULL AND p.status <> "concluido" AND p.ultimo_acesso_em IS NOT NULL
     GROUP BY p.aluno_id, p.inscricao_id LIMIT 30'
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($linhas as $l) {
    foreach ($inscricaoModel->forUsuarioAprovadas($l['aluno_id']) as $ins) {
        if ((int) $ins['id'] === (int) $l['inscricao_id']) {
            $comUltimoAcesso = array('usuario_id' => (int) $l['aluno_id'], 'inscricao_id' => (int) $l['inscricao_id']);
            break 2;
        }
    }
}

$candidatos = $pdo->query('SELECT usuario_id FROM inscricoes WHERE deleted_at IS NULL
                           GROUP BY usuario_id ORDER BY COUNT(*) DESC LIMIT 80')->fetchAll(PDO::FETCH_COLUMN);
foreach ($candidatos as $uid) {
    $ativas = $inscricaoModel->forUsuarioAprovadas($uid);
    if (count($ativas) === 1 && $alunoUnico === null) {
        $alunoUnico = array('usuario_id' => (int) $uid, 'inscricao' => $ativas[0]);
    }
    foreach ($ativas as $ins) {
        $temProgresso = $pdo->query('SELECT COUNT(*) FROM conteudo_progresso_aluno
                                     WHERE inscricao_id = ' . (int) $ins['id'] . ' AND deleted_at IS NULL')->fetchColumn();
        $temItens = $pdo->query('SELECT COUNT(*) FROM conteudo_itens i
                                 INNER JOIN conteudo_modulos m ON m.id=i.modulo_id AND m.status="publicado" AND m.deleted_at IS NULL
                                 WHERE i.curso_evento_id = ' . (int) $ins['curso_evento_id'] . '
                                   AND i.status="publicado" AND i.deleted_at IS NULL AND i.tipo<>"etiqueta"')->fetchColumn();
        if ($semProgresso === null && (int) $temProgresso === 0 && (int) $temItens > 0) {
            $semProgresso = array('usuario_id' => (int) $uid, 'inscricao_id' => (int) $ins['id']);
        }
        if ($aptoCert === null && !empty($ins['apto_certificado'])) {
            $aptoCert = array('usuario_id' => (int) $uid, 'inscricao_id' => (int) $ins['id']);
        }
        if ($naoAptoCert === null && empty($ins['apto_certificado'])) {
            $naoAptoCert = array('usuario_id' => (int) $uid, 'inscricao_id' => (int) $ins['id']);
        }
        if ($tudoConcluido === null && (int) $temItens > 0) {
            $pend = $pdo->query('SELECT COUNT(*) FROM conteudo_itens i
                INNER JOIN conteudo_modulos m ON m.id=i.modulo_id AND m.status="publicado" AND m.deleted_at IS NULL
                LEFT JOIN conteudo_progresso_aluno p ON p.item_id=i.id AND p.inscricao_id=' . (int) $ins['id'] . '
                     AND p.aluno_id=' . (int) $uid . ' AND p.deleted_at IS NULL
                WHERE i.curso_evento_id=' . (int) $ins['curso_evento_id'] . ' AND i.status="publicado"
                  AND i.deleted_at IS NULL AND i.tipo<>"etiqueta"
                  AND (p.id IS NULL OR p.status<>"concluido")')->fetchColumn();
            if ((int) $pend === 0) {
                $tudoConcluido = array('usuario_id' => (int) $uid, 'inscricao_id' => (int) $ins['id']);
            }
        }
    }
}

printf("Fixtures: ultimo_acesso=%s  sem_progresso=%s  concluido=%s  apto=%s  nao_apto=%s\n",
    $comUltimoAcesso ? $comUltimoAcesso['inscricao_id'] : '-',
    $semProgresso ? $semProgresso['inscricao_id'] : '-',
    $tudoConcluido ? $tudoConcluido['inscricao_id'] : '-',
    $aptoCert ? $aptoCert['inscricao_id'] : '-',
    $naoAptoCert ? $naoAptoCert['inscricao_id'] : '-');

if (!$alunoUnico) {
    fwrite(STDERR, "ERRO: banco sem aluno de matricula unica.\n");
    exit(1);
}

// ===================================================================

describe('NENHUMA tool escreve no banco');

it('recalcularInscricao() não aparece em nenhum arquivo da Norminha', function () {
    $arquivos = array_merge(
        glob(BASE_PATH . '/app/Services/Norminha*.php') ?: array(),
        glob(BASE_PATH . '/app/Models/Norminha*.php') ?: array(),
        glob(BASE_PATH . '/app/Controllers/Api/Norminha*.php') ?: array()
    );
    expect(count($arquivos))->toBeGreaterThan(0);

    foreach ($arquivos as $arquivo) {
        $fonte = file_get_contents($arquivo);
        // Ignora comentarios: a proibicao e citada em docblock de proposito.
        $semComentarios = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $fonte);
        if (strpos($semComentarios, 'recalcularInscricao') !== false) {
            throw new RuntimeException('CHAMADA PROIBIDA em ' . basename($arquivo));
        }
    }
    expect(true)->toBeTrue();
});

it('nenhum INSERT/UPDATE/DELETE ao exercitar todas as tools', function () use ($pdo, $tools, $alunoUnico, $comUltimoAcesso) {
    $ler = function () use ($pdo) {
        return $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN
            ('Com_insert','Com_update','Com_delete','Com_replace')")->fetchAll(PDO::FETCH_KEY_PAIR);
    };
    $antes = $ler();

    $u = $alunoUnico['usuario_id'];
    $i = (int) $alunoUnico['inscricao']['id'];
    $tools->getStudentProgress($u, $i);
    $tools->getResumePoint($u, $i);
    $tools->getNextLearningItem($u, $i);
    $tools->getCertificateStatus($u, $i);
    $tools->getCurrentLessonContext($u, array('inscricao_id' => $i));
    if ($comUltimoAcesso) {
        $tools->getResumePoint($comUltimoAcesso['usuario_id'], $comUltimoAcesso['inscricao_id']);
    }

    foreach ($ler() as $chave => $valor) {
        if ((int) $valor !== (int) $antes[$chave]) {
            throw new RuntimeException("ESCRITA detectada: {$chave} {$antes[$chave]} -> {$valor}");
        }
    }
    expect(true)->toBeTrue();
});

it('apto_certificado no banco não muda depois de consultar o certificado', function () use ($pdo, $tools, $alunoUnico) {
    $id = (int) $alunoUnico['inscricao']['id'];
    $ler = function () use ($pdo, $id) {
        return $pdo->query('SELECT apto_certificado, percentual_progresso, status
                            FROM inscricoes WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    };
    $antes = $ler();
    $tools->getCertificateStatus($alunoUnico['usuario_id'], $id);
    $tools->getStudentProgress($alunoUnico['usuario_id'], $id);
    expect($ler())->toEqual($antes);
});

describe('Progresso');

it('devolve o mesmo percentual que a tela do aluno', function () use ($tools, $alunoUnico) {
    $r = $tools->getStudentProgress($alunoUnico['usuario_id'], (int) $alunoUnico['inscricao']['id']);
    expect($r['ok'])->toBeTrue();
    // C1: origem e inscricoes.percentual_progresso, nao resumoAluno()
    expect((float) $r['percentual'])->toEqual((float) $alunoUnico['inscricao']['percentual_progresso']);
    expect($r)->toHaveKey('itens_obrigatorios');
});

it('formata o rótulo em PT-BR, com vírgula decimal', function () use ($tools, $alunoUnico) {
    $r = $tools->getStudentProgress($alunoUnico['usuario_id'], (int) $alunoUnico['inscricao']['id']);
    expect($r['label'])->toContain('% concluído');
    expect(strpos($r['label'], '.') === false || strpos($r['label'], ',') !== false)->toBeTrue();
});

describe('Retomada — os três casos de origem');

if ($comUltimoAcesso) {
    it('CASO 1: item acessado e não concluído -> origem = ultimo_acesso', function () use ($tools, $comUltimoAcesso) {
        $r = $tools->getResumePoint($comUltimoAcesso['usuario_id'], $comUltimoAcesso['inscricao_id']);
        expect($r['ok'])->toBeTrue();
        expect($r['origem'])->toBe('ultimo_acesso');
        expect($r['item'])->notToBeNull();
        expect($r['ultimo_acesso_em'])->notToBeNull();
    });

    it('só o caso 1 autoriza dizer "você parou aqui"', function () use ($tools, $comUltimoAcesso) {
        $r = $tools->getResumePoint($comUltimoAcesso['usuario_id'], $comUltimoAcesso['inscricao_id']);
        expect($r['status_progresso'])->notToBeNull();
        expect($r['status_progresso'] === 'concluido')->toBeFalse();
    });
}

if ($semProgresso) {
    it('CASO 2: nada acessado -> origem = proximo_item, sem ultimo_acesso_em', function () use ($tools, $semProgresso) {
        $r = $tools->getResumePoint($semProgresso['usuario_id'], $semProgresso['inscricao_id']);
        expect($r['origem'])->toBe('proximo_item');
        expect($r['item'])->notToBeNull();
        // nao pode fingir memoria que o sistema nao tem
        expect($r['ultimo_acesso_em'])->toBeNull();
    });
}

if ($tudoConcluido) {
    it('CASO 3: tudo concluído -> origem = curso_concluido, sem item', function () use ($tools, $tudoConcluido) {
        $r = $tools->getResumePoint($tudoConcluido['usuario_id'], $tudoConcluido['inscricao_id']);
        expect($r['origem'])->toBe('curso_concluido');
        expect($r['item'])->toBeNull();
    });
}

it('origem está SEMPRE presente', function () use ($tools, $alunoUnico) {
    $r = $tools->getResumePoint($alunoUnico['usuario_id'], (int) $alunoUnico['inscricao']['id']);
    expect($r)->toHaveKey('origem');
    expect(in_array($r['origem'], array('ultimo_acesso', 'proximo_item', 'curso_concluido'), true))->toBeTrue();
});

describe('Próximo item');

it('respeita a ordem de módulo e item', function () use ($tools, $pdo, $alunoUnico) {
    $r = $tools->getNextLearningItem($alunoUnico['usuario_id'], (int) $alunoUnico['inscricao']['id']);
    expect($r['ok'])->toBeTrue();
    expect($r)->toHaveKey('obrigatorios_pendentes');

    if ($r['item']) {
        $curso = (int) $alunoUnico['inscricao']['curso_evento_id'];
        $primeiro = $pdo->query(
            'SELECT i.id FROM conteudo_itens i
             INNER JOIN conteudo_modulos m ON m.id=i.modulo_id AND m.status="publicado" AND m.deleted_at IS NULL
             LEFT JOIN conteudo_progresso_aluno p ON p.item_id=i.id
                    AND p.inscricao_id=' . (int) $alunoUnico['inscricao']['id'] . '
                    AND p.aluno_id=' . (int) $alunoUnico['usuario_id'] . ' AND p.deleted_at IS NULL
             WHERE i.curso_evento_id=' . $curso . ' AND i.status="publicado" AND i.deleted_at IS NULL
               AND i.tipo<>"etiqueta" AND (p.id IS NULL OR p.status<>"concluido")
             ORDER BY m.ordem ASC, i.ordem ASC, i.id ASC LIMIT 1'
        )->fetchColumn();
        expect($r['item']['id'])->toBe((int) $primeiro);
    }
});

it('nunca devolve etiqueta como próximo passo', function () use ($tools, $alunoUnico, $comUltimoAcesso) {
    foreach (array($alunoUnico['usuario_id'] => (int) $alunoUnico['inscricao']['id']) as $u => $i) {
        $r = $tools->getNextLearningItem($u, $i);
        if ($r['item']) {
            expect($r['item']['tipo'] === 'etiqueta')->toBeFalse();
        }
    }
    if ($comUltimoAcesso) {
        $r = $tools->getResumePoint($comUltimoAcesso['usuario_id'], $comUltimoAcesso['inscricao_id']);
        if ($r['item']) {
            expect($r['item']['tipo'] === 'etiqueta')->toBeFalse();
        }
    }
});

describe('Certificado');

if ($aptoCert) {
    it('inscrição apta: situacao apto e sem motivos de bloqueio', function () use ($tools, $aptoCert) {
        $r = $tools->getCertificateStatus($aptoCert['usuario_id'], $aptoCert['inscricao_id']);
        expect($r['ok'])->toBeTrue();
        expect($r['apto'])->toBeTrue();
        expect(in_array($r['situacao'], array('apto', 'certificado_emitido'), true))->toBeTrue();
    });
}

if ($naoAptoCert) {
    it('inscrição não apta: apto=false com motivos em PT-BR', function () use ($tools, $naoAptoCert) {
        $r = $tools->getCertificateStatus($naoAptoCert['usuario_id'], $naoAptoCert['inscricao_id']);
        expect($r['apto'])->toBeFalse();
        expect(count($r['motivos']))->toBeGreaterThan(0);
    });

    it('a resposta concorda com inscricoes.apto_certificado (a mesma da tela)', function () use ($tools, $pdo, $naoAptoCert) {
        $r = $tools->getCertificateStatus($naoAptoCert['usuario_id'], $naoAptoCert['inscricao_id']);
        $banco = $pdo->query('SELECT apto_certificado FROM inscricoes WHERE id = ' . $naoAptoCert['inscricao_id'])->fetchColumn();
        expect($r['apto'])->toBe((bool) $banco);
    });
}

it('TETO DE TAMANHO: motivos truncados, com a contagem real preservada', function () use ($tools, $naoAptoCert, $alunoUnico) {
    $alvo = $naoAptoCert ?: array('usuario_id' => $alunoUnico['usuario_id'], 'inscricao_id' => (int) $alunoUnico['inscricao']['id']);
    $r = $tools->getCertificateStatus($alvo['usuario_id'], $alvo['inscricao_id']);

    expect(count($r['motivos']))->toBeLessThanOrEqual(5);
    expect($r)->toHaveKey('motivos_total');
    expect($r['motivos_total'])->toBeGreaterThanOrEqual(count($r['motivos']));

    foreach ($r['motivos'] as $m) {
        expect(mb_strlen($m, 'UTF-8'))->toBeLessThanOrEqual(300);
    }
    // o payload inteiro precisa caber num prompt
    expect(strlen(json_encode($r)))->toBeLessThanOrEqual(4096);
    echo "      motivos: " . count($r['motivos']) . " de {$r['motivos_total']} | payload "
        . strlen(json_encode($r)) . " bytes\n";
});

describe('Escopo e negação');

it('inscrição de outro aluno não é usada', function () use ($tools, $alunoUnico, $pdo, $inscricaoModel) {
    $alheia = 0;
    foreach ($pdo->query('SELECT id, usuario_id FROM inscricoes WHERE deleted_at IS NULL LIMIT 200') as $l) {
        if ((int) $l['usuario_id'] !== $alunoUnico['usuario_id']) {
            $alheia = (int) $l['id'];
            break;
        }
    }
    expect($alheia)->toBeGreaterThan(0);

    $r = $tools->getStudentProgress($alunoUnico['usuario_id'], $alheia);
    // cai na propria matricula; jamais na alheia
    expect($r['ok'])->toBeTrue();
    expect($r['inscricao_id'])->toBe((int) $alunoUnico['inscricao']['id']);
    expect($r['inscricao_id'] === $alheia)->toBeFalse();
});

it('hint inválido não quebra nenhuma tool', function () use ($tools, $alunoUnico) {
    foreach (array(0, -7, 999999999) as $lixo) {
        expect($tools->getStudentProgress($alunoUnico['usuario_id'], $lixo)['ok'])->toBeTrue();
        expect($tools->getResumePoint($alunoUnico['usuario_id'], $lixo)['ok'])->toBeTrue();
        expect($tools->getNextLearningItem($alunoUnico['usuario_id'], $lixo)['ok'])->toBeTrue();
    }
});

it('aluno sem matrícula recebe erro seguro, sem revelar recurso alheio', function () use ($tools, $pdo, $inscricaoModel) {
    $sem = 0;
    foreach ($pdo->query('SELECT id FROM usuarios ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        if (!$inscricaoModel->forUsuarioAprovadas($uid)) { $sem = (int) $uid; break; }
    }
    if (!$sem) { echo "      (todo usuario tem matricula — caso nao exercitado)\n"; expect(true)->toBeTrue(); return; }

    foreach (array('getStudentProgress', 'getResumePoint', 'getNextLearningItem', 'getCertificateStatus') as $metodo) {
        $r = $tools->$metodo($sem, null);
        expect($r['ok'])->toBeFalse();
        expect($r['erro'])->toBe('sem_inscricao');
        expect(strpos($r['mensagem'], 'matrícula ativa') !== false)->toBeTrue();
    }
});

describe('Aula atual');

it('sem item selecionado, não finge que existe aula', function () use ($tools, $alunoUnico) {
    $r = $tools->getCurrentLessonContext($alunoUnico['usuario_id'], array('inscricao_id' => (int) $alunoUnico['inscricao']['id']));
    expect($r['ok'])->toBeTrue();
    expect($r['tem_aula_atual'])->toBeFalse();
    expect($r['item'])->toBeNull();
});

it('item de avaliação liga em_avaliacao (base do guardrail)', function () use ($tools, $pdo, $inscricaoModel) {
    $itens = $pdo->query('SELECT i.id, i.tipo, i.curso_evento_id FROM conteudo_itens i
        INNER JOIN conteudo_modulos m ON m.id=i.modulo_id AND m.status="publicado" AND m.deleted_at IS NULL
        WHERE i.tipo IN ("quiz","avaliacao_textual") AND i.status="publicado" AND i.deleted_at IS NULL LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($itens as $item) {
        $alunos = $pdo->query('SELECT usuario_id FROM inscricoes WHERE curso_evento_id='
            . (int) $item['curso_evento_id'] . ' AND deleted_at IS NULL LIMIT 20')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($alunos as $uid) {
            foreach ($inscricaoModel->forUsuarioAprovadas($uid) as $ins) {
                if ((int) $ins['curso_evento_id'] !== (int) $item['curso_evento_id']) { continue; }
                $r = $tools->getCurrentLessonContext((int) $uid, array(
                    'inscricao_id' => (int) $ins['id'], 'item_id' => (int) $item['id'],
                ));
                expect($r['em_avaliacao'])->toBeTrue();
                expect($r['tipo_avaliacao'])->toBe((string) $item['tipo']);
                return;
            }
        }
    }
    echo "      (nenhum par avaliacao+aluno no banco)\n";
    expect(true)->toBeTrue();
});

it('nenhuma tool devolve gabarito ou campo administrativo', function () use ($tools, $alunoUnico) {
    $u = $alunoUnico['usuario_id']; $i = (int) $alunoUnico['inscricao']['id'];
    $tudo = json_encode(array(
        $tools->getStudentProgress($u, $i),
        $tools->getResumePoint($u, $i),
        $tools->getNextLearningItem($u, $i),
        $tools->getCertificateStatus($u, $i),
        $tools->getCurrentLessonContext($u, array('inscricao_id' => $i)),
    ), JSON_UNESCAPED_UNICODE);

    foreach (array('gabarito', 'resposta_correta', 'alternativa_correta', 'is_correta',
                   'chave_correcao', 'cpf', 'pagador', 'senha') as $proibido) {
        if (stripos($tudo, $proibido) !== false) {
            throw new RuntimeException("Campo proibido no retorno: {$proibido}");
        }
    }
    expect(true)->toBeTrue();
});

exit(testes_resumo());

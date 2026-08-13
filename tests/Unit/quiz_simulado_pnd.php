<?php

/**
 * Testes do simulado com banco de questões (formato PND).
 *
 * Execução (banco de testes recomendado — cria e remove dados):
 *   DB_DATABASE=dc_quiz_test DB_USERNAME=... DB_PASSWORD=... php tests/Unit/quiz_simulado_pnd.php
 *
 * Cobre: compatibilidade com quiz legado, sorteio 30+50+1, distribuição de
 * dificuldade, ausência de repetição entre tentativas, fallback auditável,
 * persistência/retomada do snapshot, limite de tentativas e concorrência,
 * percentual só nas objetivas, discursiva obrigatória e fora do cálculo,
 * melhor tentativa, expiração com envio automático, validação contra o
 * snapshot, não exposição do gabarito, correção manual e autorização.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\ConteudoQuizBlocoService;
use App\Services\ConteudoQuizDiscursivaService;
use App\Services\ConteudoQuizService;
use App\Services\Quiz\QuizRandomizerSemente;

$pdo = testes_conectar_banco();

// ----------------------------------------------------------------
// Massa de teste
// ----------------------------------------------------------------

$sufixo = 'PNDTEST' . substr((string) microtime(true), -6);
$criado = array('curso' => 0, 'modulo' => 0, 'usuarios' => array(), 'pedido' => 0);

function inserir(PDO $pdo, $sql, array $params = array())
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $pdo->lastInsertId();
}

// Curso, módulo e item
$cursoId = inserir($pdo, 'INSERT INTO cursos_eventos (nome, slug, created_at, updated_at) VALUES (:n, :s, NOW(), NOW())', array(
    'n' => '__TESTE_PND__ ' . $sufixo,
    's' => 'teste-pnd-' . strtolower($sufixo),
));
$criado['curso'] = $cursoId;

$moduloId = inserir($pdo, 'INSERT INTO conteudo_modulos (curso_evento_id, titulo, status, ordem, created_at, updated_at)
                           VALUES (:c, :t, \'publicado\', 900, NOW(), NOW())', array(
    'c' => $cursoId, 't' => '__TESTE_PND_MODULO__',
));
$criado['modulo'] = $moduloId;

$itemId = inserir($pdo, 'INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, status, ordem, created_at, updated_at)
                         VALUES (:c, :m, \'quiz\', :t, 1, \'publicado\', 1, NOW(), NOW())', array(
    'c' => $cursoId, 'm' => $moduloId, 't' => 'Simulado PND - Pedagogia',
));

// Quiz: 3 tentativas, 330 minutos, mínimo de 60%, sorteio por blocos
$quizId = inserir($pdo, 'INSERT INTO conteudo_quizzes
        (item_id, instrucoes, tentativas_maximas, percentual_minimo, duracao_minutos, modo_selecao,
         acao_ao_expirar, evitar_repeticao_tentativas, exige_aprovacao,
         exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio,
         embaralhar_perguntas, embaralhar_alternativas, created_at, updated_at)
        VALUES (:i, :ins, 3, 60.00, 330, \'blocos\', \'enviar_automatico\', 1, 1, 1, 1, 1, 1, 1, NOW(), NOW())', array(
    'i'   => $itemId,
    'ins' => 'Prova de 330 minutos com 80 questões objetivas e 1 discursiva.',
));

// Blocos: FGD 30, PEDAGOGIA 50, DISCURSIVA 1
$distribuicao = json_encode(array('facil' => 20, 'media' => 60, 'dificil' => 20));
$blocoFgd = inserir($pdo, 'INSERT INTO conteudo_quiz_blocos
        (quiz_id, codigo, titulo, tipo_questao, quantidade_sortear, distribuicao_dificuldade_json,
         conta_para_percentual, obrigatorio_para_envio, ordem, status, created_at, updated_at)
        VALUES (:q, \'FGD\', \'Formação Geral Docente\', \'multipla_escolha\', 30, :d, 1, 1, 1, \'ativo\', NOW(), NOW())',
    array('q' => $quizId, 'd' => $distribuicao));

$blocoPed = inserir($pdo, 'INSERT INTO conteudo_quiz_blocos
        (quiz_id, codigo, titulo, tipo_questao, quantidade_sortear, distribuicao_dificuldade_json,
         conta_para_percentual, obrigatorio_para_envio, ordem, status, created_at, updated_at)
        VALUES (:q, \'PEDAGOGIA\', \'Componente Específico - Pedagogia\', \'multipla_escolha\', 50, :d, 1, 1, 2, \'ativo\', NOW(), NOW())',
    array('q' => $quizId, 'd' => $distribuicao));

$blocoDis = inserir($pdo, 'INSERT INTO conteudo_quiz_blocos
        (quiz_id, codigo, titulo, tipo_questao, quantidade_sortear, distribuicao_dificuldade_json,
         conta_para_percentual, obrigatorio_para_envio, ordem, status, created_at, updated_at)
        VALUES (:q, \'DISCURSIVA\', \'Questão discursiva\', \'discursiva\', 1, NULL, 0, 1, 3, \'ativo\', NOW(), NOW())',
    array('q' => $quizId));

/**
 * Cria questões objetivas com 4 alternativas (a 2a é sempre a correta).
 */
function criarObjetivas(PDO $pdo, $quizId, $blocoId, array $porDificuldade, $tema)
{
    $ids = array();
    $ordem = 1;
    foreach ($porDificuldade as $dificuldade => $quantidade) {
        for ($i = 0; $i < $quantidade; $i++) {
            $perguntaId = inserir($pdo, 'INSERT INTO conteudo_quiz_perguntas
                (quiz_id, bloco_id, enunciado, tipo, dificuldade, tema, status, peso, obrigatoria, ordem, created_at, updated_at)
                VALUES (:q, :b, :e, \'multipla_escolha\', :d, :t, \'ativo\', 1, 1, :o, NOW(), NOW())', array(
                'q' => $quizId, 'b' => $blocoId,
                'e' => 'Questão ' . $tema . ' ' . $dificuldade . ' #' . ($i + 1),
                'd' => $dificuldade, 't' => $tema, 'o' => $ordem++,
            ));

            foreach (array('A', 'B', 'C', 'D') as $indice => $letra) {
                inserir($pdo, 'INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at)
                               VALUES (:p, :t, :c, :o, NOW(), NOW())', array(
                    'p' => $perguntaId, 't' => 'Alternativa ' . $letra,
                    'c' => $indice === 1 ? 1 : 0, 'o' => $indice + 1,
                ));
            }
            $ids[] = $perguntaId;
        }
    }
    return $ids;
}

// Banco: 90 de FGD (18/54/18) e 150 de Pedagogia (30/90/30)
criarObjetivas($pdo, $quizId, $blocoFgd, array('facil' => 18, 'media' => 54, 'dificil' => 18), 'FGD');
criarObjetivas($pdo, $quizId, $blocoPed, array('facil' => 30, 'media' => 90, 'dificil' => 30), 'Pedagogia');

// 3 discursivas no banco (sorteia 1 por tentativa)
for ($i = 1; $i <= 3; $i++) {
    inserir($pdo, 'INSERT INTO conteudo_quiz_perguntas
        (quiz_id, bloco_id, enunciado, tipo, dificuldade, tema, status, rubrica, nota_maxima, peso, obrigatoria, ordem, created_at, updated_at)
        VALUES (:q, :b, :e, \'discursiva\', \'media\', :t, \'ativo\', :r, 10.00, 1, 1, :o, NOW(), NOW())', array(
        'q' => $quizId, 'b' => $blocoDis,
        'e' => 'Discursiva #' . $i . ': discuta a prática docente.',
        't' => 'Prática docente', 'r' => 'Critérios: contextualização, argumentação e proposta.', 'o' => $i,
    ));
}

/**
 * Cria aluno + inscrição completa (pedido, item e participante).
 */
function criarAlunoInscrito(PDO $pdo, $cursoId, $nome, $email, $cpf, &$criado)
{
    $alunoId = inserir($pdo, 'INSERT INTO usuarios (nome, email, cpf, senha_hash, status, created_at, updated_at)
                              VALUES (:n, :e, :c, :s, \'ativo\', NOW(), NOW())', array(
        'n' => $nome, 'e' => $email, 'c' => $cpf, 's' => password_hash('teste123', PASSWORD_DEFAULT),
    ));
    $criado['usuarios'][] = $alunoId;

    $pedidoId = inserir($pdo, 'INSERT INTO pedidos (comprador_usuario_id, pagador_usuario_id, codigo, created_at, updated_at)
                               VALUES (:u, :u2, :c, NOW(), NOW())', array(
        'u' => $alunoId, 'u2' => $alunoId, 'c' => 'TSTPND' . substr(uniqid(), -10),
    ));
    $itemPedidoId = inserir($pdo, 'INSERT INTO pedido_itens (pedido_id, curso_evento_id, created_at, updated_at)
                                   VALUES (:p, :c, NOW(), NOW())', array('p' => $pedidoId, 'c' => $cursoId));
    $participanteId = inserir($pdo, 'INSERT INTO participantes_pedido (pedido_id, pedido_item_id, usuario_id, nome, email, created_at, updated_at)
                                     VALUES (:p, :i, :u, :n, :e, NOW(), NOW())', array(
        'p' => $pedidoId, 'i' => $itemPedidoId, 'u' => $alunoId, 'n' => $nome, 'e' => $email,
    ));

    $inscricaoId = inserir($pdo, 'INSERT INTO inscricoes
            (pedido_id, pedido_item_id, participante_pedido_id, curso_evento_id, usuario_id, status, created_at, updated_at)
            VALUES (:p, :i, :pa, :c, :u, \'ativa\', NOW(), NOW())', array(
        'p' => $pedidoId, 'i' => $itemPedidoId, 'pa' => $participanteId, 'c' => $cursoId, 'u' => $alunoId,
    ));

    return array('aluno_id' => $alunoId, 'inscricao_id' => $inscricaoId);
}

$aluno  = criarAlunoInscrito($pdo, $cursoId, '__TesteAluno PND__', 'pnd1.' . strtolower($sufixo) . '@teste.local', substr('900' . substr($sufixo, -8), 0, 11), $criado);
$outro  = criarAlunoInscrito($pdo, $cursoId, '__TesteOutro PND__', 'pnd2.' . strtolower($sufixo) . '@teste.local', substr('901' . substr($sufixo, -8), 0, 11), $criado);

$alunoId     = $aluno['aluno_id'];
$inscricaoId = $aluno['inscricao_id'];

// Serviço com aleatoriedade determinista
$quizService     = new ConteudoQuizService(new QuizRandomizerSemente(20260812));
$blocoService    = new ConteudoQuizBlocoService();
$discursivaService = new ConteudoQuizDiscursivaService();

// ----------------------------------------------------------------
// Utilitários dos testes
// ----------------------------------------------------------------

function snapshotDaTentativa(PDO $pdo, $tentativaId)
{
    $stmt = $pdo->prepare('SELECT quiz_snapshot_json FROM conteudo_quiz_tentativas WHERE id = :id');
    $stmt->execute(array('id' => (int) $tentativaId));
    return json_decode((string) $stmt->fetchColumn(), true);
}

/**
 * Monta as respostas de uma tentativa acertando $acertos objetivas.
 */
function montarRespostas(array $snapshot, $acertos)
{
    $respostas   = array();
    $discursivas = array();
    $corretas    = 0;

    foreach ($snapshot['perguntas'] as $pergunta) {
        if ((string) $pergunta['tipo'] === 'discursiva') {
            $discursivas[(int) $pergunta['id']] = 'Resposta discursiva de teste com argumentação suficiente.';
            continue;
        }

        $idCorreta = null;
        $idErrada  = null;
        foreach ($pergunta['alternativas'] as $alternativa) {
            if (!empty($alternativa['correta'])) {
                $idCorreta = (int) $alternativa['id'];
            } elseif ($idErrada === null) {
                $idErrada = (int) $alternativa['id'];
            }
        }

        if ($corretas < $acertos) {
            $respostas[(int) $pergunta['id']] = $idCorreta;
            $corretas++;
        } else {
            $respostas[(int) $pergunta['id']] = $idErrada;
        }
    }

    return array('respostas' => $respostas, 'discursivas' => $discursivas);
}

function contarPorBlocoSnapshot(array $snapshot)
{
    $contagem = array();
    foreach ($snapshot['perguntas'] as $pergunta) {
        $codigo = (string) $pergunta['bloco_codigo'];
        $contagem[$codigo] = (isset($contagem[$codigo]) ? $contagem[$codigo] : 0) + 1;
    }
    return $contagem;
}

echo "=== Testes do simulado PND (banco de questões) ===\n";

// ----------------------------------------------------------------
describe('Validação administrativa da composição');
// ----------------------------------------------------------------

it('banco completo passa na validação de composição', function () use ($quizService, $quizId) {
    $resultado = $quizService->validarComposicao($quizId);
    expect($resultado['ok'])->toBeTrue();
    expect(count($resultado['erros']))->toBe(0);
});

it('bloco sem questões suficientes impede a publicação', function () use ($pdo, $quizService, $quizId, $blocoFgd) {
    $pdo->prepare('UPDATE conteudo_quiz_blocos SET quantidade_sortear = 500 WHERE id = :id')
        ->execute(array('id' => $blocoFgd));

    try {
        $resultado = $quizService->validarComposicao($quizId);
        expect($resultado['ok'])->toBeFalse();
        expect(count($resultado['erros']))->toBeGreaterThan(0);
    } finally {
        // Restaura a composição mesmo se a asserção falhar.
        $pdo->prepare('UPDATE conteudo_quiz_blocos SET quantidade_sortear = 30 WHERE id = :id')
            ->execute(array('id' => $blocoFgd));
    }
});

it('exceção consciente converte o erro em alerta com mensagem clara', function () use ($pdo, $quizService, $quizId, $blocoFgd) {
    $pdo->prepare('UPDATE conteudo_quiz_blocos SET quantidade_sortear = 500 WHERE id = :id')->execute(array('id' => $blocoFgd));
    $pdo->prepare('UPDATE conteudo_quizzes SET permitir_banco_insuficiente = 1 WHERE id = :id')->execute(array('id' => $quizId));

    try {
        $resultado = $quizService->validarComposicao($quizId);
        expect($resultado['ok'])->toBeTrue();
        expect(count($resultado['alertas']))->toBeGreaterThan(0);
        expect($resultado['alertas'][0])->toContain('Exceção autorizada');
    } finally {
        $pdo->prepare('UPDATE conteudo_quiz_blocos SET quantidade_sortear = 30 WHERE id = :id')->execute(array('id' => $blocoFgd));
        $pdo->prepare('UPDATE conteudo_quizzes SET permitir_banco_insuficiente = 0 WHERE id = :id')->execute(array('id' => $quizId));
    }
});

it('bloco recusa questão de tipo divergente', function () use ($quizService, $quizId, $blocoDis) {
    $resultado = $quizService->salvarPergunta(array(
        'quiz_id'  => $quizId,
        'bloco_id' => $blocoDis, // bloco discursivo
        'tipo'     => 'multipla_escolha',
        'enunciado'=> 'Objetiva em bloco discursivo',
        'alternativas' => array(
            array('texto' => 'A', 'correta' => 0),
            array('texto' => 'B', 'correta' => 1),
        ),
    ));
    expect($resultado['ok'])->toBeFalse();
    expect($resultado['message'])->toContain('não corresponde ao tipo do bloco');
});

// ----------------------------------------------------------------
describe('Sorteio da tentativa (30 + 50 + 1)');
// ----------------------------------------------------------------

$tentativa1 = null;

it('cria a tentativa com 81 questões no snapshot', function () use ($quizService, $pdo, $quizId, $inscricaoId, $alunoId, $cursoId, &$tentativa1) {
    $resultado = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    expect($resultado['ok'])->toBeTrue();
    expect($resultado['retomada'])->toBeFalse();

    $tentativa1 = $resultado['tentativa'];
    $snapshot   = snapshotDaTentativa($pdo, $tentativa1['id']);

    expect(count($snapshot['perguntas']))->toBe(81);
    $contagem = contarPorBlocoSnapshot($snapshot);
    expect($contagem['FGD'])->toBe(30);
    expect($contagem['PEDAGOGIA'])->toBe(50);
    expect($contagem['DISCURSIVA'])->toBe(1);
});

it('o snapshot guarda 80 objetivas e 1 discursiva', function () use (&$tentativa1) {
    expect((int) $tentativa1['total_perguntas'])->toBe(81);
    expect((int) $tentativa1['total_objetivas'])->toBe(80);
    expect((int) $tentativa1['total_discursivas'])->toBe(1);
    expect((string) $tentativa1['discursiva_status'])->toBe('pendente');
});

it('a distribuição de dificuldade é preservada por bloco', function () use ($pdo, &$tentativa1) {
    $snapshot = snapshotDaTentativa($pdo, $tentativa1['id']);
    $porBloco = array();
    foreach ($snapshot['perguntas'] as $pergunta) {
        if ((string) $pergunta['tipo'] === 'discursiva') {
            continue;
        }
        $codigo = (string) $pergunta['bloco_codigo'];
        if (!isset($porBloco[$codigo])) {
            $porBloco[$codigo] = array('facil' => 0, 'media' => 0, 'dificil' => 0);
        }
        $porBloco[$codigo][(string) $pergunta['dificuldade']]++;
    }

    expect($porBloco['FGD'])->toEqual(array('facil' => 6, 'media' => 18, 'dificil' => 6));
    expect($porBloco['PEDAGOGIA'])->toEqual(array('facil' => 10, 'media' => 30, 'dificil' => 10));
});

it('a prova tem 330 minutos e prazo calculado no servidor', function () use (&$tentativa1) {
    expect((int) $tentativa1['duracao_minutos'])->toBe(330);
    expect($tentativa1['expira_em'])->notToBeNull();

    $duracaoReal = strtotime($tentativa1['expira_em']) - strtotime($tentativa1['iniciada_em']);
    expect($duracaoReal)->toBe(330 * 60);
});

it('o snapshot registra as alternativas embaralhadas e a ordem apresentada', function () use ($pdo, &$tentativa1) {
    $snapshot = snapshotDaTentativa($pdo, $tentativa1['id']);
    $ordens = array();
    foreach ($snapshot['perguntas'] as $pergunta) {
        $ordens[] = (int) $pergunta['ordem_apresentacao'];
        if ((string) $pergunta['tipo'] !== 'discursiva') {
            expect(count($pergunta['alternativas']))->toBe(4);
        }
    }
    expect($ordens)->toEqual(range(1, 81));
    expect($snapshot['versao'])->toBe(2);
    expect($snapshot['modo_selecao'])->toBe('blocos');
});

it('retomar a tentativa não gera novo sorteio', function () use ($quizService, $pdo, $quizId, $inscricaoId, $alunoId, $cursoId, &$tentativa1) {
    $antes = snapshotDaTentativa($pdo, $tentativa1['id']);

    $resultado = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));

    expect($resultado['retomada'])->toBeTrue();
    expect((int) $resultado['tentativa']['id'])->toBe((int) $tentativa1['id']);

    $depois = snapshotDaTentativa($pdo, $tentativa1['id']);
    expect(json_encode($antes['perguntas']))->toBe(json_encode($depois['perguntas']));
});

// ----------------------------------------------------------------
describe('Segurança: gabarito e pertencimento ao snapshot');
// ----------------------------------------------------------------

it('o gabarito não é exposto ao aluno antes do envio', function () use ($quizService, $itemId, $alunoId, $inscricaoId) {
    $quiz = $quizService->findQuizParaAluno($itemId, $alunoId, $inscricaoId);
    expect($quiz)->notToBeNull();
    foreach ($quiz['perguntas'] as $pergunta) {
        foreach ($pergunta['alternativas'] as $alternativa) {
            if (array_key_exists('correta', $alternativa)) {
                throw new RuntimeException('Campo "correta" exposto ao aluno antes do envio.');
            }
        }
        if (array_key_exists('rubrica', $pergunta)) {
            throw new RuntimeException('Rubrica de correção exposta ao aluno.');
        }
    }
});

it('rascunho rejeita questão fora do snapshot silenciosamente', function () use ($quizService, $pdo, $alunoId, $quizId, &$tentativa1) {
    // Pergunta do banco que não entrou no sorteio desta tentativa
    $snapshot = snapshotDaTentativa($pdo, $tentativa1['id']);
    $sorteadas = array();
    foreach ($snapshot['perguntas'] as $pergunta) {
        $sorteadas[] = (int) $pergunta['id'];
    }

    $stmt = $pdo->prepare('SELECT id FROM conteudo_quiz_perguntas WHERE quiz_id = :q AND id NOT IN (' . implode(',', $sorteadas) . ') LIMIT 1');
    $stmt->execute(array('q' => $quizId));
    $foraId = (int) $stmt->fetchColumn();
    expect($foraId)->toBeGreaterThan(0);

    $resultado = $quizService->salvarRascunho(array(
        'tentativa_id' => (int) $tentativa1['id'],
        'aluno_id'     => $alunoId,
        'respostas'    => array($foraId => 1),
    ));
    expect($resultado['ok'])->toBeTrue();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM conteudo_quiz_respostas WHERE tentativa_id = :t AND pergunta_id = :p');
    $stmt->execute(array('t' => (int) $tentativa1['id'], 'p' => $foraId));
    expect((int) $stmt->fetchColumn())->toBe(0);
});

it('envio rejeita alternativa que não pertence à questão sorteada', function () use ($quizService, $pdo, $alunoId, &$tentativa1) {
    $snapshot = snapshotDaTentativa($pdo, $tentativa1['id']);
    $primeira = null;
    $segunda  = null;
    foreach ($snapshot['perguntas'] as $pergunta) {
        if ((string) $pergunta['tipo'] === 'discursiva') {
            continue;
        }
        if ($primeira === null) {
            $primeira = $pergunta;
        } elseif ($segunda === null) {
            $segunda = $pergunta;
            break;
        }
    }

    // Alternativa da segunda questão enviada para a primeira
    $resultado = $quizService->enviarTentativa(array(
        'tentativa_id' => (int) $tentativa1['id'],
        'aluno_id'     => $alunoId,
        'respostas'    => array((int) $primeira['id'] => (int) $segunda['alternativas'][0]['id']),
    ));

    expect($resultado['ok'])->toBeFalse();

    $stmt = $pdo->prepare('SELECT status FROM conteudo_quiz_tentativas WHERE id = :id');
    $stmt->execute(array('id' => (int) $tentativa1['id']));
    expect((string) $stmt->fetchColumn())->toBe('em_andamento');
});

it('aluno não acessa a tentativa de outro aluno', function () use ($quizService, $outro, &$tentativa1) {
    expect($quizService->obterTentativaParaAluno((int) $tentativa1['id'], $outro['aluno_id']))->toBeNull();
});

// ----------------------------------------------------------------
describe('Obrigatoriedade validada apenas contra o snapshot');
// ----------------------------------------------------------------

it('envio sem responder tudo é bloqueado', function () use ($quizService, $pdo, $alunoId, &$tentativa1) {
    $snapshot = snapshotDaTentativa($pdo, $tentativa1['id']);
    $dados    = montarRespostas($snapshot, 80);

    // Remove uma objetiva
    array_pop($dados['respostas']);

    $resultado = $quizService->enviarTentativa(array(
        'tentativa_id' => (int) $tentativa1['id'],
        'aluno_id'     => $alunoId,
        'respostas'    => $dados['respostas'],
        'discursivas'  => $dados['discursivas'],
    ));

    expect($resultado['ok'])->toBeFalse();
    expect($resultado['message'])->toContain('objetivas');
});

it('a discursiva é obrigatória para o envio', function () use ($quizService, $pdo, $alunoId, &$tentativa1) {
    $snapshot = snapshotDaTentativa($pdo, $tentativa1['id']);
    $dados    = montarRespostas($snapshot, 80);

    $resultado = $quizService->enviarTentativa(array(
        'tentativa_id' => (int) $tentativa1['id'],
        'aluno_id'     => $alunoId,
        'respostas'    => $dados['respostas'],
        'discursivas'  => array(), // sem discursiva
    ));

    expect($resultado['ok'])->toBeFalse();
    expect($resultado['message'])->toContain('discursiva');
});

// ----------------------------------------------------------------
describe('Correção: 60% apenas sobre as 80 objetivas');
// ----------------------------------------------------------------

$resultadoTentativa1 = null;

it('48 acertos objetivos aprovam o aluno (denominador 80)', function () use ($quizService, $pdo, $alunoId, $itemId, $inscricaoId, &$tentativa1, &$resultadoTentativa1) {
    $snapshot = snapshotDaTentativa($pdo, $tentativa1['id']);
    $dados    = montarRespostas($snapshot, 48);

    $envio = $quizService->enviarTentativa(array(
        'tentativa_id' => (int) $tentativa1['id'],
        'aluno_id'     => $alunoId,
        'item_id'      => $itemId,
        'inscricao_id' => $inscricaoId,
        'respostas'    => $dados['respostas'],
        'discursivas'  => $dados['discursivas'],
    ));

    expect($envio['ok'])->toBeTrue();
    $resultadoTentativa1 = $envio['resultado'];

    expect((int) $resultadoTentativa1['total_objetivas'])->toBe(80);
    expect((int) $resultadoTentativa1['total_acertos'])->toBe(48);
    expect((float) $resultadoTentativa1['percentual'])->toBe(60.0);
    expect($resultadoTentativa1['aprovado'])->toBeTrue();
});

it('a discursiva não entra no cálculo do percentual', function () use (&$resultadoTentativa1) {
    // 81 questões no snapshot, mas o denominador é 80
    expect((int) $resultadoTentativa1['total_perguntas'])->toBe(81);
    expect((int) $resultadoTentativa1['total_objetivas'])->toBe(80);
    expect((float) $resultadoTentativa1['pontos_totais'])->toBe(80.0);
    expect((int) $resultadoTentativa1['total_discursivas'])->toBe(1);
    expect((string) $resultadoTentativa1['discursiva_status'])->toBe('pendente');
});

it('o item obrigatório fica concluído para o aluno aprovado', function () use ($pdo, $alunoId, $itemId) {
    $stmt = $pdo->prepare('SELECT status FROM conteudo_progresso_aluno WHERE aluno_id = :a AND item_id = :i AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(array('a' => $alunoId, 'i' => $itemId));
    expect((string) $stmt->fetchColumn())->toBe('concluido');
});

it('o desempenho é detalhado por bloco e por tema', function () use ($quizService, $pdo, &$tentativa1) {
    $stmt = $pdo->prepare('SELECT * FROM conteudo_quiz_tentativas WHERE id = :id');
    $stmt->execute(array('id' => (int) $tentativa1['id']));
    $tentativa = $stmt->fetch();

    $desempenho = $quizService->desempenhoDaTentativa($tentativa);
    expect($desempenho['total_objetivas'])->toBe(80);
    expect($desempenho['total_acertos'])->toBe(48);
    expect(count($desempenho['blocos']))->toBe(2);
    expect(count($desempenho['temas']))->toBeGreaterThan(0);

    $soma = 0;
    foreach ($desempenho['blocos'] as $bloco) {
        $soma += $bloco['total'];
    }
    expect($soma)->toBe(80);
});

it('reenviar a mesma tentativa não recalcula nem duplica', function () use ($quizService, $alunoId, &$tentativa1) {
    $reenvio = $quizService->enviarTentativa(array(
        'tentativa_id' => (int) $tentativa1['id'],
        'aluno_id'     => $alunoId,
        'respostas'    => array(),
    ));
    expect($reenvio['ok'])->toBeTrue();
    expect(!empty($reenvio['reutilizada']))->toBeTrue();
});

// ----------------------------------------------------------------
describe('Correção manual da discursiva');
// ----------------------------------------------------------------

$correcaoId = null;

it('a discursiva enviada entra na fila de correção como pendente', function () use ($discursivaService, $quizId, $cursoId, &$correcaoId) {
    $fila = $discursivaService->listarFila($quizId, $cursoId, array('status' => 'pendente'));
    expect(count($fila))->toBe(1);
    expect((string) $fila[0]['status'])->toBe('pendente');
    expect($fila[0]['resposta_texto'])->toContain('Resposta discursiva de teste');
    $correcaoId = (int) $fila[0]['id'];
});

it('a nota da discursiva exige valor válido', function () use ($discursivaService, $alunoId, &$correcaoId) {
    $semNota = $discursivaService->corrigir(array('correcao_id' => $correcaoId, 'usuario_id' => $alunoId));
    expect($semNota['ok'])->toBeFalse();

    $acimaMaximo = $discursivaService->corrigir(array(
        'correcao_id' => $correcaoId, 'usuario_id' => $alunoId, 'nota' => 11,
    ));
    expect($acimaMaximo['ok'])->toBeFalse();
    expect($acimaMaximo['message'])->toContain('nota máxima');
});

it('registra nota, rubrica, feedback, corretor e data', function () use ($discursivaService, $pdo, $alunoId, &$correcaoId) {
    $resultado = $discursivaService->corrigir(array(
        'correcao_id' => $correcaoId,
        'usuario_id'  => $alunoId,
        'nota'        => 8.5,
        'rubrica'     => 'Contextualização e proposta pedagógica.',
        'feedback'    => 'Boa argumentação; aprofunde a fundamentação teórica.',
    ));
    expect($resultado['ok'])->toBeTrue();

    $stmt = $pdo->prepare('SELECT * FROM conteudo_quiz_correcoes_discursivas WHERE id = :id');
    $stmt->execute(array('id' => $correcaoId));
    $correcao = $stmt->fetch();

    expect((string) $correcao['status'])->toBe('corrigida');
    expect((float) $correcao['nota'])->toBe(8.5);
    expect((int) $correcao['corretor_id'])->toBe($alunoId);
    expect($correcao['corrigida_em'])->notToBeNull();
    expect((string) $correcao['origem'])->toBe('manual');
});

it('mantém histórico básico de alteração da nota', function () use ($discursivaService, $pdo, $alunoId, &$correcaoId) {
    $discursivaService->corrigir(array(
        'correcao_id' => $correcaoId,
        'usuario_id'  => $alunoId,
        'nota'        => 9.0,
        'feedback'    => 'Revisão da correção após recurso.',
    ));

    $stmt = $pdo->prepare('SELECT * FROM conteudo_quiz_correcoes_discursivas_historico WHERE correcao_id = :c ORDER BY id DESC');
    $stmt->execute(array('c' => $correcaoId));
    $historico = $stmt->fetchAll();

    expect(count($historico))->toBe(2);
    expect((float) $historico[0]['nota_anterior'])->toBe(8.5);
    expect((float) $historico[0]['nota_nova'])->toBe(9.0);
});

it('a nota discursiva não altera a aprovação objetiva', function () use ($pdo, &$tentativa1) {
    $stmt = $pdo->prepare('SELECT aprovado, percentual, discursiva_status FROM conteudo_quiz_tentativas WHERE id = :id');
    $stmt->execute(array('id' => (int) $tentativa1['id']));
    $tentativa = $stmt->fetch();

    expect((int) $tentativa['aprovado'])->toBe(1);
    expect((float) $tentativa['percentual'])->toBe(60.0);
    expect((string) $tentativa['discursiva_status'])->toBe('corrigida');
});

it('o aluno vê o feedback apenas depois da correção', function () use ($quizService, $alunoId, &$tentativa1) {
    $dados = $quizService->obterTentativaParaAluno((int) $tentativa1['id'], $alunoId);
    expect(count($dados['discursivas']))->toBe(1);
    expect((float) $dados['discursivas'][0]['nota'])->toBe(9.0);
    expect($dados['discursivas'][0]['feedback'])->toContain('Revisão da correção');
});

// ----------------------------------------------------------------
describe('Tentativas seguintes: sem repetição e melhor resultado');
// ----------------------------------------------------------------

$tentativa2 = null;

it('a segunda tentativa não repete questões da primeira', function () use ($quizService, $pdo, $quizId, $inscricaoId, $alunoId, $cursoId, &$tentativa1, &$tentativa2) {
    $resultado = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    expect($resultado['ok'])->toBeTrue();
    $tentativa2 = $resultado['tentativa'];
    expect((int) $tentativa2['numero_tentativa'])->toBe(2);
    expect((int) $tentativa2['sorteio_com_repeticao'])->toBe(0);

    $ids1 = array();
    foreach (snapshotDaTentativa($pdo, $tentativa1['id'])['perguntas'] as $pergunta) {
        $ids1[] = (int) $pergunta['id'];
    }
    $ids2 = array();
    foreach (snapshotDaTentativa($pdo, $tentativa2['id'])['perguntas'] as $pergunta) {
        $ids2[] = (int) $pergunta['id'];
    }

    expect(count(array_intersect($ids1, $ids2)))->toBe(0);
    expect(count($ids2))->toBe(81);
});

it('reprovar na segunda tentativa não reabre o item já concluído', function () use ($quizService, $pdo, $alunoId, $itemId, $inscricaoId, &$tentativa2) {
    $snapshot = snapshotDaTentativa($pdo, $tentativa2['id']);
    $dados    = montarRespostas($snapshot, 10); // 12,5%

    $envio = $quizService->enviarTentativa(array(
        'tentativa_id' => (int) $tentativa2['id'],
        'aluno_id'     => $alunoId,
        'item_id'      => $itemId,
        'inscricao_id' => $inscricaoId,
        'respostas'    => $dados['respostas'],
        'discursivas'  => $dados['discursivas'],
    ));

    expect($envio['ok'])->toBeTrue();
    expect($envio['resultado']['aprovado'])->toBeFalse();
    expect((float) $envio['resultado']['percentual'])->toBe(12.5);

    // A melhor tentativa manda: o item continua concluído
    $stmt = $pdo->prepare('SELECT status FROM conteudo_progresso_aluno WHERE aluno_id = :a AND item_id = :i AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(array('a' => $alunoId, 'i' => $itemId));
    expect((string) $stmt->fetchColumn())->toBe('concluido');
});

it('a melhor tentativa aprovada é preservada no histórico', function () use ($pdo, $quizId, $inscricaoId) {
    $modelo = new \App\Models\ConteudoQuizTentativa();
    $melhor = $modelo->findMelhorTentativa($quizId, $inscricaoId);
    expect((float) $melhor['percentual'])->toBe(60.0);
    expect((int) $melhor['aprovado'])->toBe(1);
    expect($modelo->existeAprovada($quizId, $inscricaoId))->toBeTrue();
});

// ----------------------------------------------------------------
describe('Tempo de prova: expiração e envio automático');
// ----------------------------------------------------------------

$tentativa3 = null;

it('a terceira tentativa é criada normalmente', function () use ($quizService, $quizId, $inscricaoId, $alunoId, $cursoId, &$tentativa3) {
    $resultado = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    expect($resultado['ok'])->toBeTrue();
    $tentativa3 = $resultado['tentativa'];
    expect((int) $tentativa3['numero_tentativa'])->toBe(3);
});

it('o tempo restante é calculado no servidor, não no navegador', function () use ($quizService, $pdo, &$tentativa3) {
    $stmt = $pdo->prepare('SELECT * FROM conteudo_quiz_tentativas WHERE id = :id');
    $stmt->execute(array('id' => (int) $tentativa3['id']));
    $tempo = $quizService->tempoDaTentativa($stmt->fetch());

    expect($tempo['duracao_minutos'])->toBe(330);
    expect($tempo['expirada'])->toBeFalse();
    expect($tempo['segundos_restantes'])->toBeGreaterThan(330 * 60 - 60);
    expect($tempo['segundos_restantes'])->toBeLessThanOrEqual(330 * 60);
});

it('ao passar 330 minutos, o rascunho salvo é enviado automaticamente', function () use ($quizService, $pdo, $alunoId, $itemId, $inscricaoId, &$tentativa3) {
    // Rascunho parcial: 20 objetivas corretas, sem discursiva
    $snapshot = snapshotDaTentativa($pdo, $tentativa3['id']);
    $dados    = montarRespostas($snapshot, 20);
    $parcial  = array_slice($dados['respostas'], 0, 20, true);

    $rascunho = $quizService->salvarRascunho(array(
        'tentativa_id' => (int) $tentativa3['id'],
        'aluno_id'     => $alunoId,
        'respostas'    => $parcial,
    ));
    expect($rascunho['ok'])->toBeTrue();

    // Simula o fim do prazo: a prova começou há mais de 330 minutos
    $pdo->prepare('UPDATE conteudo_quiz_tentativas
                   SET iniciada_em = DATE_SUB(NOW(), INTERVAL 331 MINUTE),
                       expira_em   = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                   WHERE id = :id')->execute(array('id' => (int) $tentativa3['id']));

    $stmt = $pdo->prepare('SELECT * FROM conteudo_quiz_tentativas WHERE id = :id');
    $stmt->execute(array('id' => (int) $tentativa3['id']));
    $encerramento = $quizService->encerrarSeExpirada($stmt->fetch());

    expect($encerramento['encerrada'])->toBeTrue();
    expect($encerramento['motivo'])->toBe('enviar_automatico');

    $stmt->execute(array('id' => (int) $tentativa3['id']));
    $tentativa = $stmt->fetch();

    expect((string) $tentativa['status'])->toBe('corrigida');
    expect((int) $tentativa['encerrada_por_tempo'])->toBe(1);
    expect((int) $tentativa['total_acertos'])->toBe(20);
    expect((float) $tentativa['percentual'])->toBe(25.0);
    // O tempo utilizado é limitado ao prazo da prova
    expect((int) $tentativa['tempo_utilizado_segundos'])->toBeLessThanOrEqual(330 * 60);
});

it('o envio automático ignora a obrigatoriedade da discursiva', function () use ($pdo, &$tentativa3) {
    $stmt = $pdo->prepare('SELECT status, discursiva_status FROM conteudo_quiz_tentativas WHERE id = :id');
    $stmt->execute(array('id' => (int) $tentativa3['id']));
    $tentativa = $stmt->fetch();
    expect((string) $tentativa['status'])->toBe('corrigida');
    expect((string) $tentativa['discursiva_status'])->toBe('pendente');
});

// ----------------------------------------------------------------
describe('Limite de tentativas e concorrência');
// ----------------------------------------------------------------

it('bloqueia a quarta tentativa', function () use ($quizService, $quizId, $inscricaoId, $alunoId, $cursoId) {
    $resultado = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    expect($resultado['ok'])->toBeFalse();
    expect($resultado['message'])->toContain('máximo de tentativas');
    expect($quizService->podeFazerNovaTentativa($quizId, $inscricaoId))->toBeFalse();
});

it('duas chamadas seguidas não criam tentativas duplicadas', function () use ($quizService, $pdo, $quizId, $cursoId, $outro) {
    $a = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $outro['inscricao_id'],
        'aluno_id' => $outro['aluno_id'], 'curso_evento_id' => $cursoId,
    ));
    $b = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $outro['inscricao_id'],
        'aluno_id' => $outro['aluno_id'], 'curso_evento_id' => $cursoId,
    ));

    expect((int) $a['tentativa']['id'])->toBe((int) $b['tentativa']['id']);
    expect($b['retomada'])->toBeTrue();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM conteudo_quiz_tentativas WHERE quiz_id = :q AND inscricao_id = :i');
    $stmt->execute(array('q' => $quizId, 'i' => $outro['inscricao_id']));
    expect((int) $stmt->fetchColumn())->toBe(1);
});

it('o banco impede tentativas duplicadas em corrida (chave única)', function () use ($pdo, $quizId, $cursoId, $outro) {
    $duplicado = false;
    try {
        $pdo->prepare('INSERT INTO conteudo_quiz_tentativas
                (quiz_id, curso_evento_id, inscricao_id, aluno_id, numero_tentativa, status, created_at, updated_at)
                VALUES (:q, :c, :i, :a, 1, \'em_andamento\', NOW(), NOW())')
            ->execute(array('q' => $quizId, 'c' => $cursoId, 'i' => $outro['inscricao_id'], 'a' => $outro['aluno_id']));
    } catch (PDOException $e) {
        $duplicado = (string) $e->getCode() === '23000';
    }
    expect($duplicado)->toBeTrue();
});

// ----------------------------------------------------------------
describe('Fallback auditável quando o banco não tem inéditas');
// ----------------------------------------------------------------

it('completa com questões já usadas e registra a ocorrência', function () use ($pdo, $cursoId, $moduloId, &$criado, $sufixo) {
    // Simulado pequeno: bloco de 5 questões e banco de 6
    $itemPequenoId = inserir($pdo, 'INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, status, ordem, created_at, updated_at)
                                    VALUES (:c, :m, \'quiz\', \'__TESTE_FALLBACK__\', 0, \'publicado\', 2, NOW(), NOW())',
        array('c' => $cursoId, 'm' => $moduloId));
    $quizPequenoId = inserir($pdo, 'INSERT INTO conteudo_quizzes
            (item_id, tentativas_maximas, percentual_minimo, modo_selecao, evitar_repeticao_tentativas,
             exige_aprovacao, exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio, created_at, updated_at)
            VALUES (:i, 3, 60, \'blocos\', 1, 1, 1, 1, 1, NOW(), NOW())', array('i' => $itemPequenoId));
    $blocoPequenoId = inserir($pdo, 'INSERT INTO conteudo_quiz_blocos
            (quiz_id, codigo, titulo, tipo_questao, quantidade_sortear, conta_para_percentual, obrigatorio_para_envio, ordem, status, created_at, updated_at)
            VALUES (:q, \'UNICO\', \'Bloco único\', \'multipla_escolha\', 5, 1, 1, 1, \'ativo\', NOW(), NOW())',
        array('q' => $quizPequenoId));

    criarObjetivas($pdo, $quizPequenoId, $blocoPequenoId, array('facil' => 2, 'media' => 2, 'dificil' => 2), 'Fallback');

    $dadosAluno = criarAlunoInscrito($pdo, $cursoId, '__TesteFallback__', 'fb.' . strtolower($sufixo) . '@teste.local', substr('902' . substr($sufixo, -8), 0, 11), $criado);
    $servico    = new ConteudoQuizService(new QuizRandomizerSemente(555));

    $primeira = $servico->iniciarOuRetomar(array(
        'quiz_id' => $quizPequenoId, 'inscricao_id' => $dadosAluno['inscricao_id'],
        'aluno_id' => $dadosAluno['aluno_id'], 'curso_evento_id' => $cursoId,
    ));
    expect((int) $primeira['tentativa']['sorteio_com_repeticao'])->toBe(0);

    // Encerra a primeira para poder abrir a segunda
    $pdo->prepare('UPDATE conteudo_quiz_tentativas SET status = \'corrigida\' WHERE id = :id')
        ->execute(array('id' => (int) $primeira['tentativa']['id']));

    $segunda = $servico->iniciarOuRetomar(array(
        'quiz_id' => $quizPequenoId, 'inscricao_id' => $dadosAluno['inscricao_id'],
        'aluno_id' => $dadosAluno['aluno_id'], 'curso_evento_id' => $cursoId,
    ));

    // 6 no banco, 5 já usadas: 1 inédita + 4 reaproveitadas
    expect((int) $segunda['tentativa']['sorteio_com_repeticao'])->toBe(1);
    expect($segunda['tentativa']['sorteio_auditoria_json'])->toContain('reutilizacao_por_falta_de_ineditas');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM conteudo_quiz_itens_utilizados WHERE tentativa_id = :t AND reutilizada = 1');
    $stmt->execute(array('t' => (int) $segunda['tentativa']['id']));
    expect((int) $stmt->fetchColumn())->toBe(4);
});

// ----------------------------------------------------------------
describe('Compatibilidade: quiz legado sem blocos');
// ----------------------------------------------------------------

it('quiz sem blocos continua funcionando como antes', function () use ($pdo, $cursoId, $moduloId, &$criado, $sufixo) {
    $itemLegadoId = inserir($pdo, 'INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, status, ordem, created_at, updated_at)
                                   VALUES (:c, :m, \'quiz\', \'__TESTE_LEGADO__\', 1, \'publicado\', 3, NOW(), NOW())',
        array('c' => $cursoId, 'm' => $moduloId));

    // Sem duracao, sem blocos: exatamente o comportamento anterior
    $quizLegadoId = inserir($pdo, 'INSERT INTO conteudo_quizzes
            (item_id, percentual_minimo, exige_aprovacao, exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio, created_at, updated_at)
            VALUES (:i, 70, 1, 1, 1, 1, NOW(), NOW())', array('i' => $itemLegadoId));

    $stmt = $pdo->prepare('SELECT modo_selecao, duracao_minutos FROM conteudo_quizzes WHERE id = :id');
    $stmt->execute(array('id' => $quizLegadoId));
    $config = $stmt->fetch();
    expect((string) $config['modo_selecao'])->toBe('todas');
    expect($config['duracao_minutos'])->toBeNull();

    // Duas questões legadas, sem bloco
    $perguntas = array();
    foreach (array('Capital do Brasil?', 'Quanto é 2+2?') as $indice => $enunciado) {
        $perguntaId = inserir($pdo, 'INSERT INTO conteudo_quiz_perguntas (quiz_id, enunciado, tipo, peso, obrigatoria, ordem, created_at, updated_at)
                                     VALUES (:q, :e, \'multipla_escolha\', 1, 1, :o, NOW(), NOW())',
            array('q' => $quizLegadoId, 'e' => $enunciado, 'o' => $indice + 1));
        $alternativas = array();
        foreach (array('Errada', 'Certa') as $posicao => $texto) {
            $alternativas[] = inserir($pdo, 'INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at)
                                             VALUES (:p, :t, :c, :o, NOW(), NOW())',
                array('p' => $perguntaId, 't' => $texto, 'c' => $posicao === 1 ? 1 : 0, 'o' => $posicao + 1));
        }
        $perguntas[$perguntaId] = $alternativas;
    }

    // As questões legadas ficam sem bloco
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM conteudo_quiz_perguntas WHERE quiz_id = :q AND bloco_id IS NULL');
    $stmt->execute(array('q' => $quizLegadoId));
    expect((int) $stmt->fetchColumn())->toBe(2);

    $dadosAluno = criarAlunoInscrito($pdo, $cursoId, '__TesteLegado__', 'lg.' . strtolower($sufixo) . '@teste.local', substr('903' . substr($sufixo, -8), 0, 11), $criado);
    $servico    = new ConteudoQuizService(new QuizRandomizerSemente(99));

    $tentativa = $servico->iniciarOuRetomar(array(
        'quiz_id' => $quizLegadoId, 'inscricao_id' => $dadosAluno['inscricao_id'],
        'aluno_id' => $dadosAluno['aluno_id'], 'curso_evento_id' => $cursoId,
    ));
    expect($tentativa['ok'])->toBeTrue();
    // Sem limite de tempo, como antes
    expect($tentativa['tentativa']['expira_em'])->toBeNull();
    expect((int) $tentativa['tentativa']['total_perguntas'])->toBe(2);

    $respostas = array();
    foreach ($perguntas as $perguntaId => $alternativas) {
        $respostas[$perguntaId] = $alternativas[1]; // a correta
    }

    $envio = $servico->enviarTentativa(array(
        'tentativa_id' => (int) $tentativa['tentativa']['id'],
        'aluno_id'     => $dadosAluno['aluno_id'],
        'item_id'      => $itemLegadoId,
        'inscricao_id' => $dadosAluno['inscricao_id'],
        'respostas'    => $respostas,
    ));

    expect($envio['ok'])->toBeTrue();
    expect((int) $envio['resultado']['total_acertos'])->toBe(2);
    expect((float) $envio['resultado']['percentual'])->toBe(100.0);
    expect($envio['resultado']['aprovado'])->toBeTrue();
    expect((int) $envio['resultado']['total_discursivas'])->toBe(0);
});

// ----------------------------------------------------------------
// Limpeza
// ----------------------------------------------------------------

echo "\n  Limpando dados de teste...\n";
try {
    $usuarios = implode(',', array_map('intval', $criado['usuarios']));
    $pdo->exec("DELETE FROM conteudo_quiz_correcoes_discursivas_historico WHERE correcao_id IN
                (SELECT id FROM conteudo_quiz_correcoes_discursivas WHERE tentativa_id IN
                 (SELECT id FROM conteudo_quiz_tentativas WHERE curso_evento_id = {$cursoId}))");
    $pdo->exec("DELETE FROM conteudo_quiz_correcoes_discursivas WHERE tentativa_id IN
                (SELECT id FROM conteudo_quiz_tentativas WHERE curso_evento_id = {$cursoId})");
    $pdo->exec("DELETE FROM conteudo_quiz_itens_utilizados WHERE tentativa_id IN
                (SELECT id FROM conteudo_quiz_tentativas WHERE curso_evento_id = {$cursoId})");
    $pdo->exec("DELETE FROM conteudo_quiz_respostas WHERE tentativa_id IN
                (SELECT id FROM conteudo_quiz_tentativas WHERE curso_evento_id = {$cursoId})");
    $pdo->exec("DELETE FROM conteudo_quiz_tentativas WHERE curso_evento_id = {$cursoId}");
    $pdo->exec("DELETE FROM conteudo_quiz_alternativas WHERE pergunta_id IN
                (SELECT id FROM conteudo_quiz_perguntas WHERE quiz_id IN
                 (SELECT id FROM conteudo_quizzes WHERE item_id IN
                  (SELECT id FROM conteudo_itens WHERE curso_evento_id = {$cursoId})))");
    $pdo->exec("DELETE FROM conteudo_quiz_perguntas WHERE quiz_id IN
                (SELECT id FROM conteudo_quizzes WHERE item_id IN
                 (SELECT id FROM conteudo_itens WHERE curso_evento_id = {$cursoId}))");
    $pdo->exec("DELETE FROM conteudo_quiz_blocos WHERE quiz_id IN
                (SELECT id FROM conteudo_quizzes WHERE item_id IN
                 (SELECT id FROM conteudo_itens WHERE curso_evento_id = {$cursoId}))");
    $pdo->exec("DELETE FROM conteudo_quizzes WHERE item_id IN (SELECT id FROM conteudo_itens WHERE curso_evento_id = {$cursoId})");
    $pdo->exec("DELETE FROM conteudo_progresso_aluno WHERE curso_evento_id = {$cursoId}");
    $pdo->exec("DELETE FROM conteudo_itens WHERE curso_evento_id = {$cursoId}");
    $pdo->exec("DELETE FROM conteudo_modulos WHERE curso_evento_id = {$cursoId}");
    $pdo->exec("DELETE FROM inscricoes WHERE curso_evento_id = {$cursoId}");
    $pdo->exec("DELETE FROM participantes_pedido WHERE pedido_id IN (SELECT id FROM pedidos WHERE comprador_usuario_id IN ({$usuarios}))");
    $pdo->exec("DELETE FROM pedido_itens WHERE curso_evento_id = {$cursoId}");
    $pdo->exec("DELETE FROM pedidos WHERE comprador_usuario_id IN ({$usuarios})");
    $pdo->exec("DELETE FROM auditoria_logs WHERE usuario_id IN ({$usuarios})");
    $pdo->exec("DELETE FROM cursos_eventos WHERE id = {$cursoId}");
    $pdo->exec("DELETE FROM usuarios WHERE id IN ({$usuarios})");
} catch (Exception $e) {
    echo "  AVISO na limpeza: " . $e->getMessage() . "\n";
}

exit(testes_resumo());

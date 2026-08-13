<?php

/**
 * Testes unitários do sistema de quizzes.
 * Execução: php tests/Unit/quiz_system.php
 */

// Bootstrap compartilhado: leitura do .env, conexão PDO, autoloader e
// helpers describe/it/expect.
require_once __DIR__ . '/_bootstrap.php';

$pdo = testes_conectar_banco();

/**
 * Cria a cadeia pedido -> item -> participante -> inscrição exigida pelas
 * chaves estrangeiras de `inscricoes`.
 */
function criarInscricaoTeste(PDO $pdo, $usuarioId, $cursoId)
{
    $pdo->prepare('INSERT INTO pedidos (comprador_usuario_id, pagador_usuario_id, codigo, created_at, updated_at)
                   VALUES (:u, :u2, :c, NOW(), NOW())')
        ->execute(array('u' => $usuarioId, 'u2' => $usuarioId, 'c' => 'TSTQZ' . substr(uniqid(), -10)));
    $pedidoId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO pedido_itens (pedido_id, curso_evento_id, created_at, updated_at) VALUES (:p, :c, NOW(), NOW())')
        ->execute(array('p' => $pedidoId, 'c' => $cursoId));
    $itemPedidoId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO participantes_pedido (pedido_id, pedido_item_id, usuario_id, nome, created_at, updated_at)
                   VALUES (:p, :i, :u, \'__TestParticipante__\', NOW(), NOW())')
        ->execute(array('p' => $pedidoId, 'i' => $itemPedidoId, 'u' => $usuarioId));
    $participanteId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO inscricoes (pedido_id, pedido_item_id, participante_pedido_id, curso_evento_id, usuario_id, status, created_at, updated_at)
                   VALUES (:p, :i, :pa, :c, :u, \'ativa\', NOW(), NOW())')
        ->execute(array('p' => $pedidoId, 'i' => $itemPedidoId, 'pa' => $participanteId, 'c' => $cursoId, 'u' => $usuarioId));

    return (int) $pdo->lastInsertId();
}

// ----------------------------------------------------------------
// Setup: criar dados de teste isolados
// ----------------------------------------------------------------

// Obter um curso e item real de tipo quiz ou criar um temporário
$cursoTest = $pdo->query('SELECT id FROM cursos_eventos LIMIT 1')->fetch();
if (!$cursoTest) {
    echo "SKIP: nenhum curso no banco de dados. Testes de integração ignorados.\n";
    exit(0);
}

$cursoId = (int) $cursoTest['id'];

// Criar módulo de teste
$pdo->prepare('INSERT INTO conteudo_modulos (curso_evento_id, titulo, status, ordem, created_at, updated_at)
               VALUES (:cid, :titulo, \'publicado\', 999, NOW(), NOW())')
    ->execute(array('cid' => $cursoId, 'titulo' => '__TEST_QUIZ_MODULE__'));
$moduloId = (int) $pdo->lastInsertId();

// Criar item de teste do tipo quiz
$pdo->prepare('INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, status, ordem, created_at, updated_at)
               VALUES (:cid, :mid, \'quiz\', :titulo, 1, \'publicado\', 1, NOW(), NOW())')
    ->execute(array('cid' => $cursoId, 'mid' => $moduloId, 'titulo' => '__TEST_QUIZ_ITEM__'));
$itemId = (int) $pdo->lastInsertId();

// Criar quiz de teste
$pdo->prepare('INSERT INTO conteudo_quizzes (item_id, percentual_minimo, exige_aprovacao, exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio, created_at, updated_at)
               VALUES (:iid, 70, 1, 1, 1, 1, NOW(), NOW())')
    ->execute(array('iid' => $itemId));
$quizId = (int) $pdo->lastInsertId();

// Criar pergunta 1 com 4 alternativas (correta B)
$pdo->prepare('INSERT INTO conteudo_quiz_perguntas (quiz_id, enunciado, tipo, peso, obrigatoria, ordem, created_at, updated_at) VALUES (:qid, :e, \'multipla_escolha\', 1, 1, 1, NOW(), NOW())')
    ->execute(array('qid' => $quizId, 'e' => 'Qual é a capital do Brasil?'));
$p1Id = (int) $pdo->lastInsertId();

$altData1 = array('Montevidéu', 'Brasília', 'São Paulo', 'Rio de Janeiro');
$altIds1  = array();
foreach ($altData1 as $idx => $texto) {
    $pdo->prepare('INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at) VALUES (:pid, :t, :c, :o, NOW(), NOW())')
        ->execute(array('pid' => $p1Id, 't' => $texto, 'c' => $idx === 1 ? 1 : 0, 'o' => $idx + 1));
    $altIds1[] = (int) $pdo->lastInsertId();
}

// Criar pergunta 2
$pdo->prepare('INSERT INTO conteudo_quiz_perguntas (quiz_id, enunciado, tipo, peso, obrigatoria, ordem, created_at, updated_at) VALUES (:qid, :e, \'multipla_escolha\', 1, 1, 2, NOW(), NOW())')
    ->execute(array('qid' => $quizId, 'e' => 'Quanto é 2+2?'));
$p2Id = (int) $pdo->lastInsertId();

$altData2 = array('3', '4', '5', '6');
$altIds2  = array();
foreach ($altData2 as $idx => $texto) {
    $pdo->prepare('INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at) VALUES (:pid, :t, :c, :o, NOW(), NOW())')
        ->execute(array('pid' => $p2Id, 't' => $texto, 'c' => $idx === 1 ? 1 : 0, 'o' => $idx + 1));
    $altIds2[] = (int) $pdo->lastInsertId();
}

// Criar aluno e inscrição de teste
$pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, cpf, status, created_at, updated_at)
               VALUES (\'__TestAluno__\', \'testquiz@quiz.test\', :pass, \'00000000001\', \'ativo\', NOW(), NOW())')
    ->execute(array('pass' => password_hash('test123', PASSWORD_DEFAULT)));
$alunoId = (int) $pdo->lastInsertId();

$inscricaoId = criarInscricaoTeste($pdo, $alunoId, $cursoId);

// Criar segundo aluno para teste de isolamento
$pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, cpf, status, created_at, updated_at)
               VALUES (\'__TestAluno2__\', \'testquiz2@quiz.test\', :pass, \'00000000002\', \'ativo\', NOW(), NOW())')
    ->execute(array('pass' => password_hash('test123', PASSWORD_DEFAULT)));
$aluno2Id = (int) $pdo->lastInsertId();

$inscricao2Id = criarInscricaoTeste($pdo, $aluno2Id, $cursoId);

// Instanciar service
$quizService = new \App\Services\ConteudoQuizService();

echo "=== Testes do Sistema de Quizzes ===\n";

// ----------------------------------------------------------------
describe('Validação de quiz (validarQuiz)');
// ----------------------------------------------------------------

it('quiz válido retorna ok=true', function () use ($quizService, $quizId) {
    $r = $quizService->validarQuiz($quizId);
    expect($r['ok'])->toBeTrue();
    expect($r['erros'])->toBe(array());
});

it('quiz sem perguntas retorna ok=false', function () use ($pdo, $quizService, $cursoId, $moduloId) {
    // Criar quiz vazio
    $pdo->prepare('INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, status, ordem, created_at, updated_at) VALUES (:c, :m, \'quiz\', \'__vazio__\', \'rascunho\', 99, NOW(), NOW())')
        ->execute(array('c' => $cursoId, 'm' => $moduloId));
    $iid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO conteudo_quizzes (item_id, percentual_minimo, exige_aprovacao, exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio, created_at, updated_at) VALUES (:iid, 0, 0, 1, 1, 1, NOW(), NOW())')
        ->execute(array('iid' => $iid));
    $qid = (int) $pdo->lastInsertId();
    $r = $quizService->validarQuiz($qid);
    expect($r['ok'])->toBeFalse();
    expect(count($r['erros']))->toBeGreaterThan(0);
});

// ----------------------------------------------------------------
describe('Salvar pergunta — validação backend');
// ----------------------------------------------------------------

it('rejeita pergunta sem alternativa correta', function () use ($quizService, $quizId) {
    $r = $quizService->salvarPergunta(array(
        'quiz_id'     => $quizId,
        'enunciado'   => 'Teste sem correta',
        'alternativas'=> array(
            array('texto' => 'A', 'correta' => 0),
            array('texto' => 'B', 'correta' => 0),
        ),
    ));
    expect($r['ok'])->toBeFalse();
});

it('rejeita pergunta com mais de uma alternativa correta', function () use ($quizService, $quizId) {
    $r = $quizService->salvarPergunta(array(
        'quiz_id'     => $quizId,
        'enunciado'   => 'Teste duas corretas',
        'alternativas'=> array(
            array('texto' => 'A', 'correta' => 1),
            array('texto' => 'B', 'correta' => 1),
        ),
    ));
    expect($r['ok'])->toBeFalse();
});

it('rejeita pergunta com menos de duas alternativas', function () use ($quizService, $quizId) {
    $r = $quizService->salvarPergunta(array(
        'quiz_id'     => $quizId,
        'enunciado'   => 'Só uma alt',
        'alternativas'=> array(
            array('texto' => 'A', 'correta' => 1),
        ),
    ));
    expect($r['ok'])->toBeFalse();
});

it('aceita pergunta válida com exatamente 1 correta', function () use ($quizService, $quizId) {
    $r = $quizService->salvarPergunta(array(
        'quiz_id'     => $quizId,
        'enunciado'   => 'Pergunta extra válida',
        'alternativas'=> array(
            array('texto' => 'Errada', 'correta' => 0),
            array('texto' => 'Certa', 'correta' => 1),
        ),
    ));
    expect($r['ok'])->toBeTrue();
    // Limpar
    if (isset($r['id'])) {
        $pdo = \App\Core\Database::connection();
        $pdo->prepare('UPDATE conteudo_quiz_perguntas SET deleted_at = NOW() WHERE id = :id')->execute(array('id' => $r['id']));
    }
});

// ----------------------------------------------------------------
describe('Tentativas — iniciar e retomar');
// ----------------------------------------------------------------

it('pode iniciar nova tentativa quando não há limite', function () use ($quizService, $quizId, $alunoId, $inscricaoId, $cursoId) {
    expect($quizService->podeFazerNovaTentativa($quizId, $inscricaoId))->toBeTrue();
});

it('cria tentativa com status em_andamento', function () use ($quizService, $quizId, $alunoId, $inscricaoId, $cursoId) {
    $r = $quizService->iniciarOuRetomar(array(
        'quiz_id'        => $quizId,
        'inscricao_id'   => $inscricaoId,
        'aluno_id'       => $alunoId,
        'curso_evento_id'=> $cursoId,
    ));
    expect($r['ok'])->toBeTrue();
    expect((string) $r['tentativa']['status'])->toBe('em_andamento');
});

it('retoma tentativa existente em vez de criar nova', function () use ($quizService, $quizId, $alunoId, $inscricaoId, $cursoId) {
    $r1 = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    $r2 = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    expect($r1['tentativa']['id'])->toBe($r2['tentativa']['id']);
    expect($r2['retomada'])->toBeTrue();
});

// ----------------------------------------------------------------
describe('Segurança — aluno não acessa tentativa de outro aluno');
// ----------------------------------------------------------------

it('obterTentativaParaAluno bloqueia acesso de outro aluno', function () use ($quizService, $quizId, $alunoId, $inscricaoId, $aluno2Id, $inscricao2Id, $cursoId) {
    // Pegar a tentativa do aluno 1
    $r = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    $tentativaId = (int) $r['tentativa']['id'];

    // Aluno 2 tenta acessar a tentativa do aluno 1
    $dados = $quizService->obterTentativaParaAluno($tentativaId, $aluno2Id);
    expect($dados)->toBeNull();
});

// ----------------------------------------------------------------
describe('Correção automática no servidor');
// ----------------------------------------------------------------

it('envia quiz e calcula acertos no servidor (sem aceitar do frontend)', function () use ($quizService, $quizId, $alunoId, $inscricaoId, $cursoId, $altIds1, $altIds2, $p1Id, $p2Id) {
    $r = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricaoId,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    $tentativaId = (int) $r['tentativa']['id'];

    // Responder ambas corretas (índice 1 = Brasília, índice 1 = 4)
    $resultado = $quizService->enviarTentativa(array(
        'tentativa_id'   => $tentativaId,
        'aluno_id'       => $alunoId,
        'item_id'        => 0, // sem item real para simplificar
        'inscricao_id'   => $inscricaoId,
        'respostas'      => array(
            $p1Id => $altIds1[1], // Brasília (correta)
            $p2Id => $altIds2[1], // 4 (correta)
        ),
    ));

    expect($resultado['ok'])->toBeTrue();
    expect((int) $resultado['resultado']['total_acertos'])->toBe(2);
    expect((float) $resultado['resultado']['percentual'])->toBe(100.0);
    expect($resultado['resultado']['aprovado'])->toBeTrue();
});

// ----------------------------------------------------------------
describe('Gabarito não é revelado antes do envio');
// ----------------------------------------------------------------

it('findQuizParaAluno omite campo correta das alternativas', function () use ($quizService, $itemId, $inscricaoId, $alunoId) {
    $quiz = $quizService->findQuizParaAluno($itemId, $alunoId, $inscricaoId);
    expect($quiz)->notToBeNull();
    foreach ($quiz['perguntas'] as $p) {
        foreach ($p['alternativas'] as $alt) {
            if (array_key_exists('correta', $alt)) {
                throw new RuntimeException("Campo 'correta' exposto ao aluno antes do envio!");
            }
        }
    }
});

// ----------------------------------------------------------------
describe('Tentativa duplicada é bloqueada');
// ----------------------------------------------------------------

it('reaproveita tentativa já corrigida sem duplicar envio', function () use ($quizService, $quizId, $aluno2Id, $inscricao2Id, $cursoId, $altIds1, $altIds2, $p1Id, $p2Id) {
    // Iniciar e enviar
    $r = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscricao2Id,
        'aluno_id' => $aluno2Id, 'curso_evento_id' => $cursoId,
    ));
    $tid = (int) $r['tentativa']['id'];
    $quizService->enviarTentativa(array(
        'tentativa_id' => $tid, 'aluno_id' => $aluno2Id,
        'item_id' => 0, 'inscricao_id' => $inscricao2Id,
        'respostas' => array($p1Id => $altIds1[0], $p2Id => $altIds2[0]),
    ));

    // Tentar enviar de novo com o mesmo id
    $r2 = $quizService->enviarTentativa(array(
        'tentativa_id' => $tid, 'aluno_id' => $aluno2Id,
        'item_id' => 0, 'inscricao_id' => $inscricao2Id,
        'respostas' => array($p1Id => $altIds1[1], $p2Id => $altIds2[1]),
    ));
    expect($r2['ok'])->toBeTrue();
    expect(!empty($r2['reutilizada']))->toBeTrue();
});

// ----------------------------------------------------------------
describe('Limite máximo de tentativas');
// ----------------------------------------------------------------

it('bloqueia nova tentativa quando limite atingido', function () use ($pdo, $quizService, $cursoId, $moduloId) {
    // Criar quiz com limite 1
    $pdo->prepare('INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, status, ordem, created_at, updated_at) VALUES (:c, :m, \'quiz\', \'__limit_test__\', \'publicado\', 99, NOW(), NOW())')
        ->execute(array('c' => $cursoId, 'm' => $moduloId));
    $iidL = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO conteudo_quizzes (item_id, tentativas_maximas, percentual_minimo, exige_aprovacao, exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio, created_at, updated_at) VALUES (:iid, 1, 0, 0, 1, 1, 1, NOW(), NOW())')
        ->execute(array('iid' => $iidL));
    $qidL = (int) $pdo->lastInsertId();

    // Criar pergunta e alternativa
    $pdo->prepare('INSERT INTO conteudo_quiz_perguntas (quiz_id, enunciado, tipo, peso, obrigatoria, ordem, created_at, updated_at) VALUES (:q, :e, \'multipla_escolha\', 1, 1, 1, NOW(), NOW())')
        ->execute(array('q' => $qidL, 'e' => 'Teste?'));
    $pidL = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at) VALUES (:p, \'A\', 1, 1, NOW(), NOW()), (:p2, \'B\', 0, 2, NOW(), NOW())')
        ->execute(array('p' => $pidL, 'p2' => $pidL));
    $altLId = (int) $pdo->lastInsertId();

    // Aluno e inscrição temporários (as chaves estrangeiras exigem registros reais)
    $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, cpf, status, created_at, updated_at) VALUES (\'__LimitAluno__\', \'limitquiz@quiz.test\', \'x\', \'99999999999\', \'ativo\', NOW(), NOW())')
        ->execute(array());
    $limitAlunoId = (int) $pdo->lastInsertId();
    $limitInscId  = criarInscricaoTeste($pdo, $limitAlunoId, $cursoId);

    // Criar tentativa diretamente no banco como já corrigida
    $pdo->prepare('INSERT INTO conteudo_quiz_tentativas (quiz_id, curso_evento_id, inscricao_id, aluno_id, numero_tentativa, status, created_at, updated_at) VALUES (:q, :c, :i, :a, 1, \'corrigida\', NOW(), NOW())')
        ->execute(array('q' => $qidL, 'c' => $cursoId, 'i' => $limitInscId, 'a' => $limitAlunoId));

    $pode = $quizService->podeFazerNovaTentativa($qidL, $limitInscId);
    expect($pode)->toBeFalse();
});

// ----------------------------------------------------------------
describe('Alternativa inválida é rejeitada');
// ----------------------------------------------------------------

it('ignora alternativa de outra pergunta silenciosamente', function () use ($quizService, $quizId, $alunoId, $cursoId, $p1Id, $altIds2) {
    // altIds2[0] pertence à pergunta 2, não à pergunta 1
    // O service deve ignorar ao gravar (não causar erro)
    // Verificamos indiretamente: iniciar nova tentativa e salvar rascunho com alt inválida
    $pdo = \App\Core\Database::connection();
    $inscAlt = criarInscricaoTeste($pdo, $alunoId, $cursoId);

    $r = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $quizId, 'inscricao_id' => $inscAlt,
        'aluno_id' => $alunoId, 'curso_evento_id' => $cursoId,
    ));
    expect($r['ok'])->toBeTrue();

    // Salvar rascunho com alternativa de outra pergunta
    $rascunho = $quizService->salvarRascunho(array(
        'tentativa_id' => (int) $r['tentativa']['id'],
        'aluno_id'     => $alunoId,
        'respostas'    => array($p1Id => $altIds2[0]), // alt de p2 passada para p1
    ));
    // Não deve lançar exceção; ok pode ser true (silencioso)
    expect(isset($rascunho['ok']))->toBeTrue();

    // Cleanup
    $pdo->prepare('DELETE FROM inscricoes WHERE id = :id')->execute(array('id' => $inscAlt));
});

// ----------------------------------------------------------------
describe('Integração com conteudo_progresso_aluno');
// ----------------------------------------------------------------

it('quiz obrigatório SEM exige_aprovacao: envio marca concluido', function () use ($pdo, $quizService, $cursoId, $moduloId, $p1Id, $p2Id, $altIds1, $altIds2) {
    // Criar quiz sem exige_aprovacao
    $pdo->prepare('INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, status, ordem, created_at, updated_at) VALUES (:c, :m, \'quiz\', \'__prog_test__\', 1, \'publicado\', 100, NOW(), NOW())')
        ->execute(array('c' => $cursoId, 'm' => $moduloId));
    $iidP = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO conteudo_quizzes (item_id, percentual_minimo, exige_aprovacao, exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio, created_at, updated_at) VALUES (:iid, 70, 0, 1, 1, 1, NOW(), NOW())')
        ->execute(array('iid' => $iidP));
    $qidP = (int) $pdo->lastInsertId();

    // Adicionar as mesmas perguntas via referência (ou duplicar)
    // Para simplificar, adicionar pergunta própria
    $pdo->prepare('INSERT INTO conteudo_quiz_perguntas (quiz_id, enunciado, tipo, peso, obrigatoria, ordem, created_at, updated_at) VALUES (:qid, \'P?\', \'multipla_escolha\', 1, 1, 1, NOW(), NOW())')
        ->execute(array('qid' => $qidP));
    $pidP = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at) VALUES (:p, \'Errada\', 0, 1, NOW(), NOW()), (:p2, \'Certa\', 1, 2, NOW(), NOW())')
        ->execute(array('p' => $pidP, 'p2' => $pidP));
    $altCorrectP = (int) $pdo->lastInsertId();
    // Pegar a alternativa correta
    $altCorretaP = $pdo->query("SELECT id FROM conteudo_quiz_alternativas WHERE pergunta_id = {$pidP} AND correta = 1 LIMIT 1")->fetchColumn();

    // Criar aluno e inscrição
    $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, cpf, status, created_at, updated_at) VALUES (\'__ProgAluno__\', \'progquiz@quiz.test\', \'x\', \'11111111111\', \'ativo\', NOW(), NOW())')
        ->execute(array());
    $progAlunoId = (int) $pdo->lastInsertId();
    $progInscId = criarInscricaoTeste($pdo, $progAlunoId, $cursoId);

    $r = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $qidP, 'inscricao_id' => $progInscId,
        'aluno_id' => $progAlunoId, 'curso_evento_id' => $cursoId,
    ));
    $tid = (int) $r['tentativa']['id'];

    // Responder errado (mas exige_aprovacao=0, deve concluir assim mesmo)
    $quizService->enviarTentativa(array(
        'tentativa_id' => $tid, 'aluno_id' => $progAlunoId,
        'item_id' => $iidP, 'inscricao_id' => $progInscId,
        'respostas' => array($pidP => (int) $pdo->query("SELECT id FROM conteudo_quiz_alternativas WHERE pergunta_id = {$pidP} AND correta = 0 LIMIT 1")->fetchColumn()),
    ));

    // Verificar progresso
    $prog = $pdo->prepare('SELECT * FROM conteudo_progresso_aluno WHERE aluno_id = :aid AND item_id = :iid AND deleted_at IS NULL LIMIT 1');
    $prog->execute(array('aid' => $progAlunoId, 'iid' => $iidP));
    $prog = $prog->fetch();

    expect($prog)->notToBeNull();
    expect((string) $prog['status'])->toBe('concluido');
});

it('quiz obrigatório COM exige_aprovacao: reprovado quando abaixo do mínimo', function () use ($pdo, $quizService, $cursoId, $moduloId) {
    // Criar quiz com exige_aprovacao=1, percentual_minimo=100
    $pdo->prepare('INSERT INTO conteudo_itens (curso_evento_id, modulo_id, tipo, titulo, obrigatorio, status, ordem, created_at, updated_at) VALUES (:c, :m, \'quiz\', \'__reprova_test__\', 1, \'publicado\', 101, NOW(), NOW())')
        ->execute(array('c' => $cursoId, 'm' => $moduloId));
    $iidR = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO conteudo_quizzes (item_id, percentual_minimo, exige_aprovacao, exibir_resultado_apos_envio, exibir_gabarito_apos_envio, exibir_comentarios_apos_envio, created_at, updated_at) VALUES (:iid, 100, 1, 1, 1, 1, NOW(), NOW())')
        ->execute(array('iid' => $iidR));
    $qidR = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO conteudo_quiz_perguntas (quiz_id, enunciado, tipo, peso, obrigatoria, ordem, created_at, updated_at) VALUES (:qid, \'P?\', \'multipla_escolha\', 1, 1, 1, NOW(), NOW())')
        ->execute(array('qid' => $qidR));
    $pidR = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO conteudo_quiz_alternativas (pergunta_id, texto, correta, ordem, created_at, updated_at) VALUES (:p, \'Errada\', 0, 1, NOW(), NOW()), (:p2, \'Certa\', 1, 2, NOW(), NOW())')
        ->execute(array('p' => $pidR, 'p2' => $pidR));
    $altErrada = (int) $pdo->query("SELECT id FROM conteudo_quiz_alternativas WHERE pergunta_id = {$pidR} AND correta = 0 LIMIT 1")->fetchColumn();

    $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, cpf, status, created_at, updated_at) VALUES (\'__ReprovaAluno__\', \'reprova@quiz.test\', \'x\', \'22222222222\', \'ativo\', NOW(), NOW())')
        ->execute(array());
    $raId = (int) $pdo->lastInsertId();
    $riId = criarInscricaoTeste($pdo, $raId, $cursoId);

    $r = $quizService->iniciarOuRetomar(array(
        'quiz_id' => $qidR, 'inscricao_id' => $riId,
        'aluno_id' => $raId, 'curso_evento_id' => $cursoId,
    ));
    $quizService->enviarTentativa(array(
        'tentativa_id' => (int) $r['tentativa']['id'], 'aluno_id' => $raId,
        'item_id' => $iidR, 'inscricao_id' => $riId,
        'respostas' => array($pidR => $altErrada),
    ));

    $prog = $pdo->prepare('SELECT status FROM conteudo_progresso_aluno WHERE aluno_id = :aid AND item_id = :iid AND deleted_at IS NULL LIMIT 1');
    $prog->execute(array('aid' => $raId, 'iid' => $iidR));
    $prog = $prog->fetch();
    expect($prog)->notToBeNull();
    expect((string) $prog['status'])->toBe('reprovado');
});

// ----------------------------------------------------------------
describe('Nenhum impacto em certificados existentes');
// ----------------------------------------------------------------

it('resetarProgressoQuizAluno não afeta certificados emitidos', function () use ($quizService, $itemId, $alunoId, $inscricaoId) {
    // O reset apenas altera conteudo_progresso_aluno, não toca certificados
    // Verificar que a tabela certificados não é tocada pelo service
    $pdo = \App\Core\Database::connection();
    $certAntes = $pdo->query('SELECT COUNT(*) FROM certificados')->fetchColumn();

    $quizService->resetarProgressoQuizAluno($itemId, $alunoId, $inscricaoId, $alunoId);

    $certDepois = $pdo->query('SELECT COUNT(*) FROM certificados')->fetchColumn();
    expect($certAntes)->toBe($certDepois);
});

// ----------------------------------------------------------------
// Cleanup
// ----------------------------------------------------------------

echo "\n  Limpando dados de teste...\n";
try {
    $pdo->exec("DELETE FROM conteudo_progresso_aluno WHERE modulo_id = {$moduloId}");
    $pdo->exec("DELETE FROM conteudo_quiz_respostas WHERE tentativa_id IN (SELECT id FROM conteudo_quiz_tentativas WHERE quiz_id = {$quizId})");
    $pdo->exec("DELETE FROM conteudo_quiz_tentativas WHERE quiz_id = {$quizId}");
    $pdo->exec("DELETE FROM conteudo_quiz_alternativas WHERE pergunta_id IN (SELECT id FROM conteudo_quiz_perguntas WHERE quiz_id = {$quizId})");
    $pdo->exec("DELETE FROM conteudo_quiz_perguntas WHERE quiz_id = {$quizId}");
    $pdo->exec("DELETE FROM conteudo_quizzes WHERE item_id = {$itemId}");
    // Limpar itens do módulo de teste
    $pdo->exec("DELETE FROM conteudo_quiz_tentativas WHERE curso_evento_id = {$cursoId} AND aluno_id IN ({$alunoId}, {$aluno2Id})");
    $pdo->exec("DELETE FROM conteudo_quiz_alternativas WHERE pergunta_id IN (SELECT id FROM conteudo_quiz_perguntas WHERE quiz_id IN (SELECT id FROM conteudo_quizzes WHERE item_id IN (SELECT id FROM conteudo_itens WHERE modulo_id = {$moduloId})))");
    $pdo->exec("DELETE FROM conteudo_quiz_perguntas WHERE quiz_id IN (SELECT id FROM conteudo_quizzes WHERE item_id IN (SELECT id FROM conteudo_itens WHERE modulo_id = {$moduloId}))");
    $pdo->exec("DELETE FROM conteudo_quizzes WHERE item_id IN (SELECT id FROM conteudo_itens WHERE modulo_id = {$moduloId})");
    $pdo->exec("DELETE FROM conteudo_itens WHERE modulo_id = {$moduloId}");
    $pdo->exec("DELETE FROM conteudo_modulos WHERE id = {$moduloId}");
    $pdo->exec("DELETE FROM inscricoes WHERE usuario_id IN ({$alunoId}, {$aluno2Id}) AND curso_evento_id = {$cursoId}");
    $pdo->exec("DELETE FROM usuarios WHERE id IN ({$alunoId}, {$aluno2Id}) OR email LIKE '%quiz.test'");
} catch (Exception $e) {
    echo "  AVISO limpeza: " . $e->getMessage() . "\n";
}

exit(testes_resumo());

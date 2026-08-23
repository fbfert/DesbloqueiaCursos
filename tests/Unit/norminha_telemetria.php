<?php

/**
 * NorminhaTelemetriaService — os números do Checkpoint 0 (Etapa 8).
 *
 * O que este teste protege:
 *
 * 1. QUE A JANELA FILTRE DE VERDADE. Um painel que soma tudo desde sempre não
 *    responde "e nos últimos 14 dias?", que é a pergunta do checkpoint.
 * 2. QUE A PERGUNTA CERTA APAREÇA. A lista de não resolvidas precisa trazer a
 *    mensagem do ALUNO, não a resposta da Norminha.
 * 3. QUE NADA PESSOAL VAZE para a tela do admin.
 *
 * Execução: php tests/Unit/norminha_telemetria.php
 * Roda em transação e desfaz tudo.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\NorminhaConversa;
use App\Models\NorminhaFeedback;
use App\Models\NorminhaMensagem;
use App\Models\NorminhaUso;
use App\Services\NorminhaTelemetriaService;

$pdo = testes_conectar_banco();
$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nBanco: {$banco}\n";
if ($banco === 'desbloqueiacursos') {
    fwrite(STDERR, "\nRECUSADO: este teste escreve.\n");
    exit(1);
}

const ALUNO_T = 999801;
const ALUNO_T2 = 999802;

$pdo->beginTransaction();

// Estado conhecido DENTRO da transação. O banco de desenvolvimento acumula
// conversas de uso manual, e um painel testado contra dados de origem
// desconhecida não prova nada: os números têm de ser exatamente os que este
// teste criou. O rollback ao final devolve tudo.
$pdo->exec('DELETE FROM norminha_feedback');
$pdo->exec('DELETE FROM norminha_mensagens');
$pdo->exec('DELETE FROM norminha_conversas');
$pdo->exec('DELETE FROM norminha_uso');

$conversas = new NorminhaConversa();
$mensagens = new NorminhaMensagem();
$feedbacks = new NorminhaFeedback();
$usos = new NorminhaUso();
$tele = new NorminhaTelemetriaService();

// Base do período: 3 fast-paths + 2 não resolvidas, do aluno A.
$c1 = $conversas->criar(ALUNO_T, array('inscricao_id' => 4242, 'curso_evento_id' => 9, 'contexto' => 'aula', 'rota' => '/v2/aula/'));

$mensagens->inserir($c1['id'], 'user', 'meu progresso', array('usuario_id' => ALUNO_T));
$mensagens->inserir($c1['id'], 'assistant', 'Você está com 40%.', array('intencao' => 'show_progress', 'resolved_by' => 'php'));

$mensagens->inserir($c1['id'], 'user', 'onde parei', array('usuario_id' => ALUNO_T));
$mensagens->inserir($c1['id'], 'assistant', 'Você parou em X.', array('intencao' => 'resume_course', 'resolved_by' => 'php'));

$mensagens->inserir($c1['id'], 'user', 'meu certificado', array('usuario_id' => ALUNO_T));
$idCert = $mensagens->inserir($c1['id'], 'assistant', 'Ainda não.', array('intencao' => 'certificate_status', 'resolved_by' => 'php'));

$PERGUNTA_CONTEUDO = 'nao entendi o metodo comparativo direto';
$mensagens->inserir($c1['id'], 'user', $PERGUNTA_CONTEUDO, array('usuario_id' => ALUNO_T));
$idNao1 = $mensagens->inserir($c1['id'], 'assistant', 'Ainda não consigo responder isso.', array('resolved_by' => 'unresolved'));

$PERGUNTA_NAVEGACAO = 'onde emito a segunda via do boleto';
$mensagens->inserir($c1['id'], 'user', $PERGUNTA_NAVEGACAO, array('usuario_id' => ALUNO_T));
$idNao2 = $mensagens->inserir($c1['id'], 'assistant', 'Ainda não consigo responder isso.', array('resolved_by' => 'unresolved'));

// Segundo aluno, para contagem de distintos.
$c2 = $conversas->criar(ALUNO_T2, array('inscricao_id' => 5, 'curso_evento_id' => 9, 'contexto' => 'area_aluno'));
$mensagens->inserir($c2['id'], 'user', 'quanto falta', array('usuario_id' => ALUNO_T2));
$mensagens->inserir($c2['id'], 'assistant', '10%.', array('intencao' => 'show_progress', 'resolved_by' => 'php'));

$feedbacks->registrar($idCert, ALUNO_T, true);
$feedbacks->registrar($idNao1, ALUNO_T, false);

$usos->registrar(ALUNO_T, false, 300);
$usos->registrarBloqueio(ALUNO_T);
$usos->registrarBloqueio(ALUNO_T);

// Ruído FORA da janela: 200 dias atrás. Nada disso pode entrar num painel de 14 dias.
$pdo->exec('UPDATE norminha_mensagens SET created_at = DATE_SUB(NOW(), INTERVAL 200 DAY)
            WHERE conversa_id = ' . (int) $c2['id']);
$pdo->exec('UPDATE norminha_conversas SET created_at = DATE_SUB(NOW(), INTERVAL 200 DAY)
            WHERE id = ' . (int) $c2['id']);

// ===================================================================

describe('A janela filtra de verdade');

it('mensagens antigas não entram no período curto', function () use ($tele) {
    $curto = $tele->volume(14);
    $longo = $tele->volume(365);

    // o aluno B so tem mensagem de 200 dias atras
    expect($longo['mensagens'] > $curto['mensagens'])->toBeTrue();
    expect($longo['alunos'] > $curto['alunos'])->toBeTrue();
    echo "      14 dias: {$curto['mensagens']} msg / {$curto['alunos']} alunos"
        . " | 365 dias: {$longo['mensagens']} msg / {$longo['alunos']} alunos\n";
});

it('conversas antigas também ficam de fora', function () use ($tele) {
    expect($tele->volume(365)['conversas'] > $tele->volume(14)['conversas'])->toBeTrue();
});

it('a janela é limitada a faixa sã', function () use ($tele) {
    // interpolada no SQL: precisa ser inteiro entre 1 e 365
    expect($tele->volume(0)['conversas'])->toBeGreaterThanOrEqual(0);
    expect($tele->volume(-5)['conversas'])->toBeGreaterThanOrEqual(0);
    expect($tele->volume(99999)['conversas'])->toBeGreaterThanOrEqual(0);
    expect($tele->volume('1 OR 1=1')['conversas'])->toBeGreaterThanOrEqual(0);
});

describe('Volume');

it('conta perguntas do aluno, não respostas da Norminha', function () use ($tele) {
    // 5 perguntas do aluno A na janela; as respostas nao contam
    expect($tele->volume(14)['mensagens'])->toBe(5);
});

it('mensagens por aluno ativo é média, não total', function () use ($tele) {
    $v = $tele->volume(14);
    expect($v['alunos'])->toBe(1);
    expect($v['mensagens_por_aluno'])->toEqual(5.0);
});

describe('Distribuição por origem da resposta');

it('separa php de unresolved e fecha 100%', function () use ($tele) {
    $r = $tele->porResolucao(14);
    expect($r['contagem']['php'])->toBe(3);
    expect($r['contagem']['unresolved'])->toBe(2);
    expect($r['total'])->toBe(5);
    expect(round($r['percentual']['php'] + $r['percentual']['unresolved']))->toEqual(100.0);
});

it('sem dados, não divide por zero', function () use ($tele, $pdo) {
    $pdo->exec('UPDATE norminha_mensagens SET created_at = DATE_SUB(NOW(), INTERVAL 300 DAY)');
    $r = $tele->porResolucao(1);
    expect($r['total'])->toBe(0);
    expect($r['percentual']['php'])->toEqual(0.0);
    $pdo->exec('UPDATE norminha_mensagens SET created_at = NOW() WHERE resolved_by IS NOT NULL OR papel = "user"');
});

describe('Atalhos');

it('conta só o que foi resolvido por fast-path', function () use ($tele) {
    $mapa = array();
    foreach ($tele->topIntencoes(14) as $l) {
        $mapa[$l['intencao']] = (int) $l['n'];
    }
    expect(isset($mapa['show_progress']))->toBeTrue();
    expect(isset($mapa['resume_course']))->toBeTrue();
    expect(isset($mapa['certificate_status']))->toBeTrue();
});

describe('Perguntas não resolvidas — a entrega da etapa');

it('traz a pergunta do ALUNO, não a resposta da Norminha', function () use ($tele, $PERGUNTA_CONTEUDO, $PERGUNTA_NAVEGACAO) {
    $lista = $tele->perguntasNaoResolvidas(14, 50);
    expect(count($lista))->toBeGreaterThanOrEqual(2);

    $textos = array();
    foreach ($lista as $l) { $textos[] = $l['pergunta']; }

    expect(in_array($PERGUNTA_CONTEUDO, $textos, true))->toBeTrue();
    expect(in_array($PERGUNTA_NAVEGACAO, $textos, true))->toBeTrue();

    // a resposta da Norminha NAO pode aparecer como se fosse a pergunta
    foreach ($textos as $t) {
        if (strpos((string) $t, 'Ainda não consigo responder') !== false) {
            throw new RuntimeException('a lista trouxe a RESPOSTA no lugar da pergunta');
        }
    }
});

it('traz o contexto acadêmico, para permitir classificar a dúvida', function () use ($tele) {
    $l = $tele->perguntasNaoResolvidas(14, 50)[0];
    foreach (array('created_at', 'usuario_id', 'curso_evento_id', 'contexto', 'rota', 'pergunta') as $c) {
        expect($l)->toHaveKey($c);
    }
    expect((int) $l['curso_evento_id'])->toBe(9);
});

it('filtra por curso', function () use ($tele) {
    expect(count($tele->perguntasNaoResolvidas(14, 50, 9)))->toBeGreaterThanOrEqual(2);
    expect(count($tele->perguntasNaoResolvidas(14, 50, 999999)))->toBe(0);
});

it('respeita o teto de itens', function () use ($tele) {
    expect(count($tele->perguntasNaoResolvidas(14, 1)))->toBe(1);
    // limite absurdo nao explode a tela
    expect(count($tele->perguntasNaoResolvidas(14, 99999)))->toBeLessThanOrEqual(100);
});

it('agrupa por curso', function () use ($tele) {
    $cursos = $tele->cursosComDuvida(14);
    expect(count($cursos))->toBeGreaterThanOrEqual(1);
    expect((int) $cursos[0]['curso_evento_id'])->toBe(9);
    expect((int) $cursos[0]['n'])->toBeGreaterThanOrEqual(2);
});

describe('Feedback e bloqueios');

it('calcula útil x não útil e a cobertura', function () use ($tele) {
    $f = $tele->feedback(14);
    expect($f['uteis'])->toBe(1);
    expect($f['nao_uteis'])->toBe(1);
    expect($f['percentual_util'])->toEqual(50.0);
    // cobertura avisa quando a amostra e pequena demais para significar algo
    expect($f)->toHaveKey('cobertura');
});

it('sem votos, o percentual é null e não zero', function () use ($tele, $pdo) {
    $pdo->exec('UPDATE norminha_feedback SET created_at = DATE_SUB(NOW(), INTERVAL 300 DAY)');
    $f = $tele->feedback(14);
    expect($f['total'])->toBe(0);
    // null distingue "ninguem votou" de "todos votaram nao util"
    expect($f['percentual_util'])->toBeNull();
    $pdo->exec('UPDATE norminha_feedback SET created_at = NOW()');
});

it('conta os 429 de verdade, não por aproximação', function () use ($tele) {
    $b = $tele->bloqueios(14);
    expect($b['total'])->toBe(2);      // dois bloqueios registrados
    expect($b['alunos'])->toBe(1);     // de um aluno so
});

describe('Privacidade do painel');

it('não devolve nome, e-mail, CPF nem hash', function () use ($tele) {
    $tudo = strtolower(json_encode(array(
        $tele->panorama(14),
        $tele->perguntasNaoResolvidas(14, 50),
        $tele->cursosComDuvida(14),
    ), JSON_UNESCAPED_UNICODE));

    foreach (array('senha', 'cpf', '@', 'telefone', 'pagador', 'hash', 'token') as $proibido) {
        if (strpos($tudo, $proibido) !== false) {
            throw new RuntimeException("painel expoe: {$proibido}");
        }
    }
    expect(true)->toBeTrue();
});

describe('Somente leitura');

it('nenhuma consulta do painel escreve', function () use ($pdo, $tele) {
    $ler = function () use ($pdo) {
        return $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN
            ('Com_insert','Com_update','Com_delete','Com_replace')")->fetchAll(PDO::FETCH_KEY_PAIR);
    };
    $antes = $ler();

    $tele->panorama(14);
    $tele->perguntasNaoResolvidas(14, 50);
    $tele->cursosComDuvida(14);

    foreach ($ler() as $k => $v) {
        if ((int) $v !== (int) $antes[$k]) {
            throw new RuntimeException("ESCRITA detectada: {$k}");
        }
    }
    expect(true)->toBeTrue();
});

$pdo->rollBack();
echo "\n(transação desfeita — nada foi gravado)\n";

exit(testes_resumo());

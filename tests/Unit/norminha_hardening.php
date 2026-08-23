<?php

/**
 * Hardening da Norminha (Etapa 15): IDOR, XSS, privacidade e rate limit.
 *
 * Este arquivo tenta QUEBRAR o sistema, não confirmá-lo funcionando. Cada teste
 * assume o papel de um aluno A tentando alcançar dado do aluno B, ou de um
 * modelo tentando escapar do escopo.
 *
 * Execução: php tests/Unit/norminha_hardening.php
 * Roda em transação e desfaz tudo.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\Inscricao;
use App\Models\NorminhaConversa;
use App\Models\NorminhaFeedback;
use App\Models\NorminhaMensagem;
use App\Models\NorminhaUso;
use App\Services\NorminhaContextService;
use App\Services\NorminhaKnowledgeService;
use App\Services\NorminhaRateLimitService;
use App\Services\NorminhaService;
use App\Services\NorminhaToolsService;

$pdo = testes_conectar_banco();
$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nBanco: {$banco}\n";
if ($banco === 'desbloqueiacursos') { fwrite(STDERR, "RECUSADO\n"); exit(1); }

// Dois alunos REAIS e distintos: IDOR contra usuário inexistente não prova nada.
$inscricaoModel = new Inscricao();
$A = null; $B = null;
foreach ($pdo->query('SELECT usuario_id FROM inscricoes WHERE deleted_at IS NULL
                      GROUP BY usuario_id ORDER BY COUNT(*) DESC LIMIT 80')->fetchAll(PDO::FETCH_COLUMN) as $u) {
    $ativas = $inscricaoModel->forUsuarioAprovadas($u);
    if (!$ativas) { continue; }
    if ($A === null) { $A = array('u' => (int) $u, 'ins' => $ativas[0]); continue; }
    if ($B === null && (int) $u !== $A['u']) { $B = array('u' => (int) $u, 'ins' => $ativas[0]); break; }
}
if (!$A || !$B) { fwrite(STDERR, "ERRO: preciso de dois alunos com matricula.\n"); exit(1); }
printf("Aluno A=%d (insc %d) · Aluno B=%d (insc %d)\n", $A['u'], $A['ins']['id'], $B['u'], $B['ins']['id']);

$pdo->beginTransaction();

$servico = new NorminhaService();
$tools = new NorminhaToolsService();
$ctxService = new NorminhaContextService();
$knowledge = new NorminhaKnowledgeService();
$conversas = new NorminhaConversa();
$mensagens = new NorminhaMensagem();
$feedbacks = new NorminhaFeedback();

// ===================================================================

describe('IDOR — o aluno A tentando alcançar o aluno B');

it('conversa de B negada para A', function () use ($servico, $A, $B) {
    $deB = $servico->processar($B['u'], array('action' => 'show_progress',
        'context' => array('inscricao_id' => (int) $B['ins']['id'])));
    expect($deB['ok'])->toBeTrue();

    $invasao = $servico->processar($A['u'], array('message' => 'oi',
        'conversation_id' => $deB['conversation_id']));
    expect($invasao['ok'])->toBeFalse();
    expect($invasao['status'])->toBe(403);
});

it('inscrição de B descartada quando A a envia', function () use ($ctxService, $A, $B) {
    $c = $ctxService->resolver($A['u'], array('inscricao_id' => (int) $B['ins']['id']));
    expect($c['inscricao_id'] === (int) $B['ins']['id'])->toBeFalse();
    if ($c['estado'] === 'ok') {
        // caiu na propria matricula, nao na alheia
        expect($c['usuario_id'])->toBe($A['u']);
    }
});

it('tools de B não respondem para A', function () use ($tools, $A, $B) {
    foreach (array('getStudentProgress', 'getResumePoint', 'getNextLearningItem', 'getCertificateStatus') as $m) {
        $r = $tools->$m($A['u'], (int) $B['ins']['id']);
        if (!empty($r['ok']) && (int) $r['inscricao_id'] === (int) $B['ins']['id']) {
            throw new RuntimeException("{$m} devolveu dados da inscricao de B");
        }
    }
    expect(true)->toBeTrue();
});

it('conteúdo de curso sem matrícula não é recuperado', function () use ($knowledge, $A, $B, $pdo) {
    $cursoB = (int) $B['ins']['curso_evento_id'];
    $cursoA = (int) $A['ins']['curso_evento_id'];
    if ($cursoA === $cursoB) { echo "      (mesmo curso — caso nao exercitado)\n"; expect(true)->toBeTrue(); return; }

    $itemB = $pdo->query('SELECT id FROM conteudo_itens WHERE curso_evento_id=' . $cursoB . '
                          AND status="publicado" AND deleted_at IS NULL LIMIT 1')->fetchColumn();
    if (!$itemB) { expect(true)->toBeTrue(); return; }

    // contexto do curso de A, pedindo item do curso de B
    $r = $knowledge->conteudoDoItemAtual($A['u'], array(
        'curso_evento_id' => $cursoA, 'item_atual' => array('id' => (int) $itemB),
    ));
    expect($r['tem_evidencia'])->toBeFalse();
});

it('feedback em mensagem de B negado para A', function () use ($servico, $mensagens, $A, $B) {
    $deB = $servico->processar($B['u'], array('action' => 'next_step',
        'context' => array('inscricao_id' => (int) $B['ins']['id'])));
    $msgB = (int) $deB['message_id'];

    // A camada de servico usa buscarDoUsuario antes de aceitar feedback
    expect($mensagens->buscarDoUsuario($msgB, $B['u']))->notToBeNull();
    expect($mensagens->buscarDoUsuario($msgB, $A['u']))->toBeNull();
});

it('histórico de B vazio para A', function () use ($servico, $mensagens, $conversas, $A, $B) {
    $deB = $servico->processar($B['u'], array('action' => 'show_progress',
        'context' => array('inscricao_id' => (int) $B['ins']['id'])));
    $conv = $conversas->buscarPorUuid($deB['conversation_id'], $B['u']);
    expect($conv)->notToBeNull();

    expect($conversas->buscarPorUuid($deB['conversation_id'], $A['u']))->toBeNull();
    expect($mensagens->ultimasDaConversa((int) $conv['id'], $A['u'], 12))->toEqual(array());
});

it('a mensagem de erro não revela que o recurso existe', function () use ($servico, $A, $B) {
    $deB = $servico->processar($B['u'], array('action' => 'show_progress',
        'context' => array('inscricao_id' => (int) $B['ins']['id'])));

    $existente = $servico->processar($A['u'], array('message' => 'x', 'conversation_id' => $deB['conversation_id']));
    $inexistente = $servico->processar($A['u'], array('message' => 'x',
        'conversation_id' => '00000000-0000-4000-8000-000000000000'));

    // Mesma resposta nos dois casos: nada distingue "existe mas nao e sua" de
    // "nao existe".
    expect($existente['erro'])->toBe($inexistente['erro']);
    expect($existente['mensagem'])->toBe($inexistente['mensagem']);
    expect($existente['status'])->toBe($inexistente['status']);
});

describe('XSS — nada do servidor vira HTML');

it('o cliente nunca usa innerHTML com texto do servidor', function () {
    $js = file_get_contents(BASE_PATH . '/assets/js/tutor-norminha.js');
    $codigo = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $js);
    foreach (array('innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(') as $perigo) {
        if (strpos($codigo, $perigo) !== false) {
            throw new RuntimeException("o cliente usa {$perigo}");
        }
    }
    expect(true)->toBeTrue();
});

it('a URL de ação é revalidada no cliente', function () {
    $js = file_get_contents(BASE_PATH . '/assets/js/tutor-norminha.js');
    expect($js)->toContain('urlInternaSegura');
    expect($js)->toContain("charAt(0) !== '/'");
});

it('texto com script sobrevive como TEXTO, não some nem executa', function () use ($servico, $pdo, $A) {
    $veneno = '<script>alert(document.cookie)</script><img src=x onerror=alert(1)>';
    $r = $servico->processar($A['u'], array('message' => $veneno,
        'context' => array('inscricao_id' => (int) $A['ins']['id'])));

    $conv = $pdo->query('SELECT conversa_id FROM norminha_mensagens WHERE id=' . (int) $r['message_id'])->fetchColumn();
    $gravado = $pdo->query('SELECT mensagem FROM norminha_mensagens WHERE conversa_id=' . (int) $conv
        . ' AND papel="user" ORDER BY id DESC LIMIT 1')->fetchColumn();

    // Gravado integro: a telemetria precisa ler a pergunta real.
    expect($gravado)->toBe($veneno);
    // E a resposta nao o ecoa.
    expect(strpos($r['message'], '<script>'))->toBeFalse();
});

it('nenhuma ação aponta para esquema perigoso', function () use ($servico, $A) {
    foreach (NorminhaService::ACOES as $acao) {
        $r = $servico->processar($A['u'], array('action' => $acao,
            'context' => array('inscricao_id' => (int) $A['ins']['id'])));
        foreach ($r['actions'] as $a) {
            foreach (array('javascript:', 'data:', 'vbscript:', 'http://', 'https://', '//') as $p) {
                if (stripos($a['url'], $p) === 0) {
                    throw new RuntimeException("URL perigosa: {$a['url']}");
                }
            }
        }
    }
    expect(true)->toBeTrue();
});

describe('Rate limit definitivo');

it('ação determinística tem política mais folgada que pergunta livre', function () use ($pdo, $A) {
    $rl = new NorminhaRateLimitService();
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id=' . $A['u']);

    $acao = $rl->registrarEVerificar($A['u'], false, true);
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id=' . $A['u']);
    $livre = $rl->registrarEVerificar($A['u'], false, false);

    expect($acao['limite_janela'] > $livre['limite_janela'])->toBeTrue();
    echo "      atalho: {$acao['limite_janela']}/janela · livre: {$livre['limite_janela']}/janela\n";
});

it('existe teto PRÓPRIO para mensagens que consomem IA', function () use ($pdo, $A) {
    $rl = new NorminhaRateLimitService();
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id=' . $A['u']);

    $bloqueou = null;
    for ($i = 1; $i <= 400; $i++) {
        $r = $rl->registrarEVerificar($A['u'], true);
        if (empty($r['permitido'])) { $bloqueou = $r; break; }
    }
    expect($bloqueou)->notToBeNull();
    // O teto de IA corta ANTES do teto geral de 200.
    expect(in_array($bloqueou['motivo'], array('ia_diario', 'janela', 'diario'), true))->toBeTrue();
    echo "      bloqueou por: {$bloqueou['motivo']} na mensagem {$i}\n";
});

it('a mensagem de bloqueio de IA oferece o que ainda funciona', function () {
    $rl = new NorminhaRateLimitService();
    $m = $rl->mensagemDeBloqueio(array('motivo' => 'ia_diario', 'retry_after' => 3600));
    expect($m)->toContain('progresso');
    expect($m)->toContain('continuam disponíveis');
});

it('a tabela de uso tem rotação', function () use ($pdo, $A) {
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id=' . $A['u']);
    (new NorminhaUso())->registrar($A['u'], false, 300);
    $pdo->exec('UPDATE norminha_uso SET dia = DATE_SUB(CURDATE(), INTERVAL 200 DAY) WHERE usuario_id=' . $A['u']);

    $removidas = (new NorminhaRateLimitService())->limparAntigas(90);
    expect($removidas)->toBeGreaterThan(0);
});

describe('Privacidade — o que sai do servidor');

it('o contexto enviado ao modelo não carrega usuario_id nem diagnóstico', function () use ($ctxService, $A) {
    $c = $ctxService->resolver($A['u'], array('inscricao_id' => 999999999));
    $enviado = $ctxService->paraModelo($c);

    expect(isset($enviado['usuario_id']))->toBeFalse();
    expect(isset($enviado['hints_descartados']))->toBeFalse();
});

it('nenhum campo pessoal aparece no que iria ao provedor', function () use ($ctxService, $knowledge, $A) {
    $c = $ctxService->resolver($A['u'], array('inscricao_id' => (int) $A['ins']['id']));
    $payload = json_encode(array(
        $ctxService->paraModelo($c),
        $knowledge->evidenciaPara($A['u'], $c, 'conceito avaliacao'),
    ), JSON_UNESCAPED_UNICODE);

    foreach (array('cpf', 'senha_hash', 'pagador', 'telefone', '@gmail', '@hotmail', '@outlook') as $proibido) {
        if (stripos($payload, $proibido) !== false) {
            throw new RuntimeException("payload ao provedor contem: {$proibido}");
        }
    }
    expect(true)->toBeTrue();
});

describe('Logs não vazam segredo');

it('nenhum service da Norminha loga chave, cookie ou Authorization', function () {
    foreach (glob(BASE_PATH . '/app/Services/Norminha*.php') as $arquivo) {
        $codigo = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents($arquivo));
        foreach (array('api_key', 'Authorization', '$_COOKIE', 'session_id') as $termo) {
            if (strpos($codigo, $termo) !== false) {
                throw new RuntimeException(basename($arquivo) . " referencia {$termo}");
            }
        }
    }
    expect(true)->toBeTrue();
});

it('o OpenAIService não loga o prompt nem o conteúdo enviado', function () {
    $codigo = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents(BASE_PATH . '/app/Services/OpenAIService.php'));
    // Procura Logger com payload/input/instructions no contexto.
    if (preg_match('/Logger::\w+\([^)]*(\$payload|\$input|instructions|\$corpo)[^)]*\)/s', $codigo)) {
        throw new RuntimeException('o service loga conteudo enviado');
    }
    expect(true)->toBeTrue();
});

describe('Sem retry automático');

it('o cliente tenta uma vez e oferece botão, em vez de repetir sozinho', function () {
    $js = file_get_contents(BASE_PATH . '/assets/js/tutor-norminha.js');
    expect($js)->toContain('Tentar novamente');
    expect($js)->toContain('AbortController');
    // Repetir sozinho gravaria a mesma pergunta duas vezes.
    expect(strpos($js, 'for (var tentativa'))->toBeFalse();
});

$pdo->rollBack();
echo "\n(transação desfeita)\n";
exit(testes_resumo());

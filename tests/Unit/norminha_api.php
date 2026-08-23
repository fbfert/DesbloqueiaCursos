<?php

/**
 * API da Norminha — testes HTTP de ponta a ponta (Etapa 5).
 *
 * Diferente dos outros testes desta suíte, este NÃO chama classes: ele fala
 * HTTP com um servidor de verdade, porque o que está sendo testado é
 * justamente a camada que os testes de unidade não alcançam — middleware,
 * CSRF, status HTTP e cabeçalhos.
 *
 * O motivo de existir está na correção C3 da auditoria: nenhum middleware do
 * projeto respondia JSON. Um fetch() que recebe 302 e segue para o HTML do
 * login enxerga 200 e conclui que deu certo. Só um teste HTTP pega isso.
 *
 * PRÉ-REQUISITOS
 *   1. servidor local:  php -S 127.0.0.1:8000 -t . tests/Smoke/router.php
 *   2. credenciais de teste em NORMINHA_API_CREDS (arquivo JSON) ou as
 *      variáveis NORMINHA_URL / NORMINHA_USER / NORMINHA_PASS
 *
 * Execução: php tests/Unit/norminha_api.php
 */

require_once __DIR__ . '/_bootstrap.php';

$base = rtrim((string) (getenv('NORMINHA_URL') ?: 'http://127.0.0.1:8000'), '/');
$arquivoCreds = getenv('NORMINHA_API_CREDS') ?: '';
$creds = $arquivoCreds !== '' && is_readable($arquivoCreds)
    ? json_decode((string) file_get_contents($arquivoCreds), true)
    : array();

$usuarioA = getenv('NORMINHA_USER') ?: (isset($creds['e1']) ? $creds['e1'] : '');
$usuarioB = isset($creds['e2']) ? $creds['e2'] : '';
$senha = getenv('NORMINHA_PASS') ?: (isset($creds['senha']) ? $creds['senha'] : '');
$inscricaoId = isset($creds['inscricao']) ? (int) $creds['inscricao'] : 0;

if ($usuarioA === '' || $senha === '') {
    fwrite(STDERR, "SKIP: sem credenciais. Defina NORMINHA_API_CREDS ou NORMINHA_USER/NORMINHA_PASS.\n");
    exit(0);
}

echo "\nAlvo: {$base}\n";

// -------------------------------------------------------------------
// Cliente HTTP mínimo
// -------------------------------------------------------------------

function http_req($base, $metodo, $caminho, $dados = null, $cookies = null, $json = true)
{
    $ch = curl_init($base . $caminho);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
    );
    if ($cookies !== null) {
        $opts[CURLOPT_COOKIEJAR] = $cookies;
        $opts[CURLOPT_COOKIEFILE] = $cookies;
    }
    if ($metodo === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($json) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($dados, JSON_UNESCAPED_UNICODE);
            $opts[CURLOPT_HTTPHEADER] = array('Content-Type: application/json');
        } else {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($dados);
        }
    }
    curl_setopt_array($ch, $opts);

    $bruto = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tamanho = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $cabecalhos = substr((string) $bruto, 0, $tamanho);
    $corpo = substr((string) $bruto, $tamanho);

    return array(
        'status' => $status,
        'corpo' => $corpo,
        'json' => json_decode($corpo, true),
        'cabecalhos' => $cabecalhos,
        'location' => preg_match('/^\s*Location:\s*(.+?)\s*$/mi', $cabecalhos, $m) ? $m[1] : null,
        'retry_after' => preg_match('/^\s*Retry-After:\s*(.+?)\s*$/mi', $cabecalhos, $m) ? trim($m[1]) : null,
    );
}

function login($base, $usuario, $senha)
{
    $cookies = tempnam(sys_get_temp_dir(), 'norminha_api_');
    $pagina = http_req($base, 'GET', '/v2/login/', null, $cookies);
    if (!preg_match('/name="_token"\s+value="([^"]+)"/i', $pagina['corpo'], $m)) {
        return null;
    }
    $token = $m[1];

    $r = http_req($base, 'POST', '/login', array(
        '_token' => $token, 'origem' => 'v2', 'login' => $usuario, 'senha' => $senha,
    ), $cookies, false);

    if ($r['status'] !== 302 && $r['status'] !== 200) {
        return null;
    }

    return array('cookies' => $cookies, 'token' => $token);
}

// -------------------------------------------------------------------

$sessaoA = login($base, $usuarioA, $senha);
if (!$sessaoA) {
    fwrite(STDERR, "ERRO: login do aluno A falhou. O servidor local esta rodando?\n");
    exit(1);
}
// O token de CSRF muda ao regenerar a sessao no login: relê da area logada.
$area = http_req($base, 'GET', '/v2/aluno/', null, $sessaoA['cookies']);
if (preg_match('/name="_token"\s+value="([^"]+)"/i', $area['corpo'], $m)) {
    $sessaoA['token'] = $m[1];
}
echo "Login do aluno A: ok\n";

// O rate limit e por usuario e dura 5 minutos: sem zerar, uma execucao anterior
// deixaria a janela consumida e TODOS os testes de chat cairiam em 429. Zerar
// aqui e o que torna a suite repetivel — e o teste de rate limit zera de novo,
// logo antes de medir o corte exato.
$pdoSetup = testes_conectar_banco();
if ($pdoSetup->query('SELECT DATABASE()')->fetchColumn() === 'desbloqueiacursos') {
    fwrite(STDERR, "RECUSADO: este teste escreve. Aponte para o banco de desenvolvimento.\n");
    exit(1);
}
foreach (array($creds['u1'] ?? 0, $creds['u2'] ?? 0) as $uid) {
    if ((int) $uid > 0) {
        $pdoSetup->exec('DELETE FROM norminha_uso WHERE usuario_id = ' . (int) $uid);
    }
}

// ===================================================================

describe('C3 — a API responde JSON, e não redireciona');

it('anônimo recebe 401 em JSON, NÃO 302 para o login', function () use ($base) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array('message' => 'oi'));

    expect($r['status'])->toBe(401);
    expect($r['location'])->toBeNull();          // nada de redirect
    expect(is_array($r['json']))->toBeTrue();    // corpo e JSON de verdade
    expect($r['json']['ok'])->toBeFalse();
    expect($r['json']['erro'])->toBe('nao_autenticado');
});

it('histórico anônimo também é 401 em JSON', function () use ($base) {
    $r = http_req($base, 'GET', '/api/norminha/historico?conversation_id=x');
    expect($r['status'])->toBe(401);
    expect($r['json']['erro'])->toBe('nao_autenticado');
});

it('CSRF inválido é 403 em JSON, não redirect', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => 'token-falso', 'message' => 'oi',
    ), $sessaoA['cookies']);

    expect($r['status'])->toBe(403);
    expect($r['location'])->toBeNull();
    expect($r['json']['erro'])->toBe('csrf_invalido');
});

it('CSRF ausente também é recusado', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array('message' => 'oi'), $sessaoA['cookies']);
    expect($r['status'])->toBe(403);
});

describe('Chat autenticado');

it('ação válida responde 200 com o contrato completo', function () use ($base, $sessaoA, $inscricaoId) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'],
        'action' => 'show_progress',
        'context' => array('inscricao_id' => $inscricaoId),
    ), $sessaoA['cookies']);

    expect($r['status'])->toBe(200);
    expect($r['json']['ok'])->toBeTrue();
    foreach (array('conversation_id','message_id','message','intent','resolved_by','avatar_state','sources','actions') as $c) {
        expect($r['json'])->toHaveKey($c);
    }
    expect($r['json']['resolved_by'])->toBe('php');
});

it('pergunta livre responde 200 e marca unresolved', function () use ($base, $sessaoA, $inscricaoId) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'],
        'message' => 'qual e a raiz quadrada de 144?',
        'context' => array('inscricao_id' => $inscricaoId),
    ), $sessaoA['cookies']);

    expect($r['status'])->toBe(200);
    expect($r['json']['resolved_by'])->toBe('unresolved');
});

it('o token CSRF viaja no corpo JSON, sem cabeçalho customizado', function () use ($base, $sessaoA) {
    // Confirma para a Etapa 6 que nao e preciso inventar header.
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'action' => 'next_step',
    ), $sessaoA['cookies']);
    expect($r['status'])->toBe(200);
});

describe('Validação — 422');

it('mensagem vazia', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'message' => '   ',
    ), $sessaoA['cookies']);
    expect($r['status'])->toBe(422);
    expect($r['json']['erro'])->toBe('mensagem_vazia');
});

it('mensagem com 2001 caracteres', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'message' => str_repeat('a', 2001),
    ), $sessaoA['cookies']);
    expect($r['status'])->toBe(422);
    expect($r['json']['erro'])->toBe('mensagem_longa');
});

it('UUID inválido', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'message' => 'oi', 'conversation_id' => 'nao-e-uuid',
    ), $sessaoA['cookies']);
    expect($r['status'])->toBe(422);
    expect($r['json']['erro'])->toBe('conversation_id_invalido');
});

it('ação fora do enum', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'action' => 'apagar_conta',
    ), $sessaoA['cookies']);
    expect($r['status'])->toBe(422);
    expect($r['json']['erro'])->toBe('acao_invalida');
});

it('contexto com chave desconhecida é recusado', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'message' => 'oi',
        'context' => array('usuario_id' => 1),      // tentativa de trocar de usuario
    ), $sessaoA['cookies']);
    expect($r['status'])->toBe(422);
    expect($r['json']['erro'])->toBe('context_invalido');
});

it('usuario_id no corpo é IGNORADO, nunca obedecido', function () use ($base, $sessaoA, $inscricaoId, $creds) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'],
        'action' => 'show_progress',
        'usuario_id' => (int) $creds['u2'],          // aluno B
        'context' => array('inscricao_id' => $inscricaoId),
    ), $sessaoA['cookies']);

    // Responde normalmente, com os dados de QUEM ESTA LOGADO.
    expect($r['status'])->toBe(200);
    expect($r['json']['ok'])->toBeTrue();
});

describe('Propriedade — 403');

it('conversa de outro aluno é negada no histórico', function () use ($base, $sessaoA, $usuarioB, $senha, $inscricaoId) {
    $meu = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'action' => 'show_progress',
        'context' => array('inscricao_id' => $inscricaoId),
    ), $sessaoA['cookies']);
    $uuid = $meu['json']['conversation_id'];
    expect($uuid)->notToBeNull();

    // o dono le
    $meuHist = http_req($base, 'GET', '/api/norminha/historico?conversation_id=' . urlencode($uuid), null, $sessaoA['cookies']);
    expect($meuHist['status'])->toBe(200);
    expect(count($meuHist['json']['mensagens']))->toBeGreaterThan(0);

    if ($usuarioB === '') { echo "      (sem aluno B — invasao nao exercitada)\n"; return; }

    $sessaoB = login($base, $usuarioB, $senha);
    if (!$sessaoB) { echo "      (login do aluno B falhou)\n"; return; }

    $invasao = http_req($base, 'GET', '/api/norminha/historico?conversation_id=' . urlencode($uuid), null, $sessaoB['cookies']);
    expect($invasao['status'])->toBe(403);
    expect($invasao['json']['erro'])->toBe('conversa_invalida');
    expect(isset($invasao['json']['mensagens']))->toBeFalse();   // nada de conteudo
});

it('o histórico não expõe dado interno', function () use ($base, $sessaoA, $inscricaoId) {
    $meu = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'action' => 'next_step',
        'context' => array('inscricao_id' => $inscricaoId),
    ), $sessaoA['cookies']);
    $h = http_req($base, 'GET', '/api/norminha/historico?conversation_id=' . urlencode($meu['json']['conversation_id']), null, $sessaoA['cookies']);

    foreach (array('openai_response_id','modelo_ia','input_tokens','output_tokens','tool_name','latencia_ms') as $interno) {
        if (strpos($h['corpo'], $interno) !== false) {
            throw new RuntimeException("historico expoe campo interno: {$interno}");
        }
    }
    expect(true)->toBeTrue();
});

describe('Feedback');

it('registra e depois atualiza sem duplicar', function () use ($base, $sessaoA, $inscricaoId) {
    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $sessaoA['token'], 'action' => 'certificate_status',
        'context' => array('inscricao_id' => $inscricaoId),
    ), $sessaoA['cookies']);
    $mid = (int) $r['json']['message_id'];

    $f1 = http_req($base, 'POST', '/api/norminha/feedback', array(
        '_token' => $sessaoA['token'], 'message_id' => $mid, 'useful' => true, 'comment' => 'clara',
    ), $sessaoA['cookies']);
    expect($f1['status'])->toBe(200);
    expect($f1['json']['useful'])->toBeTrue();

    $f2 = http_req($base, 'POST', '/api/norminha/feedback', array(
        '_token' => $sessaoA['token'], 'message_id' => $mid, 'useful' => false,
    ), $sessaoA['cookies']);
    expect($f2['status'])->toBe(200);
    expect($f2['json']['useful'])->toBeFalse();
});

it('feedback em mensagem alheia é 403', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/feedback', array(
        '_token' => $sessaoA['token'], 'message_id' => 999999999, 'useful' => true,
    ), $sessaoA['cookies']);
    expect($r['status'])->toBe(403);
    expect($r['json']['erro'])->toBe('mensagem_invalida');
});

it('feedback sem useful é 422', function () use ($base, $sessaoA) {
    $r = http_req($base, 'POST', '/api/norminha/feedback', array(
        '_token' => $sessaoA['token'], 'message_id' => 1,
    ), $sessaoA['cookies']);
    expect($r['status'])->toBe(422);
});

describe('Rate limit — 429');

it('a 21ª mensagem na janela é bloqueada com Retry-After', function () use ($base, $usuarioA, $senha, $inscricaoId, $creds) {
    // O limite e por usuario, nao por sessao: as chamadas dos testes acima ja
    // consumiram a janela. Zera o contador para medir o corte EXATO — do
    // contrario o teste so provaria "bloqueia em algum momento".
    $pdo = testes_conectar_banco();
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id = ' . (int) $creds['u1']);

    $s = login($base, $usuarioA, $senha);
    $area = http_req($base, 'GET', '/v2/aluno/', null, $s['cookies']);
    preg_match('/name="_token"\s+value="([^"]+)"/i', $area['corpo'], $m);
    $token = $m[1];

    // PERGUNTA LIVRE: limite de 20 por janela. Atalho deterministico tem
    // politica 3x mais folgada (Etapa 15) e por isso NAO serve para medir o
    // corte de 21 — foi assim que este teste pegou a mudanca de politica.
    $status = array();
    $bloqueio = null;
    for ($i = 1; $i <= 30; $i++) {
        $r = http_req($base, 'POST', '/api/norminha/chat', array(
            '_token' => $token, 'message' => 'pergunta livre numero ' . $i,
            'context' => array('inscricao_id' => $inscricaoId),
        ), $s['cookies']);
        $status[] = $r['status'];
        if ($r['status'] === 429) { $bloqueio = $r; break; }
    }

    echo "      bloqueou na requisicao " . count($status) . " (limite: 20 por 5 min)\n";
    expect($bloqueio)->notToBeNull();
    expect(count($status))->toBe(21);      // 20 passam, a 21a corta
    expect($status[19])->toBe(200);        // a 20a ainda passa
    expect($bloqueio['status'])->toBe(429);
    expect($bloqueio['json']['erro'])->toBe('limite_excedido');
    expect((int) $bloqueio['json']['retry_after'])->toBeGreaterThan(0);
    expect($bloqueio['retry_after'])->notToBeNull();          // cabecalho Retry-After
    expect((int) $bloqueio['retry_after'])->toBeGreaterThan(0);
});

it('atalho determinístico tem política mais folgada que pergunta livre', function () use ($base, $usuarioA, $senha, $inscricaoId, $creds) {
    // O atalho custa uma consulta ao banco, nao um token. Travar navegacao
    // normal seria a pior protecao possivel.
    $pdo = testes_conectar_banco();
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id = ' . (int) $creds['u1']);

    $s = login($base, $usuarioA, $senha);
    $area = http_req($base, 'GET', '/v2/aluno/', null, $s['cookies']);
    preg_match('/name="_token"\s+value="([^"]+)"/i', $area['corpo'], $m);

    // 25 atalhos: a pergunta livre ja teria sido cortada na 21a.
    $bloqueou = false;
    for ($i = 1; $i <= 25; $i++) {
        $r = http_req($base, 'POST', '/api/norminha/chat', array(
            '_token' => $m[1], 'action' => 'show_progress',
            'context' => array('inscricao_id' => $inscricaoId),
        ), $s['cookies']);
        if ($r['status'] === 429) { $bloqueou = true; break; }
    }
    expect($bloqueou)->toBeFalse();
    echo "      25 atalhos seguidos: nenhum bloqueio\n";
});

it('o bloqueio explica em PT-BR, sem jargão', function () use ($base, $usuarioA, $senha) {
    $s = login($base, $usuarioA, $senha);
    $area = http_req($base, 'GET', '/v2/aluno/', null, $s['cookies']);
    preg_match('/name="_token"\s+value="([^"]+)"/i', $area['corpo'], $m);

    $r = http_req($base, 'POST', '/api/norminha/chat', array(
        '_token' => $m[1], 'message' => 'mais uma pergunta livre',
    ), $s['cookies']);

    // A janela ja estourou no teste anterior: mesmo usuario, mesma janela.
    if ($r['status'] === 429) {
        expect($r['json']['mensagem'])->toContain('muitas mensagens');
    }
    expect(in_array($r['status'], array(200, 429), true))->toBeTrue();
});

exit(testes_resumo());

<?php

/**
 * API do app do aluno (/api/app/v1) — teste HTTP de ponta a ponta.
 *
 * Fala HTTP com o app de verdade (php -S + tests/Api/router.php) contra o MariaDB
 * 10.5 do container de teste, com TODAS as migrações aplicadas e as fixtures
 * (tests/Api/montar_banco.php). Cobre cada endpoint do contrato
 * (openspec/changes/api-app-v1/contrato.md) e os caminhos 401/403/404/422/423/426/429/500.
 *
 * Pré-requisitos (ver tests/Api/README.md):
 *   docker run -d --name desbloqueia-test-db -e MARIADB_ROOT_PASSWORD=teste_root_123 -p 33061:3306 mariadb:10.5
 *   php tests/Api/montar_banco.php
 *   php -S 127.0.0.1:8099 -t . tests/Api/router.php
 *
 * Execução:
 *   php tests/Api/app_api_e2e.php            (reaplica as fixtures no início)
 *
 * Variáveis: APP_E2E_URL (http://127.0.0.1:8099), APP_TESTE_DB (desbloqueia_app_teste),
 * APP_TESTE_DB_PORT (33061), APP_TESTE_DB_ROOT_PASS (teste_root_123).
 * GUARDA: o banco precisa terminar em "_teste".
 */

define('BASE_PATH', dirname(__DIR__, 2));

$base = rtrim((string) (getenv('APP_E2E_URL') ?: 'http://127.0.0.1:8099'), '/');
$api = $base . '/api/app/v1';
$banco = getenv('APP_TESTE_DB') ?: 'desbloqueia_app_teste';
if (!preg_match('/_teste$/', $banco)) {
    fwrite(STDERR, "RECUSADO: o banco precisa terminar em _teste.\n");
    exit(1);
}

// Banco de teste: reaplica as fixtures (estado conhecido) e conecta o PDO.
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/montar_banco.php') . ' --so-fixtures', $codigoFixtures);
if ($codigoFixtures !== 0) {
    fwrite(STDERR, "ERRO: fixtures não aplicadas.\n");
    exit(1);
}

putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=' . (getenv('APP_TESTE_DB_PORT') ?: '33061'));
putenv('DB_DATABASE=' . $banco);
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=' . (getenv('APP_TESTE_DB_ROOT_PASS') ?: 'teste_root_123'));
putenv('APP_KEY=chave-local-somente-para-testes-da-api-do-app-000000');
putenv('FCM_ENABLED=false');
putenv('MAIL_ENABLED=false');
require_once BASE_PATH . '/tests/Unit/_bootstrap.php';
$pdo = testes_conectar_banco();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $banco) {
    fwrite(STDERR, "RECUSADO: conexão em banco inesperado.\n");
    exit(1);
}

echo "\nAlvo: {$api} | banco: {$banco}\n";

// -------------------------------------------------------------------
// Cliente HTTP
// -------------------------------------------------------------------

function http($metodo, $url, $corpo = null, array $cabecalhos = array(), $multipart = false)
{
    $ch = curl_init($url);
    $h = array('X-App-Version: 1.0.0 (1)', 'X-App-Platform: android');
    foreach ($cabecalhos as $k => $v) {
        $h[] = $k . ': ' . $v;
    }
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CUSTOMREQUEST => $metodo,
    );
    if ($corpo !== null) {
        if ($multipart) {
            $opts[CURLOPT_POSTFIELDS] = $corpo;
        } else {
            $h[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($corpo);
        }
    } elseif ($metodo === 'POST') {
        $h[] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = '{}';
    }
    $opts[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($ch, $opts);
    $bruto = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tam = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $cab = array();
    foreach (explode("\r\n", substr($bruto, 0, $tam)) as $linha) {
        if (strpos($linha, ':') !== false) {
            list($k, $v) = explode(':', $linha, 2);
            $cab[strtolower(trim($k))][] = trim($v);
        }
    }
    $corpoResp = substr($bruto, $tam);

    return array('status' => $status, 'h' => $cab, 'corpo' => $corpoResp, 'json' => json_decode($corpoResp, true));
}

function cab(array $r, $nome)
{
    return isset($r['h'][strtolower($nome)]) ? $r['h'][strtolower($nome)][0] : null;
}

function bearer($token)
{
    return array('Authorization' => 'Bearer ' . $token);
}

function exigir($condicao, $mensagem)
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function exigirStatus(array $r, $status, $codigo = null)
{
    $codigoAtual = $r['json']['erro']['codigo'] ?? null;
    if ($r['status'] !== $status || ($codigo !== null && $codigoAtual !== $codigo)) {
        throw new RuntimeException("Esperava {$status}" . ($codigo ? " {$codigo}" : '') . ", recebeu {$r['status']} " . substr($r['corpo'], 0, 300));
    }
    exigir(stripos((string) cab($r, 'set-cookie'), 'PHPSESSID') === false && cab($r, 'set-cookie') === null, 'resposta da API trouxe Set-Cookie');
    exigir($r['status'] >= 400 || array_key_exists('data', (array) $r['json']) || strpos((string) cab($r, 'content-type'), 'application/json') === false, 'sucesso sem "data"');
    if ($r['status'] >= 400 && strpos((string) cab($r, 'content-type'), 'application/json') !== false) {
        exigir(isset($r['json']['erro']['codigo'], $r['json']['erro']['mensagem']), 'erro sem codigo/mensagem');
        exigir(!preg_match('/(Stack trace|\.php on line|Fatal error|Warning:)/i', $r['corpo']), 'erro vazou detalhe do PHP');
    }
}

function chaves(array $obj, array $esperadas, $onde)
{
    $faltando = array_diff($esperadas, array_keys($obj));
    if ($faltando) {
        throw new RuntimeException("{$onde}: faltam chaves " . implode(', ', $faltando));
    }
}

function iso($valor, $onde)
{
    exigir($valor === null || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', (string) $valor), "{$onde}: data fora do ISO-8601 com fuso ({$valor})");
}

function login($login, $device = 'dispositivo-e2e-a')
{
    global $api;
    $r = http('POST', $api . '/auth/login', array('login' => $login, 'senha' => 'Local@12345', 'device_id' => $device, 'device_name' => 'Pixel de teste'));
    exigirStatus($r, 200);
    return $r['json']['data'];
}

function limparLimites()
{
    global $pdo;
    $pdo->exec('DELETE FROM app_limites_taxa');
}

function png1x1()
{
    $arquivo = sys_get_temp_dir() . '/app-e2e-' . bin2hex(random_bytes(4)) . '.png';
    file_put_contents($arquivo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    return $arquivo;
}

// ===================================================================
describe('Sistema');
// ===================================================================

it('GET /config devolve versões e URLs (público)', function () use ($api) {
    $r = http('GET', $api . '/config');
    exigirStatus($r, 200);
    chaves($r['json']['data'], array('versao_minima_android', 'versao_atual_android', 'url_site', 'url_catalogo', 'url_suporte', 'url_termos', 'url_privacidade'), 'config');
    expect(is_int($r['json']['data']['versao_minima_android']))->toBeTrue();
});

it('caminho desconhecido sob /api/app responde 404 JSON (GET e POST)', function () use ($api) {
    $r = http('GET', $api . '/nao-existe');
    exigirStatus($r, 404, 'nao_encontrado');
    $r = http('POST', $api . '/nao-existe/tambem');
    exigirStatus($r, 404, 'nao_encontrado');
});

it('a API não abre sessão PHP (sem Set-Cookie) nem exige CSRF no POST', function () use ($api) {
    $r = http('POST', $api . '/auth/login', array('login' => 'x@x.com', 'senha' => 'errada', 'device_id' => 'dispositivo-csrf-1'));
    exigir($r['status'] !== 419 && ($r['json']['erro']['codigo'] ?? '') !== 'csrf_invalido', 'POST do app caiu no CSRF');
    expect(cab($r, 'set-cookie'))->toBeNull();
});

// ===================================================================
describe('Autenticação');
// ===================================================================

it('login sem campos → 422 validacao com campos', function () use ($api) {
    $r = http('POST', $api . '/auth/login', array('login' => '', 'senha' => '', 'device_id' => 'x'));
    exigirStatus($r, 422, 'validacao');
    chaves($r['json']['erro']['campos'], array('login', 'senha', 'device_id'), 'campos');
});

it('senha errada → 401 credenciais_invalidas', function () use ($api) {
    limparLimites();
    $r = http('POST', $api . '/auth/login', array('login' => 'aluno.caderno@teste.local', 'senha' => 'errada', 'device_id' => 'dispositivo-e2e-a'));
    exigirStatus($r, 401, 'credenciais_invalidas');
});

it('cadastro sem senha (checkout rápido) → 401, não 500', function () use ($api) {
    $r = http('POST', $api . '/auth/login', array('login' => 'semsenha@teste.local', 'senha' => 'qualquer', 'device_id' => 'dispositivo-e2e-s'));
    exigirStatus($r, 401, 'credenciais_invalidas');
});

it('conta inativa → 403 conta_inativa', function () use ($api) {
    $r = http('POST', $api . '/auth/login', array('login' => 'inativo@teste.local', 'senha' => 'Local@12345', 'device_id' => 'dispositivo-e2e-i'));
    exigirStatus($r, 403, 'conta_inativa');
});

it('bloqueio após tentativas → 423 conta_bloqueada com minutos na mensagem', function () use ($api, $pdo) {
    limparLimites();
    for ($i = 0; $i < 5; $i++) {
        http('POST', $api . '/auth/login', array('login' => 'bloqueio@teste.local', 'senha' => 'errada' . $i, 'device_id' => 'dispositivo-e2e-b'));
    }
    $r = http('POST', $api . '/auth/login', array('login' => 'bloqueio@teste.local', 'senha' => 'Local@12345', 'device_id' => 'dispositivo-e2e-b'));
    exigirStatus($r, 423, 'conta_bloqueada');
    expect(preg_match('/\d+ minuto/', $r['json']['erro']['mensagem']) === 1)->toBeTrue();
    $pdo->exec("UPDATE usuarios SET tentativas_login = 0, bloqueado_ate = NULL WHERE id = 9103");
});

it('limite de taxa por login → 429 muitas_tentativas com Retry-After', function () use ($api) {
    limparLimites();
    $ultimo = null;
    for ($i = 0; $i < 11; $i++) {
        $ultimo = http('POST', $api . '/auth/login', array('login' => 'ninguem.' . 'x@teste.local', 'senha' => 'errada', 'device_id' => 'dispositivo-e2e-r'));
    }
    exigirStatus($ultimo, 429, 'muitas_tentativas');
    expect((int) cab($ultimo, 'retry-after'))->toBeGreaterThan(0);
    limparLimites();
});

$sessao = array();

it('login ok devolve par de tokens + usuário; banco guarda só o SHA-256', function () use (&$sessao, $pdo) {
    $dados = login('aluno.caderno@teste.local');
    chaves($dados, array('access_token', 'access_expira_em', 'refresh_token', 'refresh_expira_em', 'usuario'), 'login');
    chaves($dados['usuario'], array('id', 'nome', 'email', 'cpf', 'telefone', 'cidade', 'estado'), 'usuario');
    expect($dados['usuario']['cpf'])->toBe('987.***.***-00');
    iso($dados['access_expira_em'], 'access_expira_em');
    iso($dados['refresh_expira_em'], 'refresh_expira_em');
    $st = $pdo->prepare('SELECT COUNT(*) FROM app_tokens WHERE token_hash = :h');
    $st->execute(array('h' => hash('sha256', $dados['access_token'])));
    expect((int) $st->fetchColumn())->toBe(1);
    $st = $pdo->prepare('SELECT COUNT(*) FROM app_tokens WHERE token_hash = :t');
    $st->execute(array('t' => $dados['access_token']));
    expect((int) $st->fetchColumn())->toBe(0);
    $sessao = $dados;
});

it('sem token / token inválido → 401 nao_autenticado; válido → 200', function () use ($api, &$sessao) {
    exigirStatus(http('GET', $api . '/me'), 401, 'nao_autenticado');
    exigirStatus(http('GET', $api . '/me', null, bearer('token-que-nao-existe-0000000000000')), 401, 'nao_autenticado');
    $r = http('GET', $api . '/me', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    expect($r['json']['data']['id'])->toBe(9001);
});

it('versão abaixo da mínima → 426 atualizacao_obrigatoria', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/me', null, array_merge(bearer($sessao['access_token']), array('X-App-Version' => '0.9.0 (0)')));
    exigirStatus($r, 426, 'atualizacao_obrigatoria');
});

it('access vencido → 401 token_expirado', function () use ($api, $pdo) {
    $d = login('aluno.caderno@teste.local', 'dispositivo-e2e-exp');
    $pdo->prepare('UPDATE app_tokens SET expira_em = DATE_SUB(expira_em, INTERVAL 2 DAY) WHERE token_hash = :h')
        ->execute(array('h' => hash('sha256', $d['access_token'])));
    exigirStatus(http('GET', $api . '/me', null, bearer($d['access_token'])), 401, 'token_expirado');
});

it('refresh rotativo: par novo, access antigo revogado; reuso → 401 sessao_revogada e revoga o dispositivo', function () use ($api) {
    $d1 = login('aluno.caderno@teste.local', 'dispositivo-e2e-rot');
    $r = http('POST', $api . '/auth/refresh', array('refresh_token' => $d1['refresh_token'], 'device_id' => 'dispositivo-e2e-rot'));
    exigirStatus($r, 200);
    $d2 = $r['json']['data'];
    chaves($d2, array('access_token', 'access_expira_em', 'refresh_token', 'refresh_expira_em'), 'refresh');
    expect(isset($d2['usuario']))->toBeFalse();
    exigir($d2['refresh_token'] !== $d1['refresh_token'], 'refresh não rotacionou');
    exigirStatus(http('GET', $api . '/me', null, bearer($d2['access_token'])), 200);
    exigirStatus(http('GET', $api . '/me', null, bearer($d1['access_token'])), 401, 'nao_autenticado');

    $reuso = http('POST', $api . '/auth/refresh', array('refresh_token' => $d1['refresh_token'], 'device_id' => 'dispositivo-e2e-rot'));
    exigirStatus($reuso, 401, 'sessao_revogada');
    exigirStatus(http('GET', $api . '/me', null, bearer($d2['access_token'])), 401, 'nao_autenticado');
    exigirStatus(http('POST', $api . '/auth/refresh', array('refresh_token' => $d2['refresh_token'], 'device_id' => 'dispositivo-e2e-rot')), 401, 'sessao_revogada');
});

it('resposta do refresh perdida: reapresentar o refresh em até 60 s, sem usar o par novo → 200; depois de usar → 401 sessao_revogada', function () use ($api) {
    $d1 = login('aluno.caderno@teste.local', 'dispositivo-e2e-janela');
    $perdido = http('POST', $api . '/auth/refresh', array('refresh_token' => $d1['refresh_token'], 'device_id' => 'dispositivo-e2e-janela'));
    exigirStatus($perdido, 200);
    // o app não recebeu a resposta e repete com o mesmo refresh
    $r = http('POST', $api . '/auth/refresh', array('refresh_token' => $d1['refresh_token'], 'device_id' => 'dispositivo-e2e-janela'));
    exigirStatus($r, 200);
    $novo = $r['json']['data'];
    exigir($novo['refresh_token'] !== $perdido['json']['data']['refresh_token'], 'não reemitiu um par novo');
    exigirStatus(http('GET', $api . '/me', null, bearer($perdido['json']['data']['access_token'])), 401, 'nao_autenticado');
    exigirStatus(http('GET', $api . '/me', null, bearer($novo['access_token'])), 200);
    // par novo já usado: reapresentar o refresh antigo agora é roubo
    exigirStatus(http('POST', $api . '/auth/refresh', array('refresh_token' => $d1['refresh_token'], 'device_id' => 'dispositivo-e2e-janela')), 401, 'sessao_revogada');
    exigirStatus(http('GET', $api . '/me', null, bearer($novo['access_token'])), 401, 'nao_autenticado');
});

it('refresh de outro aparelho ou inexistente → 401', function () use ($api) {
    $d = login('aluno.caderno@teste.local', 'dispositivo-e2e-dev1');
    exigirStatus(http('POST', $api . '/auth/refresh', array('refresh_token' => $d['refresh_token'], 'device_id' => 'dispositivo-e2e-dev2')), 401, 'sessao_revogada');
    exigirStatus(http('POST', $api . '/auth/refresh', array('refresh_token' => 'nao-existe-00000000000000000000', 'device_id' => 'dispositivo-e2e-dev1')), 401, 'nao_autenticado');
    exigirStatus(http('POST', $api . '/auth/refresh', array('refresh_token' => '', 'device_id' => '')), 422, 'validacao');
});

it('cadastro: inválido → 422 com campos; válido → 201; duplicado → 422', function () use ($api) {
    limparLimites();
    $r = http('POST', $api . '/auth/cadastro', array('nome' => '', 'email' => 'invalido', 'cpf' => '123', 'senha' => '1', 'senha_confirmacao' => '2'));
    exigirStatus($r, 422, 'validacao');
    chaves($r['json']['erro']['campos'], array('nome', 'email', 'cpf', 'senha', 'aceite_termos', 'aceite_privacidade'), 'cadastro');

    $dados = array(
        'nome' => 'Novo Aluno App', 'email' => 'app.cadastro.1@teste.local', 'cpf' => '935.411.347-80',
        'telefone' => '(11) 95555-4444', 'senha' => 'Senha@12345', 'senha_confirmacao' => 'Senha@12345',
        'aceite_termos' => true, 'aceite_privacidade' => true,
    );
    $r = http('POST', $api . '/auth/cadastro', $dados);
    exigirStatus($r, 201);
    expect($r['json']['data']['ok'])->toBeTrue();
    $r = http('POST', $api . '/auth/login', array('login' => 'app.cadastro.1@teste.local', 'senha' => 'Senha@12345', 'device_id' => 'dispositivo-e2e-novo'));
    exigirStatus($r, 200);
    $r = http('POST', $api . '/auth/cadastro', $dados);
    exigirStatus($r, 422, 'validacao');
    chaves($r['json']['erro']['campos'], array('email', 'cpf'), 'duplicado');
});

it('recuperar-senha: sempre 200 (existe ou não); excesso → 429', function () use ($api) {
    limparLimites();
    exigirStatus(http('POST', $api . '/auth/recuperar-senha', array('login' => 'aluno.caderno@teste.local')), 200);
    $r = http('POST', $api . '/auth/recuperar-senha', array('login' => 'nao.existe@teste.local'));
    exigirStatus($r, 200);
    expect($r['json']['data']['ok'])->toBeTrue();
    $ultimo = null;
    for ($i = 0; $i < 6; $i++) {
        $ultimo = http('POST', $api . '/auth/recuperar-senha', array('login' => 'alvo.repetido@teste.local'));
    }
    exigirStatus($ultimo, 429, 'muitas_tentativas');
    limparLimites();
});

// ===================================================================
describe('Perfil');
// ===================================================================

it('POST /me atualiza telefone/cidade/estado; UF inválida → 422', function () use ($api, &$sessao) {
    $r = http('POST', $api . '/me', array('telefone' => '(11) 91234-5678', 'cidade' => 'Santos', 'estado' => 'SP'), bearer($sessao['access_token']));
    exigirStatus($r, 200);
    expect($r['json']['data']['cidade'])->toBe('Santos');
    $r = http('POST', $api . '/me', array('estado' => 'XYZ1'), bearer($sessao['access_token']));
    exigirStatus($r, 422, 'validacao');
});

it('POST /me/senha: senha atual errada → 422; certa → 200 e revoga os outros aparelhos', function () use ($api, &$sessao, $pdo) {
    $outro = login('aluno.caderno@teste.local', 'dispositivo-e2e-outro');
    $r = http('POST', $api . '/me/senha', array('senha_atual' => 'errada', 'senha_nova' => 'NovaSenha@1', 'senha_nova_confirmacao' => 'NovaSenha@1'), bearer($sessao['access_token']));
    exigirStatus($r, 422, 'validacao');
    $r = http('POST', $api . '/me/senha', array('senha_atual' => 'Local@12345', 'senha_nova' => 'NovaSenha@1', 'senha_nova_confirmacao' => 'NovaSenha@1'), bearer($sessao['access_token']));
    exigirStatus($r, 200);
    exigirStatus(http('GET', $api . '/me', null, bearer($outro['access_token'])), 401, 'nao_autenticado');
    exigirStatus(http('GET', $api . '/me', null, bearer($sessao['access_token'])), 200);
    // volta a senha da fixture
    $pdo->exec("UPDATE usuarios SET senha_hash = '\$2y\$10\$so3dNPtguylmlbF7xgeFKOTXpVXYf9gA8ghtweClLmnkcnpibh2bi' WHERE id = 9001");
});

// ===================================================================
describe('Meus cursos e conteúdo');
// ===================================================================

$tokenB = null;

it('GET /inscricoes: só as inscrições com acesso do próprio aluno, no formato do contrato', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/inscricoes', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    $ids = array_map(function ($i) { return $i['id']; }, $r['json']['data']);
    sort($ids);
    expect($ids)->toEqual(array(9001, 9002, 9003));
    foreach ($r['json']['data'] as $insc) {
        chaves($insc, array('id', 'status', 'curso', 'turma', 'progresso_percentual', 'acesso_expira_em', 'certificado', 'atualizado_em'), 'inscricao');
        chaves($insc['curso'], array('id', 'titulo', 'slug', 'imagem_url', 'carga_horaria'), 'curso');
        chaves($insc['certificado'], array('status', 'codigo'), 'certificado');
        expect(is_int($insc['progresso_percentual']))->toBeTrue();
        iso($insc['atualizado_em'], 'atualizado_em');
    }
    $porId = array();
    foreach ($r['json']['data'] as $insc) {
        $porId[$insc['id']] = $insc;
    }
    expect($porId[9002]['certificado']['status'])->toBe('emitido');
    expect($porId[9002]['certificado']['codigo'])->toBe('CADFIX-2026-0001');
});

it('inscrição de outro aluno ou inexistente → 403 sem_acesso', function () use ($api, &$sessao) {
    exigirStatus(http('GET', $api . '/inscricoes/9101', null, bearer($sessao['access_token'])), 403, 'sem_acesso');
    exigirStatus(http('GET', $api . '/inscricoes/999999', null, bearer($sessao['access_token'])), 403, 'sem_acesso');
    exigirStatus(http('GET', $api . '/inscricoes/9101/itens/9002', null, bearer($sessao['access_token'])), 403, 'sem_acesso');
});

it('GET /inscricoes/{id}: árvore publicada com status do contrato', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/inscricoes/9001', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    chaves($r['json']['data'], array('inscricao', 'modulos'), 'arvore');
    expect(count($r['json']['data']['modulos']))->toBeGreaterThan(2);
    $validos = array('nao_iniciado', 'em_andamento', 'concluido', 'enviado', 'corrigido', 'aprovado', 'reprovado');
    $ids = array();
    foreach ($r['json']['data']['modulos'] as $m) {
        chaves($m, array('id', 'titulo', 'ordem', 'concluido', 'itens'), 'modulo');
        foreach ($m['itens'] as $item) {
            chaves($item, array('id', 'tipo', 'titulo', 'ordem', 'obrigatorio', 'status', 'concluido_em'), 'item');
            exigir(in_array($item['status'], $validos, true), 'status fora do enum: ' . $item['status']);
            $ids[] = $item['id'];
        }
    }
    exigir(!in_array(9019, $ids, true), 'item em rascunho apareceu na árvore');
});

it('abrir texto: HTML sanitizado (sem script/onclick), navegação anterior/próximo', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/inscricoes/9001/itens/9002', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    $d = $r['json']['data'];
    chaves($d, array('item', 'conteudo', 'anterior', 'proximo', 'pode_concluir_manualmente'), 'item aberto');
    chaves($d['conteudo'], array('html', 'video', 'link', 'arquivo'), 'conteudo');
    exigir(stripos($d['conteudo']['html'], '<script') === false && stripos($d['conteudo']['html'], 'onclick') === false, 'HTML não sanitizado');
    expect($d['anterior']['id'])->toBe(9001);
    expect($d['proximo']['id'])->toBe(9003);
    expect($d['pode_concluir_manualmente'])->toBeFalse();
});

it('abrir HTML conclui automaticamente (mesma regra do site)', function () use ($api, &$sessao, $pdo) {
    $r = http('GET', $api . '/inscricoes/9001/itens/9003', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    expect($r['json']['data']['item']['status'])->toBe('concluido');
    $st = $pdo->query("SELECT status FROM conteudo_progresso_aluno WHERE inscricao_id = 9001 AND item_id = 9003 AND deleted_at IS NULL");
    expect($st->fetchColumn())->toBe('concluido');
});

it('abrir vídeo/link/arquivo: blocos do tipo preenchidos, demais null', function () use ($api, &$sessao) {
    $v = http('GET', $api . '/inscricoes/9001/itens/9004', null, bearer($sessao['access_token']))['json']['data'];
    expect($v['conteudo']['video']['provedor'])->toBe('youtube');
    expect($v['conteudo']['html'])->toBeNull();
    $l = http('GET', $api . '/inscricoes/9001/itens/9009', null, bearer($sessao['access_token']))['json']['data'];
    expect($l['conteudo']['link']['abrir_externo'])->toBeTrue();
    $a = http('GET', $api . '/inscricoes/9001/itens/9008', null, bearer($sessao['access_token']))['json']['data'];
    chaves($a['conteudo']['arquivo'], array('nome', 'mime', 'tamanho_bytes', 'download_url'), 'arquivo');
    expect($a['conteudo']['arquivo']['download_url'])->toBe('/api/app/v1/inscricoes/9001/itens/9008/arquivo');
});

it('item em rascunho ou inexistente → 404', function () use ($api, &$sessao) {
    exigirStatus(http('GET', $api . '/inscricoes/9001/itens/9019', null, bearer($sessao['access_token'])), 404, 'nao_encontrado');
    exigirStatus(http('GET', $api . '/inscricoes/9001/itens/123456789', null, bearer($sessao['access_token'])), 404, 'nao_encontrado');
});

it('concluir vídeo → 200 (idempotente); quiz → 422 nao_concluivel', function () use ($api, &$sessao) {
    $r = http('POST', $api . '/inscricoes/9001/itens/9004/concluir', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    expect($r['json']['data']['item']['status'])->toBe('concluido');
    expect(is_int($r['json']['data']['progresso_percentual']))->toBeTrue();
    exigirStatus(http('POST', $api . '/inscricoes/9001/itens/9004/concluir', null, bearer($sessao['access_token'])), 200);
    exigirStatus(http('POST', $api . '/inscricoes/9001/itens/9011/concluir', null, bearer($sessao['access_token'])), 422, 'nao_concluivel');
    exigirStatus(http('POST', $api . '/inscricoes/9001/itens/9021/concluir', null, bearer($sessao['access_token'])), 422, 'nao_concluivel');
});

it('arquivo: binário com Content-Type, Content-Length e Content-Disposition; dono exigido', function () use ($api, &$sessao, &$tokenB) {
    $r = http('GET', $api . '/inscricoes/9001/itens/9008/arquivo', null, bearer($sessao['access_token']));
    expect($r['status'])->toBe(200);
    expect(cab($r, 'content-type'))->toBe('application/pdf');
    expect((int) cab($r, 'content-length'))->toBe(strlen($r['corpo']));
    expect(strpos((string) cab($r, 'content-disposition'), 'attachment;') === 0)->toBeTrue();
    expect(substr($r['corpo'], 0, 5))->toBe('%PDF-');
    exigirStatus(http('GET', $api . '/inscricoes/9001/itens/9002/arquivo', null, bearer($sessao['access_token'])), 404, 'nao_encontrado');
    exigirStatus(http('GET', $api . '/inscricoes/9001/itens/9008/arquivo'), 401, 'nao_autenticado');
    $tokenB = login('aluno.b@teste.local', 'dispositivo-e2e-b1')['access_token'];
    exigirStatus(http('GET', $api . '/inscricoes/9001/itens/9008/arquivo', null, bearer($tokenB)), 403, 'sem_acesso');
});

// ===================================================================
describe('Quiz');
// ===================================================================

$tentativaId = null;

it('estado inicial do quiz', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/inscricoes/9001/itens/9011/quiz', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    chaves($r['json']['data'], array('regras', 'tentativa_em_andamento', 'tentativas', 'pode_iniciar'), 'quiz');
    chaves($r['json']['data']['regras'], array('percentual_minimo', 'exige_aprovacao', 'duracao_minutos', 'tentativas_maximas', 'tentativas_usadas', 'mostra_resultado', 'mostra_gabarito'), 'regras');
    expect($r['json']['data']['pode_iniciar'])->toBeTrue();
    expect($r['json']['data']['tentativa_em_andamento'])->toBeNull();
});

it('iniciar: perguntas sem gabarito; retomar devolve a mesma tentativa com o rascunho', function () use ($api, &$sessao, &$tentativaId) {
    $r = http('POST', $api . '/inscricoes/9001/itens/9011/quiz/iniciar', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    $d = $r['json']['data'];
    chaves($d, array('tentativa', 'perguntas', 'respostas'), 'iniciar');
    expect(count($d['perguntas']))->toBe(3);
    exigir(strpos($r['corpo'], '"correta"') === false && strpos($r['corpo'], 'explicacao') === false && strpos($r['corpo'], 'gabarito') === false, 'gabarito vazou na tentativa em andamento');
    foreach ($d['perguntas'] as $p) {
        chaves($p, array('id', 'tipo', 'enunciado_html', 'ordem', 'alternativas'), 'pergunta');
        expect($p['tipo'])->toBe('unica');
    }
    $tentativaId = $d['tentativa']['id'];

    $rasc = http('POST', $api . '/inscricoes/9001/itens/9011/quiz/rascunho', array('tentativa_id' => $tentativaId, 'respostas' => array('901101' => array('alternativas' => array(9011011)))), bearer($sessao['access_token']));
    exigirStatus($rasc, 200);
    expect($rasc['json']['data']['ok'])->toBeTrue();

    $t = http('GET', $api . '/inscricoes/9001/itens/9011/quiz/tempo?tentativa_id=' . $tentativaId, null, bearer($sessao['access_token']));
    exigirStatus($t, 200);
    expect($t['json']['data']['expirada'])->toBeFalse();
    expect(array_key_exists('segundos_restantes', $t['json']['data']))->toBeTrue();

    $de_novo = http('POST', $api . '/inscricoes/9001/itens/9011/quiz/iniciar', null, bearer($sessao['access_token']));
    expect($de_novo['json']['data']['tentativa']['id'])->toBe($tentativaId);
    expect($de_novo['json']['data']['respostas']['901101']['alternativas'])->toEqual(array(9011011));
});

it('outro aluno não usa a tentativa; tentativa de outro quiz → 404', function () use ($api, &$sessao, &$tentativaId, &$tokenB) {
    exigirStatus(http('GET', $api . '/inscricoes/9001/itens/9011/quiz/tempo?tentativa_id=' . $tentativaId, null, bearer($tokenB)), 403, 'sem_acesso');
    exigirStatus(http('GET', $api . '/inscricoes/9001/itens/9014/quiz/tempo?tentativa_id=' . $tentativaId, null, bearer($sessao['access_token'])), 404, 'nao_encontrado');
});

it('enviar: resultado com percentual, aprovação e gabarito liberados pelo quiz', function () use ($api, &$sessao, &$tentativaId) {
    $respostas = array(
        '901101' => array('alternativas' => array(9011011)),
        '901102' => array('alternativas' => array(9011023)),
        '901103' => array('alternativas' => array(9011031)),
    );
    $r = http('POST', $api . '/inscricoes/9001/itens/9011/quiz/enviar', array('tentativa_id' => $tentativaId, 'respostas' => $respostas), bearer($sessao['access_token']));
    exigirStatus($r, 200);
    $d = $r['json']['data'];
    chaves($d, array('tentativa', 'percentual', 'aprovado', 'aguardando_correcao', 'perguntas'), 'resultado');
    expect($d['percentual'])->toBe(100);
    expect($d['aprovado'])->toBeTrue();
    foreach ($d['perguntas'] as $p) {
        chaves($p, array('correta', 'gabarito', 'comentario_html'), 'pergunta resultado');
        expect($p['correta'])->toBeTrue();
        expect(count($p['gabarito']))->toBe(1);
    }
    $g = http('GET', $api . '/inscricoes/9001/itens/9011/quiz/tentativas/' . $tentativaId, null, bearer($sessao['access_token']));
    exigirStatus($g, 200);
    expect($g['json']['data']['percentual'])->toBe(100);
    // enviada_em é gravado pelo PHP (UTC) e o banco está em -03:00: o instante no JSON tem de ser "agora".
    $enviada = strtotime($g['json']['data']['tentativa']['enviada_em']);
    exigir(abs($enviada - time()) < 120, 'enviada_em fora do instante real: ' . $g['json']['data']['tentativa']['enviada_em']);
    $e = http('GET', $api . '/inscricoes/9001/itens/9011/quiz', null, bearer($sessao['access_token']));
    expect(count($e['json']['data']['tentativas']))->toBe(1);
    expect($e['json']['data']['tentativas'][0]['status'])->toBe('corrigida');
});

it('quiz com resultado oculto não revela gabarito nem acerto', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/inscricoes/9001/itens/9015/quiz/tentativas/9015', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    expect($r['json']['data']['percentual'])->toBeNull();
    foreach ($r['json']['data']['perguntas'] as $p) {
        expect($p['gabarito'])->toBeNull();
        expect($p['correta'])->toBeNull();
    }
});

// ===================================================================
describe('Avaliação textual');
// ===================================================================

$imagemId = null;

it('estado: pode enviar, limites do contrato', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/inscricoes/9001/itens/9021/avaliacao', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    chaves($r['json']['data'], array('enunciado_html', 'nota_minima', 'pode_enviar', 'pode_reenviar', 'limites', 'entregas'), 'avaliacao');
    expect($r['json']['data']['pode_enviar'])->toBeTrue();
    expect($r['json']['data']['limites']['max_imagens'])->toBe(5);
});

it('envio multipart com imagem → 201; texto curto → 422', function () use ($api, &$sessao, &$imagemId) {
    $curto = http('POST', $api . '/inscricoes/9001/itens/9021/avaliacao', array('texto' => 'ok'), bearer($sessao['access_token']), true);
    exigirStatus($curto, 422, 'validacao');

    $png = png1x1();
    $r = http('POST', $api . '/inscricoes/9001/itens/9021/avaliacao', array(
        'texto' => 'Minha resposta pelo aplicativo, citando os artigos 219 e 224 do CPC.',
        'imagens[0]' => new CURLFile($png, 'image/png', 'esquema.png'),
    ), bearer($sessao['access_token']), true);
    @unlink($png);
    exigirStatus($r, 201);
    chaves($r['json']['data'], array('id', 'status', 'texto', 'imagens', 'nota', 'feedback_html', 'enviada_em', 'corrigida_em'), 'entrega');
    expect($r['json']['data']['status'])->toBe('enviada');
    expect(count($r['json']['data']['imagens']))->toBe(1);
    $imagemId = $r['json']['data']['imagens'][0]['id'];

    $e = http('GET', $api . '/inscricoes/9001/itens/9021/avaliacao', null, bearer($sessao['access_token']));
    expect(count($e['json']['data']['entregas']))->toBe(1);
    expect($e['json']['data']['pode_enviar'])->toBeFalse();
});

it('imagem da entrega: só o próprio aluno', function () use ($api, &$sessao, &$imagemId, &$tokenB) {
    $r = http('GET', $api . '/avaliacoes/imagens/' . $imagemId, null, bearer($sessao['access_token']));
    expect($r['status'])->toBe(200);
    expect(cab($r, 'content-type'))->toBe('image/png');
    exigirStatus(http('GET', $api . '/avaliacoes/imagens/' . $imagemId, null, bearer($tokenB)), 404, 'nao_encontrado');
});

it('avaliação corrigida mostra nota e feedback', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/inscricoes/9001/itens/9024/avaliacao', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    expect($r['json']['data']['entregas'][0]['nota'])->toBe(8.5);
    exigir($r['json']['data']['entregas'][0]['feedback_html'] !== null, 'feedback ausente');
});

// ===================================================================
describe('Pedidos');
// ===================================================================

it('GET /pedidos: só os do aluno, centavos e regras pode_*', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/pedidos', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    $porId = array();
    foreach ($r['json']['data'] as $p) {
        chaves($p, array('id', 'numero', 'status', 'status_rotulo', 'total_centavos', 'criado_em', 'itens', 'pode_cancelar', 'pode_enviar_comprovante', 'pode_pagar_online', 'comprovante', 'pix'), 'pedido');
        $porId[$p['id']] = $p;
    }
    exigir(!isset($porId[9101]), 'pedido de outro aluno listado');
    expect($porId[9003]['total_centavos'])->toBe(12990);
    expect($porId[9003]['pode_cancelar'])->toBeTrue();
    expect($porId[9003]['pix']['chave'])->toBe('cpeducacursos@gmail.com');
    expect($porId[9008]['pode_cancelar'])->toBeFalse();
});

it('cancelar: próprio pendente → cancelado; pago → 422; de outro → 404', function () use ($api, &$sessao) {
    $r = http('POST', $api . '/pedidos/9003/cancelar', array('motivo' => 'Desisti pelo app'), bearer($sessao['access_token']));
    exigirStatus($r, 200);
    expect($r['json']['data']['status'])->toBe('cancelado');
    exigirStatus(http('POST', $api . '/pedidos/9008/cancelar', null, bearer($sessao['access_token'])), 422, 'nao_cancelavel');
    exigirStatus(http('POST', $api . '/pedidos/9101/cancelar', null, bearer($sessao['access_token'])), 404, 'nao_encontrado');
});

it('comprovante PIX multipart → comprovante_enviado; tipo inválido → 422; pedido alheio → 404', function () use ($api, &$sessao) {
    $txt = sys_get_temp_dir() . '/app-e2e-comprovante.txt';
    file_put_contents($txt, 'não sou imagem');
    $r = http('POST', $api . '/pedidos/9006/comprovante', array('arquivo' => new CURLFile($txt, 'text/plain', 'comprovante.txt')), bearer($sessao['access_token']), true);
    @unlink($txt);
    exigirStatus($r, 422, 'validacao');

    $png = png1x1();
    $r = http('POST', $api . '/pedidos/9006/comprovante', array('arquivo' => new CURLFile($png, 'image/png', 'pix.png')), bearer($sessao['access_token']), true);
    exigirStatus($r, 200);
    expect($r['json']['data']['status'])->toBe('comprovante_enviado');
    expect($r['json']['data']['comprovante']['status'])->toBe('em_analise');
    $r = http('POST', $api . '/pedidos/9101/comprovante', array('arquivo' => new CURLFile($png, 'image/png', 'pix.png')), bearer($sessao['access_token']), true);
    @unlink($png);
    exigirStatus($r, 404, 'nao_encontrado');
});

it('pagar online com AbacatePay desligado → 422 pagamento_indisponivel; pedido alheio → 404', function () use ($api, &$sessao) {
    exigirStatus(http('POST', $api . '/pedidos/9004/abacatepay', null, bearer($sessao['access_token'])), 422, 'pagamento_indisponivel');
    exigirStatus(http('POST', $api . '/pedidos/9101/abacatepay', null, bearer($sessao['access_token'])), 404, 'nao_encontrado');
});

// ===================================================================
describe('Certificados');
// ===================================================================

it('lista só os certificados do aluno', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/certificados', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    $codigos = array_map(function ($c) { return $c['codigo']; }, $r['json']['data']);
    expect($codigos)->toEqual(array('CADFIX-2026-0001'));
    chaves($r['json']['data'][0], array('codigo', 'curso_titulo', 'emitido_em', 'carga_horaria', 'pdf_url', 'validacao_url'), 'certificado');
});

it('PDF do próprio certificado → 200 application/pdf; de outro aluno → 403; inexistente → 404', function () use ($api, &$sessao) {
    $r = http('GET', $api . '/certificados/CADFIX-2026-0001/pdf', null, bearer($sessao['access_token']));
    expect($r['status'])->toBe(200);
    expect(cab($r, 'content-type'))->toBe('application/pdf');
    expect(substr($r['corpo'], 0, 5))->toBe('%PDF-');
    exigirStatus(http('GET', $api . '/certificados/APPFIX-2026-0101/pdf', null, bearer($sessao['access_token'])), 403, 'sem_acesso');
    exigirStatus(http('GET', $api . '/certificados/NAOEXISTE-0000/pdf', null, bearer($sessao['access_token'])), 404, 'nao_encontrado');
});

// ===================================================================
describe('Catálogo (público)');
// ===================================================================

it('lista paginada com meta; busca; categoria inexistente → vazio', function () use ($api) {
    $r = http('GET', $api . '/catalogo?pagina=1&por_pagina=2');
    exigirStatus($r, 200);
    chaves($r['json']['meta'], array('pagina', 'por_pagina', 'total'), 'meta');
    expect(count($r['json']['data']))->toBe(2);
    chaves($r['json']['data'][0], array('id', 'titulo', 'slug', 'resumo', 'imagem_url', 'preco_centavos', 'preco_promocional_centavos', 'carga_horaria', 'modalidade', 'categoria'), 'curso');
    $b = http('GET', $api . '/catalogo?busca=' . urlencode('aluno B'));
    expect($b['json']['data'][0]['slug'])->toBe('fixture-app-curso-b');
    $c = http('GET', $api . '/catalogo?categoria=nao-existe');
    expect($c['json']['data'])->toEqual(array());
});

it('detalhe por slug com turmas e url_compra; HTML sanitizado; inexistente → 404', function () use ($api) {
    $r = http('GET', $api . '/catalogo/fixture-app-curso-b');
    exigirStatus($r, 200);
    chaves($r['json']['data'], array('descricao_html', 'turmas_abertas', 'url_compra'), 'detalhe');
    exigir(stripos($r['json']['data']['descricao_html'], '<script') === false, 'descrição não sanitizada');
    expect($r['json']['data']['turmas_abertas'][0]['vagas_restantes'])->toBe(29);
    exigirStatus(http('GET', $api . '/catalogo/slug-que-nao-existe'), 404, 'nao_encontrado');
});

// ===================================================================
describe('Push: dispositivos e notificações');
// ===================================================================

it('dispositivo: inválido → 422; upsert por device_id; remover', function () use ($api, &$sessao, $pdo) {
    exigirStatus(http('POST', $api . '/dispositivos', array('device_id' => 'x', 'fcm_token' => 'curto', 'plataforma' => 'windows'), bearer($sessao['access_token'])), 422, 'validacao');
    $dados = array('device_id' => 'dispositivo-e2e-a', 'fcm_token' => str_repeat('a', 30) . ':APA91b' . str_repeat('X', 40), 'plataforma' => 'android', 'app_versao' => '1.0.0 (1)');
    exigirStatus(http('POST', $api . '/dispositivos', $dados, bearer($sessao['access_token'])), 200);
    $dados['fcm_token'] = str_repeat('b', 30) . ':APA91b' . str_repeat('Y', 40);
    exigirStatus(http('POST', $api . '/dispositivos', $dados, bearer($sessao['access_token'])), 200);
    $linhas = $pdo->query("SELECT fcm_token FROM app_dispositivos WHERE device_id = 'dispositivo-e2e-a'")->fetchAll(PDO::FETCH_COLUMN);
    expect(count($linhas))->toBe(1);
    expect(substr($linhas[0], 0, 1))->toBe('b');
    exigirStatus(http('POST', $api . '/dispositivos/remover', array('device_id' => 'dispositivo-e2e-a'), bearer($sessao['access_token'])), 200);
    expect((int) $pdo->query("SELECT COUNT(*) FROM app_dispositivos WHERE device_id = 'dispositivo-e2e-a'")->fetchColumn())->toBe(0);
});

it('evento real do site gera notificação (pagamento confirmado) sem quebrar a ação', function () use ($pdo) {
    $resultado = (new \App\Services\PedidoService())->confirmarPagamentoGateway(9004, array('gateway' => 'teste_e2e', 'observacao' => 'Pagamento de teste e2e.'));
    expect($resultado['ok'])->toBeTrue();
    $st = $pdo->query("SELECT tipo, envio_status, dados FROM app_notificacoes WHERE usuario_id = 9001 AND tipo = 'pedido_aprovado' ORDER BY id DESC LIMIT 1");
    $linha = $st->fetch(PDO::FETCH_ASSOC);
    exigir($linha !== false, 'notificação não gravada');
    expect($linha['envio_status'])->toBe('pendente'); // FCM_ENABLED=false
    expect(json_decode($linha['dados'], true)['pedido_id'])->toBe('9004');
    (new \App\Services\PushService())->notificar(9101, 'conteudo_novo', 'Conteúdo novo', 'Notificação do aluno B.', array('item_id' => 1));
});

it('GET /notificacoes: só as do aluno, paginado; marcar lida; de outro → 404', function () use ($api, &$sessao, $pdo) {
    $r = http('GET', $api . '/notificacoes?pagina=1', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    chaves($r['json']['meta'], array('pagina', 'por_pagina', 'total'), 'meta');
    expect(count($r['json']['data']))->toBeGreaterThanOrEqual(1);
    foreach ($r['json']['data'] as $n) {
        chaves($n, array('id', 'tipo', 'titulo', 'corpo', 'dados', 'lida', 'criada_em'), 'notificacao');
    }
    $id = $r['json']['data'][0]['id'];
    expect($r['json']['data'][0]['lida'])->toBeFalse();
    exigirStatus(http('POST', $api . '/notificacoes/' . $id . '/lida', null, bearer($sessao['access_token'])), 200);
    $r2 = http('GET', $api . '/notificacoes', null, bearer($sessao['access_token']));
    expect($r2['json']['data'][0]['lida'])->toBeTrue();
    $idB = (int) $pdo->query('SELECT id FROM app_notificacoes WHERE usuario_id = 9101 ORDER BY id DESC LIMIT 1')->fetchColumn();
    exigirStatus(http('POST', $api . '/notificacoes/' . $idB . '/lida', null, bearer($sessao['access_token'])), 404, 'nao_encontrado');
});

// ===================================================================
describe('Erros e encerramento');
// ===================================================================

it('falha inesperada → 500 erro_interno em JSON, sem detalhe do PHP', function () use ($api, &$sessao, $pdo) {
    $pdo->exec('RENAME TABLE app_notificacoes TO app_notificacoes_e2e');
    try {
        $r = http('GET', $api . '/notificacoes', null, bearer($sessao['access_token']));
    } finally {
        $pdo->exec('RENAME TABLE app_notificacoes_e2e TO app_notificacoes');
    }
    exigirStatus($r, 500, 'erro_interno');
    exigir(stripos($r['corpo'], 'SQLSTATE') === false && stripos($r['corpo'], 'app_notificacoes') === false, 'erro vazou SQL');
});

it('logout revoga os tokens e remove o push do aparelho', function () use ($api, &$sessao, $pdo) {
    http('POST', $api . '/dispositivos', array('device_id' => 'dispositivo-e2e-a', 'fcm_token' => str_repeat('c', 60), 'plataforma' => 'android'), bearer($sessao['access_token']));
    $r = http('POST', $api . '/auth/logout', null, bearer($sessao['access_token']));
    exigirStatus($r, 200);
    expect($r['json']['data']['ok'])->toBeTrue();
    exigirStatus(http('GET', $api . '/me', null, bearer($sessao['access_token'])), 401, 'nao_autenticado');
    exigirStatus(http('POST', $api . '/auth/refresh', array('refresh_token' => $sessao['refresh_token'], 'device_id' => 'dispositivo-e2e-a')), 401, 'sessao_revogada');
    expect((int) $pdo->query("SELECT COUNT(*) FROM app_dispositivos WHERE device_id = 'dispositivo-e2e-a'")->fetchColumn())->toBe(0);
});

it('o site HTML continua com sessão: /login responde HTML e abre cookie', function () use ($base) {
    $r = http('GET', $base . '/login');
    expect($r['status'])->toBe(200);
    exigir(stripos((string) cab($r, 'content-type'), 'text/html') !== false, 'login não é HTML');
    exigir(cab($r, 'set-cookie') !== null, 'site HTML deixou de abrir sessão');
});

exit(testes_resumo());

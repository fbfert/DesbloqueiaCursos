<?php

/**
 * Login com Google — teste HTTP de ponta a ponta (site e API do app).
 *
 * Fala com o app de verdade (php -S + tests/Api/router.php, banco de teste) e com o
 * "Google de teste" (tests/Api/google_stub.php), que assina id_tokens com a chave
 * de tests/Unit/_google_teste.php. Nada aqui fala com o Google real.
 *
 *   php tests/Api/montar_banco.php
 *   php -S 127.0.0.1:8099 -t . tests/Api/router.php
 *   php -S 127.0.0.1:8098 tests/Api/google_stub.php
 *   php tests/Api/google_e2e.php            (reaplica as fixtures no início)
 */

define('BASE_PATH', dirname(dirname(__DIR__)));

$base = rtrim((string) (getenv('APP_E2E_URL') ?: 'http://127.0.0.1:8099'), '/');
$api = $base . '/api/app/v1';
$banco = getenv('APP_TESTE_DB') ?: 'desbloqueia_app_teste';
if (!preg_match('/_teste$/', $banco)) {
    fwrite(STDERR, "RECUSADO: o banco precisa terminar em _teste.\n");
    exit(1);
}

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
require_once BASE_PATH . '/tests/Unit/_bootstrap.php';
require_once BASE_PATH . '/tests/Unit/_google_teste.php';
$pdo = testes_conectar_banco();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $banco) {
    fwrite(STDERR, "RECUSADO: conexão em banco inesperado.\n");
    exit(1);
}

// Chave pública do "Google de teste" no arquivo que o servidor lê (router.php).
$g = google_teste_chaves('chave-teste-1');
file_put_contents(BASE_PATH . '/storage/tmp/google_teste_jwks.json', json_encode(google_teste_jwks($g)));
@unlink(BASE_PATH . '/storage/cache/google_jwks_teste.json');
$pdo->exec('DELETE FROM app_limites_taxa');
// As fixtures recriam os alunos com FOREIGN_KEY_CHECKS=0: vínculos Google de execuções
// anteriores não caem em cascata e apontariam para o aluno recriado.
$pdo->exec("DELETE i FROM usuario_identidades i JOIN usuarios u ON u.id = i.usuario_id WHERE u.email LIKE '%@teste.local' OR u.email LIKE '%@gmail.test'");

const CLIENTE_WEB = 'cliente-web-teste.apps.googleusercontent.com';

echo "\nAlvo: {$base} | banco: {$banco}\n";

// -------------------------------------------------------------------
// Cliente HTTP com cookies
// -------------------------------------------------------------------

function gweb($metodo, $url, $cookies = null, $corpo = null, array $cabecalhos = array())
{
    $ch = curl_init($url);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => $cabecalhos,
    );
    if ($cookies !== null) {
        $opts[CURLOPT_COOKIEJAR] = $cookies;
        $opts[CURLOPT_COOKIEFILE] = $cookies;
    }
    if ($corpo !== null) {
        $opts[CURLOPT_POSTFIELDS] = $corpo;
    }
    curl_setopt_array($ch, $opts);
    $bruto = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tam = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $location = null;
    foreach (explode("\r\n", substr($bruto, 0, $tam)) as $linha) {
        if (stripos($linha, 'Location:') === 0) {
            $location = trim(substr($linha, 9));
        }
    }
    $corpoResp = substr($bruto, $tam);

    return array('status' => $status, 'location' => $location, 'corpo' => $corpoResp, 'json' => json_decode($corpoResp, true));
}

function gapi($caminho, array $corpo, $token = null)
{
    global $api;
    $h = array('X-App-Version: 1.0.0 (1)', 'X-App-Platform: android', 'Content-Type: application/json');
    if ($token) {
        $h[] = 'Authorization: Bearer ' . $token;
    }
    return gweb('POST', $api . $caminho, null, json_encode($corpo), $h);
}

function gget($caminho, $token)
{
    global $api;
    return gweb('GET', $api . $caminho, null, null, array('X-App-Version: 1.0.0 (1)', 'X-App-Platform: android', 'Authorization: Bearer ' . $token));
}

function caminho($location)
{
    $p = parse_url((string) $location);
    return ($p['path'] ?? '') . (isset($p['query']) ? '?' . $p['query'] : '');
}

function exigir($condicao, $mensagem)
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

/** Inicia o fluxo no site e devolve [cookies, query da URL do Google]. */
function iniciar($origem = 'v2', $redirect = '')
{
    global $base;
    $cookies = tempnam(sys_get_temp_dir(), 'g_e2e_');
    $q = '?origem=' . rawurlencode($origem) . ($redirect !== '' ? '&redirect=' . rawurlencode($redirect) : '');
    $r = gweb('GET', $base . '/login/google' . $q, $cookies);
    exigir($r['status'] === 302, "início respondeu {$r['status']}");
    exigir(strpos((string) $r['location'], 'https://accounts.google.com/o/oauth2/v2/auth?') === 0, 'não redirecionou ao Google: ' . $r['location']);
    parse_str((string) parse_url($r['location'], PHP_URL_QUERY), $query);
    return array($cookies, $query);
}

/** Simula a volta do Google com as claims escolhidas. */
function voltar($cookies, array $query, array $claims, array $extra = array())
{
    global $base;
    $claims = array_merge(array('aud' => CLIENTE_WEB, 'nonce' => $query['nonce'], 'email_verified' => true), $claims);
    $code = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
    $params = array_merge(array('state' => $query['state'], 'code' => $code), $extra);
    return gweb('GET', $base . '/login/google/callback?' . http_build_query($params), $cookies);
}

function token($html)
{
    return preg_match('/name="_token" value="([^"]+)"/', (string) $html, $m) ? $m[1] : '';
}

function mensagemNoLogin($cookies, $destino)
{
    global $base;
    $pagina = gweb('GET', $base . $destino, $cookies);
    return html_entity_decode($pagina['corpo'], ENT_QUOTES, 'UTF-8');
}

$sufixo = bin2hex(random_bytes(3));
$ctx = array();

// ===================================================================
describe('Site — fluxo de redirecionamento');
// ===================================================================

it('início leva ao Google com state, nonce, PKCE S256 e o callback', function () {
    list(, $q) = iniciar();
    exigir($q['client_id'] === CLIENTE_WEB, 'client_id errado');
    exigir($q['code_challenge_method'] === 'S256' && strlen($q['code_challenge']) === 43, 'PKCE ausente');
    exigir(strlen($q['state']) >= 43 && strlen($q['nonce']) >= 43, 'state/nonce curtos');
    exigir(substr($q['redirect_uri'], -22) === '/login/google/callback', 'redirect_uri errado');
});

it('pessoa nova: conta criada, sessão aberta e tela "Complete seu cadastro"', function () use ($pdo, $sufixo, &$ctx, $base) {
    list($cookies, $q) = iniciar('v2', '/v2/aluno/');
    $email = "nova.site.$sufixo@gmail.test";
    $r = voltar($cookies, $q, array('sub' => "site-novo-$sufixo", 'email' => $email, 'name' => 'Joana Google'));
    exigir($r['status'] === 302 && caminho($r['location']) === '/conta/completar', "destino: {$r['status']} {$r['location']}");
    $u = $pdo->query('SELECT * FROM usuarios WHERE email = ' . $pdo->quote($email))->fetch();
    exigir($u && $u['cpf'] === null && $u['cadastro_origem'] === 'google', 'conta não criada como esperado');
    $aluno = gweb('GET', $base . '/v2/aluno/', $cookies);
    exigir($aluno['status'] === 200, "área do aluno respondeu {$aluno['status']} " . $aluno['location']);
    $ctx['cookiesNovo'] = $cookies;
    $ctx['queryNovo'] = $q;
    $ctx['emailNovo'] = $email;
});

it('"Complete seu cadastro": CPF inválido é recusado com mensagem', function () use (&$ctx, $base) {
    $pagina = gweb('GET', $base . '/conta/completar', $ctx['cookiesNovo']);
    exigir($pagina['status'] === 200 && strpos($pagina['corpo'], 'Complete seu cadastro') !== false, "tela respondeu {$pagina['status']}");
    $r = gweb('POST', $base . '/conta/completar', $ctx['cookiesNovo'], http_build_query(array('_token' => token($pagina['corpo']), 'cpf' => '529.982.247-26')));
    exigir(caminho($r['location']) === '/conta/completar', "inválido foi para {$r['location']}");
    $de_novo = html_entity_decode(gweb('GET', $base . '/conta/completar', $ctx['cookiesNovo'])['corpo'], ENT_QUOTES, 'UTF-8');
    exigir(strpos($de_novo, 'Informe um CPF válido.') !== false, 'mensagem de CPF inválido ausente');
});

it('"Fazer isso depois" segue ao destino e a conta continua sem CPF', function () use (&$ctx, $base, $pdo) {
    $pagina = gweb('GET', $base . '/conta/completar', $ctx['cookiesNovo']);
    $r = gweb('POST', $base . '/conta/completar', $ctx['cookiesNovo'], http_build_query(array('_token' => token($pagina['corpo']), 'acao' => 'depois')));
    exigir(caminho($r['location']) === '/v2/aluno/', "depois foi para {$r['location']}");
    exigir($pdo->query('SELECT cpf FROM usuarios WHERE email = ' . $pdo->quote($ctx['emailNovo']))->fetchColumn() === null, 'CPF deveria seguir vazio');
    $aluno = gweb('GET', $base . '/v2/aluno/', $ctx['cookiesNovo']);
    exigir(strpos($aluno['corpo'], 'Falta o seu CPF.') !== false, 'lembrete de CPF ausente na área do aluno');
});

it('Minha conta: CPF editável, conta Google vinculada e sem opção de desvincular (sem senha)', function () use (&$ctx, $base) {
    $conta = html_entity_decode(gweb('GET', $base . '/v2/minha-conta', $ctx['cookiesNovo'])['corpo'], ENT_QUOTES, 'UTF-8');
    exigir(preg_match('/<input[^>]*name="cpf"/', $conta) === 1, 'CPF deveria ser editável');
    exigir(strpos($conta, 'Conta Google vinculada') !== false, 'bloco da conta Google ausente');
    exigir(strpos($conta, 'Desvincular conta Google') === false, 'não deveria oferecer desvincular sem senha');
});

it('Minha conta: informar o CPF grava e ele vira somente leitura', function () use (&$ctx, $base, $pdo) {
    $pagina = gweb('GET', $base . '/v2/minha-conta', $ctx['cookiesNovo']);
    $cpf = testes_cpf_livre($pdo);
    $r = gweb('POST', $base . '/v2/minha-conta', $ctx['cookiesNovo'], http_build_query(array(
        '_token' => token($pagina['corpo']), 'nome' => 'Joana Google', 'email' => $ctx['emailNovo'], 'cpf' => $cpf, 'telefone' => '', 'estado' => '', 'cidade' => '',
    )));
    exigir(caminho($r['location']) === '/v2/minha-conta', "salvar foi para {$r['location']}");
    $u = $pdo->query('SELECT cpf, cadastro_status FROM usuarios WHERE email = ' . $pdo->quote($ctx['emailNovo']))->fetch();
    exigir($u['cpf'] === $cpf && $u['cadastro_status'] === 'completo', 'CPF não gravado: ' . json_encode($u));
    $conta = gweb('GET', $base . '/v2/minha-conta', $ctx['cookiesNovo'])['corpo'];
    exigir(preg_match('/<input[^>]*name="cpf"/', $conta) === 0 && strpos($conta, 'readonly') !== false, 'CPF deveria estar somente leitura');
    exigir(strpos(gweb('GET', $base . '/v2/aluno/', $ctx['cookiesNovo'])['corpo'], 'Falta o seu CPF.') === false, 'lembrete deveria sumir após salvar o CPF');
});

it('retorno repetido é recusado: "Sua tentativa de login expirou"', function () use (&$ctx, $sufixo) {
    $r = voltar($ctx['cookiesNovo'], $ctx['queryNovo'], array('sub' => "site-novo-$sufixo", 'email' => $ctx['emailNovo']));
    exigir($r['status'] === 302 && strpos(caminho($r['location']), '/v2/login') === 0, "repetição foi para {$r['location']}");
    exigir(strpos(mensagemNoLogin($ctx['cookiesNovo'], caminho($r['location'])), 'Sua tentativa de login expirou. Tente novamente.') !== false, 'mensagem de expiração ausente');
});

it('state trocado é recusado', function () use ($sufixo) {
    list($cookies, $q) = iniciar();
    $q['state'] = 'outro-state';
    $r = voltar($cookies, $q, array('sub' => "x-$sufixo", 'email' => "x.$sufixo@gmail.test"));
    exigir(strpos(mensagemNoLogin($cookies, caminho($r['location'])), 'Sua tentativa de login expirou') !== false, 'state trocado não foi recusado');
});

it('cancelar no Google: "Login com o Google cancelado."', function () {
    list($cookies, $q) = iniciar();
    $r = gweb('GET', $GLOBALS['base'] . '/login/google/callback?' . http_build_query(array('state' => $q['state'], 'error' => 'access_denied')), $cookies);
    exigir(strpos(mensagemNoLogin($cookies, caminho($r['location'])), 'Login com o Google cancelado.') !== false, 'mensagem de cancelamento ausente');
});

it('Google recusa a troca do código: mensagem de indisponibilidade', function () {
    list($cookies, $q) = iniciar();
    $r = gweb('GET', $GLOBALS['base'] . '/login/google/callback?' . http_build_query(array('state' => $q['state'], 'code' => 'falhar')), $cookies);
    exigir(strpos(mensagemNoLogin($cookies, caminho($r['location'])), 'Não foi possível entrar com o Google agora.') !== false, 'mensagem de falha ausente');
});

it('nonce diferente do da sessão é recusado', function () use ($sufixo, $pdo) {
    list($cookies, $q) = iniciar();
    $r = voltar($cookies, $q, array('sub' => "nonce-$sufixo", 'email' => "nonce.$sufixo@gmail.test", 'nonce' => 'forjado'));
    exigir(strpos(mensagemNoLogin($cookies, caminho($r['location'])), 'Não foi possível entrar com o Google agora.') !== false, 'nonce forjado não foi recusado');
    exigir((int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email = 'nonce.$sufixo@gmail.test'")->fetchColumn() === 0, 'criou conta com nonce forjado');
});

it('conta existente com e-mail verificado: vincula e vai ao destino pedido', function () use ($pdo, $sufixo, &$ctx) {
    list($cookies, $q) = iniciar('v2', '/v2/aluno/?aba=pedidos');
    $ctx['cookiesB'] = $cookies;
    $r = voltar($cookies, $q, array('sub' => "site-b-$sufixo", 'email' => 'aluno.b@teste.local'));
    exigir(caminho($r['location']) === '/v2/aluno/?aba=pedidos', "destino: {$r['location']}");
    exigir((int) $pdo->query("SELECT COUNT(*) FROM usuario_identidades i JOIN usuarios u ON u.id = i.usuario_id WHERE u.email = 'aluno.b@teste.local' AND i.sub = 'site-b-$sufixo'")->fetchColumn() === 1, 'vínculo não gravado');
});

it('conta com senha pode desvincular o Google em Minha conta', function () use (&$ctx, $base, $pdo, $sufixo) {
    $conta = gweb('GET', $base . '/v2/minha-conta', $ctx['cookiesB']);
    exigir(strpos($conta['corpo'], 'Desvincular conta Google') !== false, 'botão de desvincular ausente');
    $r = gweb('POST', $base . '/v2/minha-conta/google/desvincular', $ctx['cookiesB'], http_build_query(array('_token' => token($conta['corpo']))));
    exigir(caminho($r['location']) === '/v2/minha-conta', "desvincular foi para {$r['location']}");
    exigir((int) $pdo->query("SELECT COUNT(*) FROM usuario_identidades WHERE sub = 'site-b-$sufixo'")->fetchColumn() === 0, 'vínculo não removido');
    exigir((int) $pdo->query("SELECT COUNT(*) FROM lixeira WHERE entidade_tipo = 'usuario_identidade' AND justificativa = 'Desvinculado pelo próprio usuário'")->fetchColumn() >= 1, 'desvínculo fora da lixeira');
});

it('destino externo é descartado', function () use ($sufixo) {
    list($cookies, $q) = iniciar('v2', 'https://evil.example/roubo');
    $r = voltar($cookies, $q, array('sub' => "site-b-$sufixo", 'email' => 'aluno.b@teste.local'));
    exigir(caminho($r['location']) === '/v2/pos-login', "destino: {$r['location']}");
});

it('conta inativa não entra', function () use ($sufixo) {
    list($cookies, $q) = iniciar();
    $r = voltar($cookies, $q, array('sub' => "inat-$sufixo", 'email' => 'inativo@teste.local'));
    exigir(strpos(mensagemNoLogin($cookies, caminho($r['location'])), 'Usuário sem permissão de acesso.') !== false, 'conta inativa entrou');
});

it('e-mail não verificado de conta existente é recusado', function () use ($sufixo) {
    list($cookies, $q) = iniciar();
    $r = voltar($cookies, $q, array('sub' => "nv-$sufixo", 'email' => 'aluno.caderno@teste.local', 'email_verified' => false));
    exigir(strpos(mensagemNoLogin($cookies, caminho($r['location'])), 'Seu e-mail no Google não está verificado.') !== false, 'e-mail não verificado entrou');
});

// ===================================================================
describe('App — POST /auth/google, GET /me e POST /me/cpf');
// ===================================================================

function tokenApp(array $claims)
{
    global $g;
    return google_teste_token($g, array_merge(array('aud' => CLIENTE_WEB, 'iat' => time(), 'exp' => time() + 3600, 'email_verified' => true), $claims));
}

function loginApp($idToken, array $extra = array())
{
    return gapi('/auth/google', array_merge(array('id_token' => $idToken, 'device_id' => 'aparelho-google-e2e', 'device_name' => 'Pixel de teste'), $extra));
}

it('pessoa nova: 200 com tokens, usuario_novo e pendência de CPF', function () use ($sufixo, &$ctx) {
    $r = loginApp(tokenApp(array('sub' => "app-novo-$sufixo", 'email' => "app.novo.$sufixo@gmail.test", 'name' => 'Ana App')));
    exigir($r['status'] === 200, "status {$r['status']} " . substr($r['corpo'], 0, 300));
    $d = $r['json']['data'];
    exigir(!empty($d['access_token']) && !empty($d['refresh_token']), 'tokens ausentes');
    exigir($d['usuario_novo'] === true, 'usuario_novo deveria ser true');
    exigir($d['usuario']['pendencias'] === array('cpf') && $d['usuario']['cpf'] === null, 'pendências erradas: ' . json_encode($d['usuario']));
    $ctx['tokenApp'] = $d['access_token'];
});

it('mesma conta de novo: usuario_novo false', function () use ($sufixo) {
    $r = loginApp(tokenApp(array('sub' => "app-novo-$sufixo", 'email' => "app.novo.$sufixo@gmail.test")), array('device_id' => 'aparelho-google-e2e-2'));
    exigir($r['status'] === 200 && $r['json']['data']['usuario_novo'] === false, "status {$r['status']}");
});

it('token do iOS (aud na lista do app) também vale', function () use ($sufixo) {
    $r = loginApp(tokenApp(array('aud' => 'cliente-ios-teste.apps.googleusercontent.com', 'sub' => "app-novo-$sufixo", 'email' => "app.novo.$sufixo@gmail.test")), array('device_id' => 'aparelho-google-e2e-3'));
    exigir($r['status'] === 200, "status {$r['status']}");
});

it('GET /me: cpf nulo e pendencias ["cpf"]', function () use (&$ctx) {
    $r = gget('/me', $ctx['tokenApp']);
    exigir($r['status'] === 200 && $r['json']['data']['pendencias'] === array('cpf') && $r['json']['data']['cpf'] === null, substr($r['corpo'], 0, 300));
});

it('POST /me sem CPF atualiza o telefone (não exige CPF)', function () use (&$ctx) {
    $r = gapi('/me', array('telefone' => '(11) 95555-4433'), $ctx['tokenApp']);
    exigir($r['status'] === 200 && $r['json']['data']['telefone'] !== null && $r['json']['data']['pendencias'] === array('cpf'), "status {$r['status']} " . substr($r['corpo'], 0, 300));
});

it('POST /me/cpf: inválido 422, de outra conta 409 cpf_em_uso', function () use (&$ctx) {
    $r = gapi('/me/cpf', array('cpf' => '529.982.247-26'), $ctx['tokenApp']);
    exigir($r['status'] === 422 && $r['json']['erro']['codigo'] === 'validacao' && $r['json']['erro']['campos']['cpf'] === 'Informe um CPF válido.', substr($r['corpo'], 0, 300));
    $r = gapi('/me/cpf', array('cpf' => '529.982.247-25'), $ctx['tokenApp']);
    exigir($r['status'] === 409 && $r['json']['erro']['codigo'] === 'cpf_em_uso', substr($r['corpo'], 0, 300));
});

it('POST /me/cpf válido: 200 e pendências vazias; de novo 409 cpf_ja_informado', function () use (&$ctx, $pdo) {
    $r = gapi('/me/cpf', array('cpf' => testes_cpf_livre($pdo)), $ctx['tokenApp']);
    exigir($r['status'] === 200 && $r['json']['data']['pendencias'] === array() && $r['json']['data']['cpf'] !== null, substr($r['corpo'], 0, 300));
    $r = gapi('/me/cpf', array('cpf' => testes_cpf_livre($pdo)), $ctx['tokenApp']);
    exigir($r['status'] === 409 && $r['json']['erro']['codigo'] === 'cpf_ja_informado', substr($r['corpo'], 0, 300));
});

it('token de outro aplicativo: 401 google_token_invalido', function () use ($sufixo) {
    $r = loginApp(tokenApp(array('aud' => 'outro-app.apps.googleusercontent.com', 'sub' => "app-x-$sufixo", 'email' => "x.$sufixo@gmail.test")));
    exigir($r['status'] === 401 && $r['json']['erro']['codigo'] === 'google_token_invalido', "status {$r['status']}");
});

it('token expirado: 401 google_token_invalido', function () use ($sufixo) {
    $r = loginApp(tokenApp(array('exp' => time() - 120, 'sub' => "app-x-$sufixo", 'email' => "x.$sufixo@gmail.test")));
    exigir($r['status'] === 401 && $r['json']['erro']['codigo'] === 'google_token_invalido', "status {$r['status']}");
});

it('nonce enviado pelo app diferente do token: 401', function () use ($sufixo) {
    $r = loginApp(tokenApp(array('nonce' => 'n-1', 'sub' => "app-x-$sufixo", 'email' => "x.$sufixo@gmail.test")), array('nonce' => 'n-2'));
    exigir($r['status'] === 401, "status {$r['status']}");
});

it('e-mail não verificado de conta existente: 409 email_nao_verificado', function () use ($sufixo) {
    $r = loginApp(tokenApp(array('sub' => "app-nv-$sufixo", 'email' => 'aluno.caderno@teste.local', 'email_verified' => false)));
    exigir($r['status'] === 409 && $r['json']['erro']['codigo'] === 'email_nao_verificado', "status {$r['status']}");
});

it('conta inativa: 403 conta_inativa', function () use ($sufixo) {
    $r = loginApp(tokenApp(array('sub' => "app-inat-$sufixo", 'email' => 'inativo@teste.local')));
    exigir($r['status'] === 403 && $r['json']['erro']['codigo'] === 'conta_inativa', "status {$r['status']}");
});

it('campos ausentes: 422 validacao', function () {
    $r = gapi('/auth/google', array('device_id' => 'x'));
    exigir($r['status'] === 422 && isset($r['json']['erro']['campos']['id_token'], $r['json']['erro']['campos']['device_id']), "status {$r['status']}");
});

it('limite de taxa por IP: 429 muitas_tentativas com Retry-After', function () use ($pdo) {
    $pdo->exec('DELETE FROM app_limites_taxa');
    $ultimo = null;
    for ($i = 0; $i < 31; $i++) {
        $ultimo = loginApp('a.b.c');
    }
    $pdo->exec('DELETE FROM app_limites_taxa');
    exigir($ultimo['status'] === 429 && $ultimo['json']['erro']['codigo'] === 'muitas_tentativas', "status {$ultimo['status']}");
});

it('recurso desligado (alvo sem GOOGLE_APP_CLIENT_IDS): 404', function () {
    $semGoogle = rtrim((string) (getenv('APP_E2E_URL_SEM_GOOGLE') ?: 'http://127.0.0.1:8010'), '/');
    $r = gweb('POST', $semGoogle . '/api/app/v1/auth/google', null, json_encode(array('id_token' => 'a.b.c', 'device_id' => 'aparelho-google-e2e')), array('X-App-Version: 1.0.0 (1)', 'X-App-Platform: android', 'Content-Type: application/json'));
    if ($r['status'] === 0) {
        echo "      (pulado: {$semGoogle} fora do ar)\n";
        return;
    }
    exigir($r['status'] === 404 && $r['json']['erro']['codigo'] === 'nao_encontrado', "status {$r['status']}");
});

$codigo = testes_resumo();
foreach (array('cookiesNovo', 'cookiesB') as $c) {
    if (!empty($ctx[$c])) {
        @unlink($ctx[$c]);
    }
}
exit($codigo);

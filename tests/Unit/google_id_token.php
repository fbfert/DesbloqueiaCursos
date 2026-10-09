<?php

/**
 * GoogleIdTokenVerifier e GoogleConfig — login com Google.
 *
 * O QUE ESTE TESTE PROTEGE
 *   - só aceita id_token RS256 assinado por chave do JWKS, com iss do Google, aud da
 *     lista do canal, exp válido (tolerância de 60 s) e nonce igual ao esperado;
 *   - kid desconhecido força nova busca das chaves, no máximo uma por minuto;
 *   - a configuração desliga o recurso sem as credenciais.
 *
 * Sem banco e sem rede: o "Google" é um par RSA gerado no teste.
 *   php tests/Unit/google_id_token.php
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_google_teste.php';

use App\Services\Google\GoogleConfig;
use App\Services\Google\GoogleIdTokenVerifier;

require_once BASE_PATH . '/app/Core/Env.php';

$g = google_teste_chaves('chave-teste-1');
$g2 = google_teste_chaves('chave-teste-2');
$aud = 'cliente-web-teste.apps.googleusercontent.com';
$agora = 1790000000;

$cache = sys_get_temp_dir() . '/google_jwks_teste_' . bin2hex(random_bytes(4)) . '.json';
register_shutdown_function(function () use ($cache) {
    @unlink($cache);
});

$buscas = 0;
$jwksAtual = google_teste_jwks($g);
$buscar = function () use (&$buscas, &$jwksAtual) {
    $buscas++;
    return array('ok' => true, 'chaves' => $jwksAtual['keys'], 'max_age' => 20000);
};
$relogio = function () use (&$agora) {
    return $agora;
};
$v = new GoogleIdTokenVerifier($buscar, $relogio, $cache);

$token = function (array $claims = array(), array $cab = array(), $par = null) use ($g, $agora) {
    return google_teste_token($par ?: $g, array_merge(array('iat' => $agora, 'exp' => $agora + 3600), $claims), $cab);
};

describe('Token válido');

it('aceita token assinado com iss, aud, exp e nonce corretos', function () use ($v, $token, $aud) {
    $r = $v->verificar($token(array('nonce' => 'n-123')), array($aud), 'n-123');
    expect($r['ok'])->toBeTrue();
    expect($r['claims']['sub'])->toBe('1000000000000000000001');
});

it('iss sem https também é do Google', function () use ($v, $token, $aud) {
    expect($v->verificar($token(array('iss' => 'accounts.google.com')), array($aud))['ok'])->toBeTrue();
});

it('aud em lista com mais de um client ID', function () use ($v, $token) {
    expect($v->verificar($token(array('aud' => 'android')), array('web', 'android'))['ok'])->toBeTrue();
});

describe('Recusas');

it('assinatura adulterada', function () use ($v, $token, $aud) {
    $partes = explode('.', $token());
    $claims = json_decode(GoogleIdTokenVerifier::base64UrlDecode($partes[1]), true);
    $claims['email'] = 'invasor@teste.local';
    $partes[1] = google_teste_b64url(json_encode($claims));
    expect($v->verificar(implode('.', $partes), array($aud))['motivo'])->toBe('assinatura');
});

it('alg diferente de RS256', function () use ($v, $token, $aud) {
    expect($v->verificar($token(array(), array('alg' => 'HS256')), array($aud))['motivo'])->toBe('algoritmo');
    expect($v->verificar($token(array(), array('alg' => 'none')), array($aud))['motivo'])->toBe('algoritmo');
});

it('aud de outro aplicativo', function () use ($v, $token, $aud) {
    expect($v->verificar($token(array('aud' => 'outro-app')), array($aud))['motivo'])->toBe('audiencia');
});

it('iss que não é o Google', function () use ($v, $token, $aud) {
    expect($v->verificar($token(array('iss' => 'https://evil.example')), array($aud))['motivo'])->toBe('emissor');
});

it('expirado há mais de 60 s', function () use ($v, $token, $aud, $agora) {
    expect($v->verificar($token(array('exp' => $agora - 61)), array($aud))['motivo'])->toBe('expirado');
});

it('expirado há menos de 60 s ainda vale (relógio)', function () use ($v, $token, $aud, $agora) {
    expect($v->verificar($token(array('exp' => $agora - 30)), array($aud))['ok'])->toBeTrue();
});

it('nonce divergente ou ausente', function () use ($v, $token, $aud) {
    expect($v->verificar($token(array('nonce' => 'outro')), array($aud), 'n-123')['motivo'])->toBe('nonce');
    expect($v->verificar($token(), array($aud), 'n-123')['motivo'])->toBe('nonce');
});

it('formato inválido', function () use ($v, $aud) {
    expect($v->verificar('abc', array($aud))['motivo'])->toBe('formato');
    expect($v->verificar('a.b.c', array($aud))['motivo'])->toBe('formato');
});

it('token assinado por chave fora do JWKS', function () use ($v, $aud, $agora) {
    $falso = google_teste_token(array('kid' => 'chave-teste-1', 'privada' => google_teste_chaves('chave-intrusa')['privada']), array('iat' => $agora, 'exp' => $agora + 3600));
    expect($v->verificar($falso, array($aud))['motivo'])->toBe('assinatura');
});

describe('Cache e rotação das chaves');

it('chaves vêm do cache: uma busca para vários tokens', function () use ($v, $token, $aud, &$buscas) {
    $antes = $buscas;
    $v->verificar($token(), array($aud));
    $v->verificar($token(), array($aud));
    expect($buscas - $antes)->toBe(0);
});

it('kid desconhecido dentro do mesmo minuto não busca de novo', function () use ($v, $token, $aud, $g2, &$buscas) {
    $antes = $buscas;
    $r = $v->verificar($token(array(), array(), $g2), array($aud));
    expect($r['motivo'])->toBe('chave_desconhecida');
    expect($buscas - $antes)->toBe(0);
});

it('rotação: kid novo após 1 minuto busca as chaves e valida', function () use ($v, $token, $aud, $g, $g2, &$buscas, &$agora, &$jwksAtual) {
    $agora += 61;
    $jwksAtual = google_teste_jwks($g, $g2);
    $antes = $buscas;
    $r = $v->verificar($token(array('iat' => $agora, 'exp' => $agora + 3600), array(), $g2), array($aud));
    expect($r['ok'])->toBeTrue();
    expect($buscas - $antes)->toBe(1);
});

it('Google fora do ar: cache vencido ainda serve', function () use ($cache, $relogio, $token, $aud, &$agora) {
    $agora += 100000;
    $offline = new GoogleIdTokenVerifier(function () {
        return array('ok' => false, 'erro' => 'timeout');
    }, $relogio, $cache);
    expect($offline->verificar($token(array('iat' => $agora, 'exp' => $agora + 3600)), array($aud))['ok'])->toBeTrue();
});

describe('Configuração');

it('sem credenciais o site e o app ficam desligados', function () {
    putenv('GOOGLE_CLIENT_ID=');
    putenv('GOOGLE_CLIENT_SECRET=');
    putenv('GOOGLE_APP_CLIENT_IDS=');
    expect(GoogleConfig::siteAtivo())->toBeFalse();
    expect(GoogleConfig::appAtivo())->toBeFalse();
});

it('client ID sem secret não liga o site', function () {
    putenv('GOOGLE_CLIENT_ID=web.apps.googleusercontent.com');
    expect(GoogleConfig::siteAtivo())->toBeFalse();
});

it('com as chaves liga, e a lista do app ignora espaços e repetidos', function () {
    putenv('GOOGLE_CLIENT_SECRET=segredo');
    putenv('GOOGLE_APP_CLIENT_IDS= web.apps , android.apps,web.apps ');
    expect(GoogleConfig::siteAtivo())->toBeTrue();
    expect(GoogleConfig::appAtivo())->toBeTrue();
    expect(GoogleConfig::get('app_client_ids'))->toEqual(array('web.apps', 'android.apps'));
});

it('JWKS de arquivo só fora de produção', function () {
    putenv('GOOGLE_JWKS_ARQUIVO_TESTE=/tmp/x.json');
    putenv('APP_ENV=production');
    expect(GoogleConfig::get('jwks_arquivo_teste'))->toBe('');
    putenv('APP_ENV=local');
    expect(GoogleConfig::get('jwks_arquivo_teste'))->toBe('/tmp/x.json');
    putenv('GOOGLE_JWKS_ARQUIVO_TESTE');
    putenv('APP_ENV');
});

exit(testes_resumo());

<?php

/**
 * GoogleOAuthService — URL de autorização (state, nonce, PKCE) e troca do código.
 *
 * Sem rede: o transporte HTTP é injetado.
 *   php tests/Unit/google_oauth.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\Google\GoogleHttp;
use App\Services\Google\GoogleOAuthService;

require_once BASE_PATH . '/app/Core/Env.php';
putenv('GOOGLE_CLIENT_ID=web-teste.apps.googleusercontent.com');
putenv('GOOGLE_CLIENT_SECRET=segredo-de-teste');

describe('PKCE');

it('code_challenge S256 bate com o vetor do RFC 7636 (apêndice B)', function () {
    expect(GoogleOAuthService::codeChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'))->toBe('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
});

it('valores aleatórios são URL-safe e distintos', function () {
    $a = GoogleOAuthService::aleatorio();
    $b = GoogleOAuthService::aleatorio();
    expect(preg_match('/^[A-Za-z0-9_-]{43}$/', $a))->toBe(1);
    expect($a !== $b)->toBeTrue();
});

describe('URL de autorização');

$url = (new GoogleOAuthService())->urlAutorizacao('estado-1', 'nonce-1', 'verificador-1');
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

it('aponta para o Google com code + PKCE S256', function () use ($url, $q) {
    expect(strpos($url, 'https://accounts.google.com/o/oauth2/v2/auth?'))->toBe(0);
    expect($q['response_type'])->toBe('code');
    expect($q['code_challenge_method'])->toBe('S256');
    expect($q['code_challenge'])->toBe(GoogleOAuthService::codeChallenge('verificador-1'));
});

it('leva state, nonce, client_id, escopos mínimos e o callback', function () use ($q) {
    expect($q['state'])->toBe('estado-1');
    expect($q['nonce'])->toBe('nonce-1');
    expect($q['client_id'])->toBe('web-teste.apps.googleusercontent.com');
    expect($q['scope'])->toBe('openid email profile');
    expect(substr($q['redirect_uri'], -strlen('/login/google/callback')))->toBe('/login/google/callback');
});

it('nunca expõe o segredo nem o verificador', function () use ($url) {
    expect(strpos($url, 'segredo-de-teste'))->toBeFalse();
    expect(strpos($url, 'verificador-1'))->toBeFalse();
});

describe('Troca do código');

it('envia code, verifier e segredo e devolve o id_token', function () {
    $enviado = null;
    $http = new GoogleHttp(function ($metodo, $url, $cab, $corpo) use (&$enviado) {
        parse_str((string) $corpo, $enviado);
        return array('status' => 200, 'corpo' => json_encode(array('id_token' => 'a.b.c', 'access_token' => 'x')));
    });
    $r = (new GoogleOAuthService($http))->trocarCodigo('codigo-1', 'verificador-1');
    expect($r['ok'])->toBeTrue();
    expect($r['id_token'])->toBe('a.b.c');
    expect($enviado['code_verifier'])->toBe('verificador-1');
    expect($enviado['client_secret'])->toBe('segredo-de-teste');
    expect($enviado['grant_type'])->toBe('authorization_code');
});

it('erro do Google vira falha, sem registrar o código no log', function () {
    $http = new GoogleHttp(function () {
        return array('status' => 400, 'corpo' => json_encode(array('error' => 'invalid_grant')));
    });
    $log = BASE_PATH . '/storage/logs/app-' . date('Y-m-d') . '.log';
    $antes = is_file($log) ? filesize($log) : 0;
    $r = (new GoogleOAuthService($http))->trocarCodigo('codigo-secreto-xyz', 'verificador-1');
    expect($r['ok'])->toBeFalse();
    clearstatcache();
    $novo = is_file($log) ? (string) file_get_contents($log, false, null, $antes) : '';
    expect(strpos($novo, 'codigo-secreto-xyz'))->toBeFalse();
    expect(strpos($novo, 'segredo-de-teste'))->toBeFalse();
});

it('falha de rede vira falha', function () {
    $http = new GoogleHttp(function () {
        return array('status' => 0, 'corpo' => '', 'erro' => 'timeout');
    });
    expect((new GoogleOAuthService($http))->trocarCodigo('c', 'v')['ok'])->toBeFalse();
});

exit(testes_resumo());

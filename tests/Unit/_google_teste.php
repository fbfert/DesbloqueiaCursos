<?php

/**
 * Apoio aos testes do login com Google: um "Google de mentira" com par RSA próprio.
 *
 *   $g = google_teste_chaves();                 // gera (ou reaproveita) o par
 *   $jwks = google_teste_jwks($g);              // array('keys' => [...]) para o verificador
 *   $token = google_teste_token($g, $claims);   // id_token RS256 assinado
 *
 * O par fica em storage/tmp/google_teste_chave.pem para o servidor de testes da API
 * (tests/Api/router.php) e os testes enxergarem a mesma chave.
 */

function google_teste_b64url($bytes)
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function google_teste_chaves($kid = 'chave-teste-1')
{
    $dir = dirname(dirname(__DIR__)) . '/storage/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $arquivo = $dir . '/google_teste_' . preg_replace('/[^a-z0-9-]/i', '', $kid) . '.pem';

    $config = array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA);
    $cnf = getenv('OPENSSL_CONF');
    if (!$cnf && PHP_OS_FAMILY === 'Windows') {
        foreach (array(dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf', 'C:/Program Files/Common Files/SSL/openssl.cnf') as $candidato) {
            if (is_file($candidato)) {
                $config['config'] = $candidato;
                break;
            }
        }
    }

    $privada = is_file($arquivo) ? file_get_contents($arquivo) : '';
    if ($privada === '') {
        $chave = openssl_pkey_new($config);
        if ($chave === false) {
            throw new RuntimeException('openssl_pkey_new falhou: ' . openssl_error_string());
        }
        openssl_pkey_export($chave, $privada, null, $config);
        file_put_contents($arquivo, $privada);
    }

    $detalhes = openssl_pkey_get_details(openssl_pkey_get_private($privada));

    return array(
        'kid' => $kid,
        'privada' => $privada,
        'n' => google_teste_b64url($detalhes['rsa']['n']),
        'e' => google_teste_b64url($detalhes['rsa']['e']),
    );
}

function google_teste_jwks(array ...$pares)
{
    $keys = array();
    foreach ($pares as $g) {
        $keys[] = array('kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => $g['kid'], 'n' => $g['n'], 'e' => $g['e']);
    }
    return array('keys' => $keys);
}

function google_teste_token(array $g, array $claims, array $cabecalho = array())
{
    $cabecalho = array_merge(array('alg' => 'RS256', 'kid' => $g['kid'], 'typ' => 'JWT'), $cabecalho);
    $claims = array_merge(array(
        'iss' => 'https://accounts.google.com',
        'aud' => 'cliente-web-teste.apps.googleusercontent.com',
        'sub' => '1000000000000000000001',
        'email' => 'pessoa.google@teste.local',
        'email_verified' => true,
        'name' => 'Pessoa Google',
        'iat' => time(),
        'exp' => time() + 3600,
    ), $claims);

    $entrada = google_teste_b64url(json_encode($cabecalho)) . '.' . google_teste_b64url(json_encode($claims));
    openssl_sign($entrada, $assinatura, $g['privada'], OPENSSL_ALGO_SHA256);

    return $entrada . '.' . google_teste_b64url($assinatura);
}

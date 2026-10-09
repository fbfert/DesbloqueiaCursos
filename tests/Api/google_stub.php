<?php

/**
 * "Google de teste": endpoint de troca do código do login com Google.
 *
 *   php -S 127.0.0.1:8098 tests/Api/google_stub.php
 *
 * O `code` recebido é o JSON das claims desejadas em base64url (o teste monta o
 * código com o nonce que leu da URL de autorização). Responde com um id_token
 * RS256 assinado pela chave de tests/Unit/_google_teste.php. code = "falhar"
 * simula o Google recusando (400 invalid_grant).
 */

require_once dirname(__DIR__) . '/Unit/_google_teste.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/token') {
    http_response_code(404);
    echo json_encode(array('error' => 'not_found'));
    return true;
}

$code = isset($_POST['code']) ? (string) $_POST['code'] : '';
if ($code === 'falhar' || empty($_POST['code_verifier']) || ($_POST['client_secret'] ?? '') !== 'segredo-somente-teste') {
    http_response_code(400);
    echo json_encode(array('error' => 'invalid_grant'));
    return true;
}

$claims = json_decode((string) base64_decode(strtr($code, '-_', '+/')), true);
if (!is_array($claims)) {
    http_response_code(400);
    echo json_encode(array('error' => 'invalid_grant'));
    return true;
}

echo json_encode(array(
    'access_token' => 'nao-usado',
    'id_token' => google_teste_token(google_teste_chaves('chave-teste-1'), $claims),
    'token_type' => 'Bearer',
    'expires_in' => 3599,
));
return true;

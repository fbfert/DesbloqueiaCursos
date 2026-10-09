<?php

use App\Core\Env;

$listaIds = function ($valor) {
    $ids = array();
    foreach (explode(',', (string) $valor) as $id) {
        $id = trim($id);
        if ($id !== '') {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
};

$clientId = trim((string) Env::get('GOOGLE_CLIENT_ID', ''));
$clientSecret = trim((string) Env::get('GOOGLE_CLIENT_SECRET', ''));
$appClientIds = $listaIds(Env::get('GOOGLE_APP_CLIENT_IDS', ''));

return array(
    // Login com Google (OpenID Connect) — mudança OpenSpec login-google.
    //
    // DESLIGADO ATÉ HAVER CREDENCIAIS. Sem GOOGLE_CLIENT_ID + GOOGLE_CLIENT_SECRET o
    // botão some das telas e /login/google responde 404; sem GOOGLE_APP_CLIENT_IDS o
    // POST /api/app/v1/auth/google responde 404. Rollback = apagar as chaves.
    //
    // GOOGLE_APP_CLIENT_IDS: lista separada por vírgula dos client IDs cujos tokens o
    // app pode apresentar. O Credential Manager do Android emite o id_token com
    // aud = client ID WEB (o "serverClientId"), então ele entra na lista também.
    'google' => array(
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'app_client_ids' => $appClientIds,
        'site_ativo' => $clientId !== '' && $clientSecret !== '',
        'app_ativo' => !empty($appClientIds),
        // Só fora de produção: "Google de teste" dos testes ponta a ponta (tests/Api) —
        // JWKS lido de arquivo local e troca do código num servidor stub, ambos com
        // uma chave própria. Ignorados com APP_ENV=production.
        'jwks_arquivo_teste' => strtolower((string) Env::get('APP_ENV', 'production')) !== 'production'
            ? trim((string) Env::get('GOOGLE_JWKS_ARQUIVO_TESTE', ''))
            : '',
        'token_url_teste' => strtolower((string) Env::get('APP_ENV', 'production')) !== 'production'
            ? trim((string) Env::get('GOOGLE_TOKEN_URL_TESTE', ''))
            : '',
    ),
);

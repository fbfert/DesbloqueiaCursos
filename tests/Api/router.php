<?php

/**
 * Router do servidor embutido para o banco de TESTE da API do app.
 *
 * Define o ambiente (banco do container desbloqueia-test-db, FCM e e-mail
 * desligados) ANTES de carregar o app: App\Core\Env::load() não sobrescreve
 * variáveis que já existem, então um .env local, se houver, não é usado.
 * Depois entrega para tests/Smoke/router.php (mesmo front controller e mesmos
 * bloqueios de diretório da produção).
 *
 *   php -S 0.0.0.0:8099 -t . tests/Api/router.php
 *
 * Valores podem ser trocados por variáveis de ambiente antes de subir o servidor.
 */

$padroes = array(
    'APP_ENV' => 'local',
    'APP_DEBUG' => 'false',
    'APP_URL' => 'http://10.0.2.2:8099',
    'APP_KEY' => 'chave-local-somente-para-testes-da-api-do-app-000000',
    'APP_TIMEZONE' => 'America/Sao_Paulo',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '33061',
    'DB_DATABASE' => 'desbloqueia_app_teste',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => 'teste_root_123',
    'DB_CHARSET' => 'utf8mb4',
    'MAIL_ENABLED' => 'false',
    'MAIL_QUEUE_PROCESSING' => 'false',
    'OPENAI_ENABLED' => 'false',
    'ABACATEPAY_ENABLED' => 'false',
    'FCM_ENABLED' => 'false',
    'APP_MOBILE_VERSAO_MINIMA_ANDROID' => '1',
    'APP_MOBILE_VERSAO_ATUAL_ANDROID' => '1',
    'SESSION_SECURE' => 'false',
);

foreach ($padroes as $chave => $valor) {
    if (getenv($chave) === false) {
        putenv($chave . '=' . $valor);
        $_ENV[$chave] = $valor;
    }
}

return require dirname(__DIR__) . '/Smoke/router.php';

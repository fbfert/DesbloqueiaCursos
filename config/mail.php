<?php

use App\Core\Env;

return array(
    'driver' => Env::get('MAIL_DRIVER', 'smtp'),
    'enabled' => Env::get('MAIL_ENABLED', 'false') === 'true',
    'host' => Env::get('SMTP_HOST', ''),
    'port' => (int) Env::get('SMTP_PORT', '587'),
    'username' => Env::get('SMTP_USERNAME', ''),
    'password' => Env::get('SMTP_PASSWORD', ''),
    'encryption' => Env::get('SMTP_ENCRYPTION', 'tls'),
    'allow_plaintext_fallback' => Env::get('MAIL_ALLOW_PLAINTEXT_FALLBACK', 'false') === 'true',
    'from_email' => Env::get('MAIL_FROM_EMAIL', Env::get('SMTP_USERNAME', 'no-reply@polorainbow.com.br')),
    'from_name' => Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'Polo Rainbow')),
    'reply_to' => Env::get('MAIL_REPLY_TO', Env::get('MAIL_FROM_EMAIL', Env::get('SMTP_USERNAME', 'no-reply@polorainbow.com.br'))),
    'queue_processing' => Env::get('MAIL_QUEUE_PROCESSING', 'true') === 'true',
);

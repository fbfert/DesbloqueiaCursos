<?php

use App\Core\Env;

return array(
    'abacatepay' => array(
        'enabled' => Env::get('ABACATEPAY_ENABLED', 'false') === 'true',
        'mode' => Env::get('ABACATEPAY_MODE', 'dev'),
        'api_key' => Env::get('ABACATEPAY_API_KEY', ''),
        'webhook_secret' => Env::get('ABACATEPAY_WEBHOOK_SECRET', ''),
        'webhook_url_secret' => Env::get('ABACATEPAY_WEBHOOK_URL_SECRET', ''),
        'environment' => Env::get('ABACATEPAY_ENVIRONMENT', 'sandbox'),
        'base_url' => rtrim(Env::get('ABACATEPAY_BASE_URL', 'https://api.abacatepay.com/v2'), '/'),
    ),
);

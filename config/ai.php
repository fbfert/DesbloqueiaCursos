<?php

use App\Core\Env;

return array(
    'claude' => array(
        'enabled' => Env::get('ANTHROPIC_ENABLED', 'false') === 'true',
        'api_key' => Env::get('ANTHROPIC_API_KEY', ''),
        'base_url' => rtrim(Env::get('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'), '/'),
        'model' => Env::get('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929'),
        'max_tokens' => (int) Env::get('ANTHROPIC_MAX_TOKENS', '1024'),
        'temperature' => Env::get('ANTHROPIC_TEMPERATURE', '0.2'),
        'timeout' => (int) Env::get('ANTHROPIC_TIMEOUT', '30'),
    ),
);

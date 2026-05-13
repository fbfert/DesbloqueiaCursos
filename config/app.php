<?php

use App\Core\Env;

return array(
    'name' => Env::get('APP_NAME', 'Desbloqueia Cursos'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', 'false') === 'true',
    'url' => Env::get('APP_URL', 'http://localhost'),
);

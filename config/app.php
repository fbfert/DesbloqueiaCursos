<?php

use App\Core\Env;

return array(
    'name' => Env::get('APP_NAME', 'Desbloqueia Cursos'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', 'false') === 'true',
    'url' => Env::get('APP_URL', 'http://localhost'),
    'key' => Env::get('APP_KEY', ''),

    // Fase 2.13 — versão da Home servida na raiz "/". Reversível por configuração,
    // SEM alterar código. Padrão 'v1' (Home V1 permanece ativa até homologação).
    // Para virar a Home para o V2 após homologação, defina HOME_VERSION=v2 no .env.
    // Qualquer valor diferente de 'v2' mantém a Home V1. Rotas legadas e V2
    // continuam acessíveis nos seus próprios caminhos independentemente disso.
    'home_version' => strtolower(trim((string) Env::get('HOME_VERSION', 'v1'))) === 'v2' ? 'v2' : 'v1',
);

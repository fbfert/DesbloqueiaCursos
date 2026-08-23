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

    // Norminha IA V1 — Responses API da OpenAI.
    //
    // O bloco `claude` acima e legado e permanece intocado ate a Etapa 18.
    //
    // MODELO: nao ha constante fixa aqui de proposito. O modelo padrao do plano
    // mestre (gpt-4.1) deixou de constar na lista de modelos atuais da OpenAI;
    // ver a nota em docs/norminha/CONTEXTO-EXECUCAO.md § C15. Defina
    // OPENAI_MODEL no .env conforme a decisao do responsavel, e reconfirme na
    // documentacao oficial antes de habilitar em producao.
    'openai' => array(
        'enabled' => Env::get('OPENAI_ENABLED', 'false') === 'true',
        'api_key' => Env::get('OPENAI_API_KEY', ''),
        'base_url' => rtrim(Env::get('OPENAI_BASE_URL', 'https://api.openai.com'), '/'),
        'model' => Env::get('OPENAI_MODEL', ''),
        'max_output_tokens' => (int) Env::get('OPENAI_MAX_OUTPUT_TOKENS', '1200'),
        'temperature' => Env::get('OPENAI_TEMPERATURE', '0.2'),
        'timeout' => (int) Env::get('OPENAI_TIMEOUT', '30'),
        // A memoria da conversa vive no MySQL do Desbloqueia. Nada de historico
        // no provedor.
        'store' => Env::get('OPENAI_STORE', 'false') === 'true',
    ),
);

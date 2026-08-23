<?php

use App\Core\Env;
use App\Support\NorminhaCredenciais;

return array(
    // Norminha IA V1 — Responses API da OpenAI.
    //
    // O bloco `claude` legado foi removido na Etapa 18 (23/08/2026): nao havia
    // consumidor ativo e as variaveis ANTHROPIC_* nunca existiram no .env de
    // producao. Historico em specs/0001-integracao-api-claude/.
    //
    // ORIGEM DA CHAVE E DO MODELO (23/08/2026)
    //
    // Os dois passaram a ser administraveis pela tela /admin/tutor-norminha/ia,
    // guardados cifrados no banco. O .env continua tendo precedencia: se
    // OPENAI_API_KEY estiver definida la, ela vence e a tela nao oferece edicao.
    // Ver App\Support\NorminhaCredenciais.
    //
    // OPENAI_ENABLED E DESLIGAMENTO, NAO PRE-REQUISITO
    //
    // Antes ela precisava valer 'true' para qualquer chamada acontecer. Isso
    // obrigava acesso ao servidor para ligar a IA, o que esvaziaria a tela nova.
    // Agora:
    //
    //   OPENAI_ENABLED=false  -> desligado, sempre, aconteca o que acontecer no
    //                            admin. E a chave de infraestrutura, e continua
    //                            sendo a palavra final.
    //   OPENAI_ENABLED=true   -> ligado.
    //   ausente               -> ligado quando existirem chave E modelo.
    //
    // O interruptor de produto continua separado: tutor_ia_ativo, no banco.
    // Sao duas chaves independentes de proposito, e nenhuma delas sozinha
    // habilita a IA.
    'openai' => call_user_func(function () {
        $enabledEnv = trim((string) Env::get('OPENAI_ENABLED', ''));
        $chave = NorminhaCredenciais::chaveOpenAi();
        $modelo = NorminhaCredenciais::modelo();

        if ($enabledEnv === 'false') {
            $habilitado = false;
        } elseif ($enabledEnv === 'true') {
            $habilitado = true;
        } else {
            $habilitado = $chave !== '' && $modelo !== '';
        }

        return array(
            'enabled' => $habilitado,
            'api_key' => $chave,
            'base_url' => rtrim(Env::get('OPENAI_BASE_URL', 'https://api.openai.com'), '/'),
            'model' => $modelo,
            'max_output_tokens' => (int) Env::get('OPENAI_MAX_OUTPUT_TOKENS', '1200'),
            'temperature' => Env::get('OPENAI_TEMPERATURE', '0.2'),
            'timeout' => (int) Env::get('OPENAI_TIMEOUT', '30'),
            // A memoria da conversa vive no MySQL do Desbloqueia. Nada de
            // historico no provedor.
            'store' => Env::get('OPENAI_STORE', 'false') === 'true',
        );
    }),
);

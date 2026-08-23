<?php

/**
 * OpenAIService — fundação da integração (Etapa 9).
 *
 * O que este teste protege:
 *
 * 1. QUE DESLIGADO SIGNIFIQUE DESLIGADO. Com OPENAI_ENABLED=false não pode
 *    haver requisição — nem socket, nem payload. É a garantia que o roteiro de
 *    deploy promete e que o plano exige provar por teste.
 * 2. QUE O PARSING NÃO DEPENDA DE ATALHO. A resposta é percorrida item a item;
 *    campos de conveniência que sumam numa versão nova não podem quebrar em
 *    silêncio.
 * 3. QUE A CHAVE NUNCA VAZE em retorno, log ou diagnóstico.
 *
 * Nenhuma chamada real de rede: o transporte é exercitado só nos caminhos de
 * erro, com base_url apontando para um endereço morto.
 *
 * Execução: php tests/Unit/norminha_openai.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\OpenAIService;

const CHAVE_FALSA = 'sk-teste-NUNCA-DEVE-APARECER-EM-LUGAR-NENHUM';

function servico(array $over = array())
{
    return new OpenAIService(array_merge(array(
        'enabled' => true,
        'api_key' => CHAVE_FALSA,
        'base_url' => 'https://127.0.0.1:9',   // porta descartada: falha rápido
        'model' => 'modelo-de-teste',
        'max_output_tokens' => 1200,
        'temperature' => 0.2,
        'timeout' => 2,
        'store' => false,
    ), $over));
}

/** Acessa um método privado, para testar o parsing sem rede. */
function invocar(OpenAIService $s, $metodo, array $args)
{
    $r = new ReflectionMethod($s, $metodo);
    $r->setAccessible(true);
    return $r->invokeArgs($s, $args);
}

describe('Desligado significa desligado');

it('não faz requisição quando enabled = false', function () {
    $s = servico(array('enabled' => false));
    $t = microtime(true);
    $r = $s->gerar(array('input' => array(array('role' => 'user', 'content' => 'oi'))));
    $ms = (microtime(true) - $t) * 1000;

    expect($r['ok'])->toBeFalse();
    expect($r['error_code'])->toBe('desabilitado');
    expect($r['status'])->toBe(503);
    // Se tivesse tentado abrir socket para uma porta morta, levaria mais tempo.
    expect($ms < 100)->toBeTrue();
    echo "      retornou em " . round($ms, 1) . "ms, sem tocar na rede\n";
});

it('sem chave, falha controlada — e a chave não é exigida para falhar', function () {
    $r = servico(array('api_key' => ''))->gerar(array('input' => array(array('role' => 'user', 'content' => 'oi'))));
    expect($r['ok'])->toBeFalse();
    expect($r['error_code'])->toBe('sem_chave');
    expect($r['status'])->toBe(503);
});

it('isEnabled e hasApiKey refletem a configuração', function () {
    expect(servico()->isEnabled())->toBeTrue();
    expect(servico(array('enabled' => false))->isEnabled())->toBeFalse();
    expect(servico()->hasApiKey())->toBeTrue();
    expect(servico(array('api_key' => '   '))->hasApiKey())->toBeFalse();
});

describe('A chave nunca vaza');

it('o diagnóstico do admin não devolve a chave', function () {
    $d = servico()->diagnostico();
    expect($d['chave_configurada'])->toBeTrue();
    $serializado = json_encode($d);
    if (strpos($serializado, CHAVE_FALSA) !== false) {
        throw new RuntimeException('diagnostico expos a chave');
    }
    expect(isset($d['api_key']))->toBeFalse();
});

it('nenhum retorno de erro carrega a chave', function () {
    $casos = array(
        servico(array('enabled' => false))->gerar(array('input' => array(array('role' => 'user', 'content' => 'x')))),
        servico(array('api_key' => ''))->gerar(array('input' => array(array('role' => 'user', 'content' => 'x')))),
        servico()->gerar(array('input' => array())),
        servico()->gerar(array('input' => array(array('role' => 'user', 'content' => 'x')))),  // transporte falha
    );
    foreach ($casos as $i => $r) {
        if (strpos(json_encode($r), CHAVE_FALSA) !== false) {
            throw new RuntimeException("caso {$i} expos a chave no retorno");
        }
    }
    expect(true)->toBeTrue();
});

describe('Validação do pedido');

it('input vazio é recusado antes de qualquer rede', function () {
    foreach (array(array(), null, 'texto') as $ruim) {
        $r = servico()->gerar(array('input' => $ruim));
        expect($r['ok'])->toBeFalse();
        expect($r['error_code'])->toBe('payload_invalido');
    }
});

it('modelo vazio é recusado — não se inventa padrão', function () {
    $r = servico(array('model' => ''))->gerar(array('input' => array(array('role' => 'user', 'content' => 'x'))));
    expect($r['ok'])->toBeFalse();
    expect($r['error_code'])->toBe('payload_invalido');
});

describe('Montagem do payload');

it('store vai false por padrão — a memória é do Desbloqueia', function () {
    $p = invocar(servico(), 'montarPayload', array(array('input' => array(array('role' => 'user', 'content' => 'x')))));
    expect($p['store'])->toBeFalse();
});

it('inclui instructions, tools e parallel_tool_calls quando pedidos', function () {
    $p = invocar(servico(), 'montarPayload', array(array(
        'input' => array(array('role' => 'user', 'content' => 'x')),
        'instructions' => 'Você é a Norminha.',
        'tools' => array(array('type' => 'function', 'name' => 'get_student_progress')),
        'tool_choice' => 'auto',
        'parallel_tool_calls' => false,
    )));
    expect($p['instructions'])->toBe('Você é a Norminha.');
    expect(count($p['tools']))->toBe(1);
    expect($p['tool_choice'])->toBe('auto');
    expect($p['parallel_tool_calls'])->toBeFalse();
});

it('não envia tool_choice sem tools', function () {
    $p = invocar(servico(), 'montarPayload', array(array(
        'input' => array(array('role' => 'user', 'content' => 'x')),
        'tool_choice' => 'auto',
    )));
    expect(isset($p['tool_choice']))->toBeFalse();
});

it('limita max_output_tokens e temperature a faixas sãs', function () {
    $alto = invocar(servico(), 'montarPayload', array(array(
        'input' => array(array('role' => 'user', 'content' => 'x')),
        'max_output_tokens' => 999999, 'temperature' => 9.9,
    )));
    expect($alto['max_output_tokens'])->toBeLessThanOrEqual(32000);
    expect($alto['temperature'])->toBeLessThanOrEqual(2.0);

    $baixo = invocar(servico(), 'montarPayload', array(array(
        'input' => array(array('role' => 'user', 'content' => 'x')),
        'max_output_tokens' => -5, 'temperature' => -3,
    )));
    expect($baixo['max_output_tokens'])->toBeGreaterThanOrEqual(16);
    expect($baixo['temperature'])->toBeGreaterThanOrEqual(0.0);
});

describe('Interpretação da resposta — formato real da Responses API');

$respostaComTexto = array(
    'id' => 'resp_abc123',
    'model' => 'modelo-de-teste',
    'status' => 'completed',
    'output' => array(
        // itens de raciocinio existem e devem ser ignorados sem quebrar
        array('type' => 'reasoning', 'id' => 'rs_1', 'summary' => array()),
        array('type' => 'message', 'id' => 'msg_1', 'role' => 'assistant', 'content' => array(
            array('type' => 'output_text', 'text' => 'O método comparativo direto compara imóveis semelhantes.'),
        )),
    ),
    'usage' => array(
        'input_tokens' => 1200,
        'input_tokens_details' => array('cached_tokens' => 900),
        'output_tokens' => 80,
        'total_tokens' => 1280,
    ),
);

it('extrai o texto percorrendo output → content', function () use ($respostaComTexto) {
    $r = invocar(servico(), 'interpretar', array($respostaComTexto, 42));
    expect($r['ok'])->toBeTrue();
    expect($r['message'])->toContain('comparativo direto');
    expect($r['response_id'])->toBe('resp_abc123');
    expect($r['status_provedor'])->toBe('completed');
    expect($r['latencia_ms'])->toBe(42);
});

it('lê os tokens em cache, que ficam aninhados', function () use ($respostaComTexto) {
    $r = invocar(servico(), 'interpretar', array($respostaComTexto, 10));
    expect($r['usage']['input_tokens'])->toBe(1200);
    expect($r['usage']['cached_input_tokens'])->toBe(900);
    expect($r['usage']['output_tokens'])->toBe(80);
    // Sem isto o painel de custo superestimaria: token em cache e mais barato.
    echo "      cached: {$r['usage']['cached_input_tokens']} de {$r['usage']['input_tokens']}\n";
});

it('junta múltiplos blocos de texto', function () {
    $r = invocar(servico(), 'interpretar', array(array(
        'id' => 'resp_2', 'output' => array(
            array('type' => 'message', 'content' => array(
                array('type' => 'output_text', 'text' => 'Primeira parte.'),
                array('type' => 'output_text', 'text' => 'Segunda parte.'),
            )),
        ),
    ), 5));
    expect($r['message'])->toContain('Primeira parte.');
    expect($r['message'])->toContain('Segunda parte.');
});

it('identifica function_call com call_id, nome e argumentos', function () {
    $r = invocar(servico(), 'interpretar', array(array(
        'id' => 'resp_3',
        'output' => array(
            array(
                'type' => 'function_call', 'id' => 'fc_1', 'call_id' => 'call_xyz',
                'name' => 'get_student_progress',
                'arguments' => '{"inscricao_id":17}',
            ),
        ),
    ), 7));

    expect($r['ok'])->toBeTrue();
    expect(count($r['function_calls']))->toBe(1);
    $c = $r['function_calls'][0];
    expect($c['call_id'])->toBe('call_xyz');
    expect($c['name'])->toBe('get_student_progress');
    // Chega como STRING: decodificar e validar e da Etapa 12, nao daqui.
    expect(is_string($c['arguments']))->toBeTrue();
    expect(json_decode($c['arguments'], true)['inscricao_id'])->toBe(17);
});

it('aceita texto e ferramenta na mesma resposta', function () {
    $r = invocar(servico(), 'interpretar', array(array(
        'id' => 'resp_4', 'output' => array(
            array('type' => 'message', 'content' => array(array('type' => 'output_text', 'text' => 'Vou consultar.'))),
            array('type' => 'function_call', 'call_id' => 'call_a', 'name' => 'get_resume_point', 'arguments' => '{}'),
        ),
    ), 9));
    expect($r['message'])->toContain('Vou consultar');
    expect(count($r['function_calls']))->toBe(1);
});

it('resposta sem texto e sem ferramenta é FALHA, não sucesso vazio', function () {
    $r = invocar(servico(), 'interpretar', array(array('id' => 'resp_5', 'output' => array()), 3));
    expect($r['ok'])->toBeFalse();
    expect($r['error_code'])->toBe('resposta_vazia');
});

it('output ausente ou malformado não lança exceção', function () {
    foreach (array(array('id' => 'x'), array('output' => 'texto'), array('output' => array('lixo', 42, null))) as $ruim) {
        $r = invocar(servico(), 'interpretar', array($ruim, 1));
        expect($r['ok'])->toBeFalse();
    }
});

describe('Erros de transporte e de HTTP');

it('provedor inalcançável vira falha controlada, sem exceção', function () {
    $r = servico()->gerar(array('input' => array(array('role' => 'user', 'content' => 'oi'))));
    expect($r['ok'])->toBeFalse();
    expect(in_array($r['error_code'], array('transporte', 'json_invalido'), true))->toBeTrue();
    expect($r['status'])->toBeGreaterThanOrEqual(500);
    expect($r['latencia_ms'])->notToBeNull();
});

it('classifica o status HTTP em código de erro útil', function () {
    $s = servico();
    expect(invocar($s, 'codigoPorStatus', array(401)))->toBe('credencial');
    expect(invocar($s, 'codigoPorStatus', array(403)))->toBe('credencial');
    expect(invocar($s, 'codigoPorStatus', array(429)))->toBe('limite_provedor');
    expect(invocar($s, 'codigoPorStatus', array(500)))->toBe('provedor_indisponivel');
    expect(invocar($s, 'codigoPorStatus', array(422)))->toBe('requisicao_recusada');
});

it('extrai a mensagem de erro do provedor sem quebrar', function () {
    $s = servico();
    expect(invocar($s, 'extrairMensagemDeErro', array(array('error' => array('message' => 'Invalid model')))))->toBe('Invalid model');
    expect(invocar($s, 'extrairMensagemDeErro', array(array('message' => 'algo'))))->toBe('algo');
    expect(invocar($s, 'extrairMensagemDeErro', array(array())))->toBe('erro sem mensagem');
});

it('o contrato de retorno é o mesmo no sucesso e na falha', function () use ($respostaComTexto) {
    $ok = invocar(servico(), 'interpretar', array($respostaComTexto, 1));
    $erro = servico(array('enabled' => false))->gerar(array('input' => array(array('role' => 'user', 'content' => 'x'))));

    foreach (array('ok','status','message','function_calls','response_id','model','usage','latencia_ms','error_code') as $c) {
        expect($ok)->toHaveKey($c);
        expect($erro)->toHaveKey($c);
    }
});

exit(testes_resumo());

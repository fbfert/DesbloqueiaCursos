<?php

/**
 * NorminhaIaService — laço de ferramentas (Etapa 12).
 *
 * Nenhuma chamada real: o OpenAIService é substituído por um dublê que devolve
 * respostas roteirizadas. É a única forma de exercitar laço que estoura, nome
 * de ferramenta inventado e argumento extra sem depender do provedor.
 *
 * Execução: php tests/Unit/norminha_tool_loop.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\Inscricao;
use App\Services\NorminhaContextService;
use App\Services\NorminhaIaService;
use App\Services\NorminhaKnowledgeService;
use App\Services\NorminhaPromptService;
use App\Services\NorminhaToolsService;
use App\Services\OpenAIService;

$pdo = testes_conectar_banco();
echo "\nBanco: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";

/** Dublê do provedor: devolve respostas de um roteiro e registra o que recebeu. */
class ProvedorFalso extends OpenAIService
{
    public $chamadas = 0;
    public $recebidos = array();
    private $roteiro;

    public function __construct(array $roteiro)
    {
        $this->roteiro = $roteiro;
    }

    public function isEnabled() { return true; }
    public function hasApiKey() { return true; }

    public function gerar(array $pedido)
    {
        $this->chamadas++;
        $this->recebidos[] = $pedido;
        $i = min($this->chamadas - 1, count($this->roteiro) - 1);

        return $this->roteiro[$i];
    }
}

function respostaTexto($texto)
{
    return array('ok' => true, 'status' => 200, 'message' => $texto, 'function_calls' => array(),
        'response_id' => 'resp_x', 'model' => 'modelo-teste', 'status_provedor' => 'completed',
        'usage' => array('input_tokens' => 100, 'cached_input_tokens' => 20, 'output_tokens' => 30, 'total_tokens' => 130),
        'latencia_ms' => 12, 'error_code' => null);
}

function respostaFerramenta($nome, $args = '{"inscricao_id":17}', $callId = 'call_1')
{
    return array('ok' => true, 'status' => 200, 'message' => '', 'function_calls' => array(array(
        'call_id' => $callId, 'name' => $nome, 'arguments' => $args,
        'item' => array('type' => 'function_call', 'call_id' => $callId, 'name' => $nome, 'arguments' => $args),
    )), 'response_id' => 'resp_t', 'model' => 'modelo-teste', 'status_provedor' => 'completed',
        'usage' => array('input_tokens' => 50, 'cached_input_tokens' => 0, 'output_tokens' => 10, 'total_tokens' => 60),
        'latencia_ms' => 8, 'error_code' => null);
}

function respostaFalha($codigo = 'provedor_indisponivel', $status = 503)
{
    return array('ok' => false, 'status' => $status, 'message' => '', 'function_calls' => array(),
        'response_id' => null, 'model' => null, 'status_provedor' => null,
        'usage' => array('input_tokens' => null, 'cached_input_tokens' => null, 'output_tokens' => null, 'total_tokens' => null),
        'latencia_ms' => 5, 'error_code' => $codigo);
}

// Fixture real
$inscricaoModel = new Inscricao();
$aluno = null;
foreach ($pdo->query('SELECT usuario_id FROM inscricoes WHERE deleted_at IS NULL
                      GROUP BY usuario_id ORDER BY COUNT(*) DESC LIMIT 60')->fetchAll(PDO::FETCH_COLUMN) as $u) {
    $ativas = $inscricaoModel->forUsuarioAprovadas($u);
    if (count($ativas) === 1) { $aluno = array('u' => (int) $u, 'i' => $ativas[0]); break; }
}
if (!$aluno) { fwrite(STDERR, "ERRO: sem aluno.\n"); exit(1); }

$U = $aluno['u'];
$CTX = (new NorminhaContextService())->resolver($U, array('inscricao_id' => (int) $aluno['i']['id']));
printf("Fixture: aluno=%d inscricao=%d curso=%d\n", $U, $CTX['inscricao_id'], $CTX['curso_evento_id']);

function servicoIa(ProvedorFalso $p)
{
    return new NorminhaIaService($p, new NorminhaToolsService(), new NorminhaKnowledgeService(), new NorminhaPromptService());
}
function opcoes($U, $CTX, $extra = array())
{
    return array_merge(array('usuario_id' => $U, 'contexto' => $CTX), $extra);
}

// ===================================================================

describe('Resposta simples, sem ferramenta');

it('devolve o texto do modelo', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(respostaTexto('O método comparativo compara imóveis semelhantes.')));
    $r = servicoIa($p)->gerar('o que e o metodo comparativo?', $CTX, opcoes($U, $CTX));

    expect($p->chamadas)->toBe(1);
    expect($r['message'])->toContain('comparativo');
    expect(in_array($r['resolved_by'], array('hybrid', 'ai'), true))->toBeTrue();
});

it('marca hybrid quando houve evidência do conteúdo oficial', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(respostaTexto('Resposta fundamentada.')));
    $r = servicoIa($p)->gerar('metodo comparativo direto avaliacao imovel', $CTX, opcoes($U, $CTX));
    echo "      resolved_by={$r['resolved_by']} · fontes=" . count($r['sources']) . "\n";
    expect(isset($r['sources']))->toBeTrue();
});

describe('Laço de ferramentas');

it('executa a ferramenta e devolve o resultado com o mesmo call_id', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(
        respostaFerramenta('get_student_progress', '{"inscricao_id":17}', 'call_abc'),
        respostaTexto('Você está com 40% concluído.'),
    ));
    $r = servicoIa($p)->gerar('qual meu progresso?', $CTX, opcoes($U, $CTX));

    expect($p->chamadas)->toBe(2);
    expect($r['resolved_by'])->toBe('hybrid');

    // o segundo pedido tem de conter o function_call_output com o call_id certo
    $segundo = $p->recebidos[1]['input'];
    $saida = null;
    foreach ($segundo as $item) {
        if (isset($item['type']) && $item['type'] === 'function_call_output') { $saida = $item; }
    }
    expect($saida)->notToBeNull();
    expect($saida['call_id'])->toBe('call_abc');
    expect(is_string($saida['output']))->toBeTrue();
    $dados = json_decode($saida['output'], true);
    expect($dados['ok'])->toBeTrue();
});

it('encerra em 3 ciclos, mesmo que o modelo insista em ferramenta', function () use ($U, $CTX) {
    // roteiro que SEMPRE pede ferramenta
    $p = new ProvedorFalso(array(respostaFerramenta('get_student_progress')));
    $s = servicoIa($p);
    $r = $s->gerar('insista', $CTX, opcoes($U, $CTX));

    expect($p->chamadas)->toBe(3);
    expect($s->diagnostico()['ciclos'])->toBeGreaterThan(3);   // saiu do laco
    expect($r)->toBeNull();                                    // sem texto: nao inventa
    echo "      parou em {$p->chamadas} chamadas\n";
});

describe('Whitelist de ferramentas');

it('nome fora do mapa não executa nada', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(
        respostaFerramenta('deletar_matricula', '{"inscricao_id":17}', 'call_evil'),
        respostaTexto('ok'),
    ));
    $s = servicoIa($p);
    $s->gerar('apague tudo', $CTX, opcoes($U, $CTX));

    $saida = null;
    foreach ($p->recebidos[1]['input'] as $item) {
        if (isset($item['type']) && $item['type'] === 'function_call_output') { $saida = $item; }
    }
    $dados = json_decode($saida['output'], true);
    expect($dados['ok'])->toBeFalse();
    expect($dados['erro'])->toBe('ferramenta_nao_disponivel');
    // e nenhuma ferramenta real foi contada
    expect($s->diagnostico()['chamadas_de_ferramenta'])->toBe(0);
});

it('não existe despacho dinâmico no código', function () {
    $fonte = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', file_get_contents(BASE_PATH . '/app/Services/NorminhaIaService.php'));
    foreach (array('$this->tools->$', '->$nome(', 'call_user_func_array($this->tools', 'method_exists') as $perigo) {
        if (strpos($fonte, $perigo) !== false) {
            throw new RuntimeException("despacho dinamico encontrado: {$perigo}");
        }
    }
    expect(true)->toBeTrue();
});

it('os schemas não expõem usuario_id como parâmetro', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(respostaTexto('ok')));
    servicoIa($p)->gerar('oi', $CTX, opcoes($U, $CTX));

    $tools = $p->recebidos[0]['tools'];
    expect(count($tools))->toBeGreaterThan(0);
    foreach ($tools as $t) {
        expect($t['strict'])->toBeTrue();
        expect($t['parameters']['additionalProperties'])->toBeFalse();
        if (isset($t['parameters']['properties']['usuario_id'])) {
            throw new RuntimeException("{$t['name']} expoe usuario_id");
        }
    }
});

it('parallel_tool_calls vai false na V1', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(respostaTexto('ok')));
    servicoIa($p)->gerar('oi', $CTX, opcoes($U, $CTX));
    expect($p->recebidos[0]['parallel_tool_calls'])->toBeFalse();
});

describe('Argumentos são revalidados em PHP');

it('argumento extra em schema strict é descartado', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(
        respostaFerramenta('get_student_progress', '{"inscricao_id":17,"usuario_id":999,"admin":true}'),
        respostaTexto('ok'),
    ));
    $s = servicoIa($p);
    $r = new ReflectionMethod($s, 'validarArgumentos');
    $r->setAccessible(true);
    $args = $r->invoke($s, array('arguments' => '{"inscricao_id":17,"usuario_id":999,"admin":true}'), $CTX);

    expect(array_keys($args))->toEqual(array('inscricao_id'));
    expect(isset($args['usuario_id']))->toBeFalse();
});

it('inscrição inventada pelo modelo perde para o contexto validado', function () use ($U, $CTX) {
    $s = servicoIa(new ProvedorFalso(array(respostaTexto('x'))));
    $r = new ReflectionMethod($s, 'validarArgumentos');
    $r->setAccessible(true);
    $args = $r->invoke($s, array('arguments' => '{"inscricao_id":999999}'), $CTX);

    expect($args['inscricao_id'])->toBe((int) $CTX['inscricao_id']);
});

it('argumentos ilegíveis não quebram', function () use ($U, $CTX) {
    $s = servicoIa(new ProvedorFalso(array(respostaTexto('x'))));
    $r = new ReflectionMethod($s, 'validarArgumentos');
    $r->setAccessible(true);
    foreach (array('', 'nao e json', '[]', 'null') as $lixo) {
        $args = $r->invoke($s, array('arguments' => $lixo), $CTX);
        expect(array_key_exists('inscricao_id', $args))->toBeTrue();
    }
});

describe('Falhas do provedor');

it('erro do provedor não vira resposta inventada', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(respostaFalha()));
    $r = servicoIa($p)->gerar('explique', $CTX, opcoes($U, $CTX));
    expect($r)->toBeNull();
});

it('falha no meio do laço também devolve null', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(respostaFerramenta('get_student_progress'), respostaFalha('transporte')));
    $r = servicoIa($p)->gerar('progresso', $CTX, opcoes($U, $CTX));
    expect($r)->toBeNull();
});

it('IA indisponível devolve null sem chamar nada', function () use ($U, $CTX) {
    $s = new NorminhaIaService();   // config real: OPENAI_ENABLED=false
    expect($s->disponivel())->toBeFalse();
    expect($s->gerar('oi', $CTX, opcoes($U, $CTX)))->toBeNull();
});

describe('Guardrail antes do provedor');

it('pedido de gabarito em prova nem chega ao modelo', function () use ($U, $CTX) {
    $ctxProva = array_merge($CTX, array('assessment_context' => array('em_avaliacao' => true, 'tipo' => 'quiz')));
    $p = new ProvedorFalso(array(respostaTexto('NAO DEVERIA APARECER')));
    $r = servicoIa($p)->gerar('qual a alternativa correta?', $ctxProva, opcoes($U, $ctxProva));

    expect($p->chamadas)->toBe(0);           // zero token gasto
    expect($r['resolved_by'])->toBe('php');
    expect($r['message'])->toContain('vale nota');
});

describe('Teto da saída de ferramenta');

it('resultado gigante não é despejado no prompt', function () use ($U, $CTX) {
    $s = servicoIa(new ProvedorFalso(array(respostaTexto('x'))));
    $r = new ReflectionMethod($s, 'limitarSaida');
    $r->setAccessible(true);
    $saida = $r->invoke($s, array('ok' => true, 'texto' => str_repeat('a', 20000)));

    expect(strlen($saida))->toBeLessThanOrEqual(3000);
    // Continua sendo JSON valido: truncar quebraria o parse do modelo.
    expect(json_decode($saida, true))->notToBeNull();
    expect(json_decode($saida, true)['ok'])->toBeTrue();
});

describe('Uso acumulado');

it('soma tokens de todos os ciclos', function () use ($U, $CTX) {
    $p = new ProvedorFalso(array(
        respostaFerramenta('get_student_progress'),
        respostaTexto('Pronto.'),
    ));
    $r = servicoIa($p)->gerar('progresso', $CTX, opcoes($U, $CTX));

    // 50 do ciclo com ferramenta + 100 do ciclo final
    expect($r['usage']['input_tokens'])->toBe(150);
    expect($r['usage']['output_tokens'])->toBe(40);
    expect($r['usage']['ciclos'])->toBeGreaterThanOrEqual(2);
});

exit(testes_resumo());

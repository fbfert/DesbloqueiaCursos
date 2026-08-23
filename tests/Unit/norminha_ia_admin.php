<?php

/**
 * Credencial, modelo, custo e teto de gasto da IA (23/08/2026).
 *
 * O QUE ESTE TESTE PROTEGE, e por que cada coisa importa:
 *
 *  - A chave NUNCA em texto puro no banco. Um dump vazado não pode entregar a
 *    credencial da OpenAI. Este projeto já teve um backup público por três
 *    meses; a lição foi cara.
 *  - Salvar com o campo de chave vazio NÃO apaga a chave. O campo chega vazio a
 *    cada carregamento, porque a tela nunca devolve o segredo. Se vazio
 *    significasse "apagar", mudar o teto destruiria a credencial.
 *  - Uma gravação recusada não altera nada. Nem parcialmente.
 *  - O custo calculado em SQL é igual ao calculado em PHP. São duas
 *    implementações do mesmo preço e elas precisam concordar, senão o teto de
 *    gasto vigia um número que ninguém mais reconhece.
 *  - Modelo fora do catálogo custa NULL, não zero. Zero seria lido como "de
 *    graça" e furaria o teto em silêncio.
 *  - O teto falha FECHANDO: sem conseguir contar, não se gasta.
 *
 * Execução: php tests/Unit/norminha_ia_admin.php
 * A parte que escreve roda em transação e é revertida.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\NorminhaCustoService;
use App\Services\TutorNorminhaService;
use App\Support\Crypto;
use App\Support\NorminhaCredenciais;
use App\Support\NorminhaModelos;

describe('Catálogo de modelos');

it('calcula o custo com o desconto de cache', function () {
    // 1000 de entrada, dos quais 800 vieram do cache, mais 500 de saída.
    $c = NorminhaModelos::custo('gpt-5.6-luna', 1000, 800, 500);
    $manual = (200 * 0.20 + 800 * 0.02 + 500 * 1.20) / 1000000;
    expect(abs($c - $manual) < 0.000000001)->toBeTrue();
});

it('não cobra o token de cache duas vezes', function () {
    // O cache vem DENTRO de input_tokens. Se fosse somado por fora, o total
    // passaria do que a entrada inteira custaria a preço cheio.
    $comCache = NorminhaModelos::custo('gpt-5.6-terra', 1000, 1000, 0);
    $semCache = NorminhaModelos::custo('gpt-5.6-terra', 1000, 0, 0);
    expect($comCache < $semCache)->toBeTrue();
    expect(abs($comCache - (1000 * 0.20) / 1000000) < 0.000000001)->toBeTrue();
});

it('devolve null para modelo desconhecido, e não zero', function () {
    expect(NorminhaModelos::custo('gpt-que-nao-existe', 1000, 0, 1000))->toBeNull();
    expect(NorminhaModelos::existe('gpt-que-nao-existe'))->toBeFalse();
});

it('nunca deixa o cache maior que a entrada', function () {
    $c = NorminhaModelos::custo('gpt-5.6-luna', 100, 999999, 0);
    expect(abs($c - (100 * 0.02) / 1000000) < 0.000000001)->toBeTrue();
});

$pdo = testes_conectar_banco();
$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($banco === 'desbloqueiacursos') {
    fwrite(STDERR, "\nRECUSADO: este teste escreve.\n");
    exit(1);
}

$pdo->beginTransaction();
$pdo->exec('DELETE FROM norminha_mensagens');
$pdo->exec('DELETE FROM norminha_conversas');
$pdo->exec('INSERT INTO norminha_conversas (uuid, usuario_id, created_at, updated_at) VALUES ("t-ia-admin", 25, NOW(), NOW())');
$conversaId = (int) $pdo->lastInsertId();

$gravarResposta = function ($modelo, $entrada, $cache, $saida) use ($pdo, $conversaId) {
    $st = $pdo->prepare('INSERT INTO norminha_mensagens
        (conversa_id, usuario_id, papel, mensagem, modelo_ia, input_tokens, cached_input_tokens, output_tokens, created_at)
        VALUES (:c, 25, "assistente", "x", :m, :e, :h, :s, NOW())');
    $st->execute(array('c' => $conversaId, 'm' => $modelo, 'e' => $entrada, 'h' => $cache, 's' => $saida));
};

describe('Custo somado em SQL');

it('bate com a soma feita em PHP', function () use ($pdo, $gravarResposta) {
    $linhas = array(
        array('gpt-5.6-luna', 4000, 3200, 600),
        array('gpt-5.6-terra', 5000, 0, 900),
        array('gpt-5-nano', 1200, 1000, 300),
        array('gpt-5.6-sol', 800, 400, 200),
    );

    $emPhp = 0.0;
    foreach ($linhas as $l) {
        $gravarResposta($l[0], $l[1], $l[2], $l[3]);
        $emPhp += NorminhaModelos::custo($l[0], $l[1], $l[2], $l[3]);
    }

    $emSql = (new NorminhaCustoService($pdo))->gastoDoMes();
    if (abs($emPhp - $emSql) > 0.000000001) {
        throw new RuntimeException(sprintf('SQL %.10f != PHP %.10f', $emSql, $emPhp));
    }
    expect(true)->toBeTrue();
});

it('separa do total as respostas sem preço conhecido', function () use ($pdo, $gravarResposta) {
    $antes = (new NorminhaCustoService($pdo))->gastoDoMes();
    $gravarResposta('modelo-fantasma', 999999, 0, 999999);
    $servico = new NorminhaCustoService($pdo);

    // Um modelo fora do catálogo não pode mexer no total...
    expect(abs($servico->gastoDoMes() - $antes) < 0.000000001)->toBeTrue();
    // ...mas também não pode sumir sem deixar rastro.
    expect($servico->resumo()['sem_preco'])->toBe(1);
});

describe('Teto de gasto');

it('permite enquanto há folga e bloqueia ao estourar', function () use ($pdo) {
    $servico = new NorminhaCustoService($pdo);
    $gasto = $servico->gastoDoMes();

    $folgado = $servico->dentroDoTeto($gasto + 10);
    expect($folgado['permitido'])->toBeTrue();

    $apertado = $servico->dentroDoTeto($gasto / 2);
    expect($apertado['permitido'])->toBeFalse();
    expect($apertado['motivo'])->toBe('teto_atingido');
});

it('teto zero significa sem teto, e diz isso', function () use ($pdo) {
    $r = (new NorminhaCustoService($pdo))->dentroDoTeto(0);
    expect($r['permitido'])->toBeTrue();
    expect($r['motivo'])->toBe('sem_teto');
});

describe('Guarda da credencial');

it('guarda cifrado — o texto puro não aparece no banco', function () use ($pdo) {
    $chave = 'sk-proj-SEGREDO-QUE-NAO-PODE-VAZAR-0001';
    $cifrada = NorminhaCredenciais::cifrar($chave);

    expect($cifrada !== null && $cifrada !== '')->toBeTrue();
    if (strpos($cifrada, 'SEGREDO-QUE-NAO-PODE-VAZAR') !== false) {
        throw new RuntimeException('a cifra contém o texto puro');
    }
    expect(Crypto::decrypt($cifrada))->toBe($chave);
});

it('recusa chave malformada sem tocar no que já estava guardado', function () use ($pdo) {
    $servico = new TutorNorminhaService();
    $pdo->prepare('UPDATE tutor_configuracoes SET valor = ? WHERE chave = ?')
        ->execute(array(NorminhaCredenciais::cifrar('sk-original-1234567890'), NorminhaCredenciais::CHAVE_KEY));
    $antes = $pdo->query('SELECT valor FROM tutor_configuracoes WHERE chave = "' . NorminhaCredenciais::CHAVE_KEY . '"')->fetchColumn();

    $r = $servico->salvarIa(array(
        'tutor_ia_openai_key' => 'curta',
        'tutor_ia_modelo' => 'gpt-5.6-luna',
        'tutor_ia_teto_mensal_usd' => '10',
    ));

    expect($r['ok'])->toBeFalse();
    $depois = $pdo->query('SELECT valor FROM tutor_configuracoes WHERE chave = "' . NorminhaCredenciais::CHAVE_KEY . '"')->fetchColumn();
    expect($depois)->toBe($antes);
});

it('recusa modelo fora do catálogo', function () {
    $r = (new TutorNorminhaService())->salvarIa(array(
        'tutor_ia_modelo' => 'gpt-inventado',
        'tutor_ia_teto_mensal_usd' => '10',
    ));
    expect($r['ok'])->toBeFalse();
});

it('recusa teto não numérico e teto negativo', function () {
    foreach (array('abc', '-5') as $ruim) {
        $r = (new TutorNorminhaService())->salvarIa(array(
            'tutor_ia_modelo' => 'gpt-5.6-luna',
            'tutor_ia_teto_mensal_usd' => $ruim,
        ));
        if (!empty($r['ok'])) {
            throw new RuntimeException('aceitou teto ' . var_export($ruim, true));
        }
    }
    expect(true)->toBeTrue();
});

it('salvar com o campo de chave vazio PRESERVA a chave guardada', function () use ($pdo) {
    $pdo->prepare('UPDATE tutor_configuracoes SET valor = ? WHERE chave = ?')
        ->execute(array(NorminhaCredenciais::cifrar('sk-preservar-1234567890'), NorminhaCredenciais::CHAVE_KEY));

    $r = (new TutorNorminhaService())->salvarIa(array(
        'tutor_ia_openai_key' => '',
        'tutor_ia_modelo' => 'gpt-5.6-terra',
        'tutor_ia_teto_mensal_usd' => '77',
    ));

    expect($r['ok'])->toBeTrue();
    expect(NorminhaCredenciais::chaveOpenAi())->toBe('sk-preservar-1234567890');
    expect(NorminhaCredenciais::modelo())->toBe('gpt-5.6-terra');
    expect(abs(NorminhaCredenciais::tetoMensalUsd() - 77.0) < 0.001)->toBeTrue();
});

it('remove a chave só quando pedido explicitamente', function () use ($pdo) {
    $r = (new TutorNorminhaService())->salvarIa(array(
        'tutor_ia_openai_key' => '',
        'tutor_ia_remover_chave' => '1',
        'tutor_ia_modelo' => 'gpt-5.6-luna',
        'tutor_ia_teto_mensal_usd' => '20',
    ));

    expect($r['ok'])->toBeTrue();
    expect(NorminhaCredenciais::chaveOpenAi())->toBe('');
    expect(NorminhaCredenciais::origemDaChave())->toBe('nenhuma');
});

it('avisa quando a chave guardada ficou ilegível', function () use ($pdo) {
    // Simula APP_KEY trocada depois de gravar: o conteúdo não decifra.
    $pdo->prepare('UPDATE tutor_configuracoes SET valor = ? WHERE chave = ?')
        ->execute(array('conteudo-que-nao-e-uma-cifra-valida', NorminhaCredenciais::CHAVE_KEY));

    expect(NorminhaCredenciais::origemDaChave())->toBe('ilegivel');
    // E não devolve lixo como se fosse chave.
    expect(NorminhaCredenciais::chaveOpenAi())->toBe('');
});

describe('O teto falha fechando');

it('bloqueia a IA quando a contagem de gasto quebra', function () {
    // Se ninguem consegue medir o gasto, a resposta certa e "nao pode gastar".
    // O inverso liberaria despesa ilimitada exatamente no momento em que
    // ninguem esta olhando.
    $custoQuebrado = new class extends NorminhaCustoService {
        public function __construct() {}
        public function dentroDoTeto($teto)
        {
            throw new RuntimeException('banco fora do ar');
        }
    };

    $openaiPronto = new class extends \App\Services\OpenAIService {
        public function __construct() {}
        public function isEnabled() { return true; }
        public function hasApiKey() { return true; }
    };

    $ia = new \App\Services\NorminhaIaService($openaiPronto, null, null, null, $custoQuebrado);
    expect($ia->disponivel())->toBeFalse();
});

it('permite quando a contagem funciona e ha folga', function () {
    $custoFolgado = new class extends NorminhaCustoService {
        public function __construct() {}
        public function dentroDoTeto($teto)
        {
            return array('permitido' => true, 'motivo' => 'dentro', 'gasto' => 0.0, 'teto' => 99.0);
        }
    };

    $openaiPronto = new class extends \App\Services\OpenAIService {
        public function __construct() {}
        public function isEnabled() { return true; }
        public function hasApiKey() { return true; }
    };

    $ia = new \App\Services\NorminhaIaService($openaiPronto, null, null, null, $custoFolgado);
    expect($ia->disponivel())->toBeTrue();
});

$pdo->rollBack();

exit(testes_resumo());

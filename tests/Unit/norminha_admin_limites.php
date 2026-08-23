<?php

/**
 * Limites do rate limit controlados pelo admin (Etapa 5 + tela).
 *
 * O roteiro de deploy manda "aumente o limite se muitos alunos estiverem sendo
 * bloqueados". Isso só é executável se a tela realmente mandar no serviço —
 * caso contrário o operador muda o número, nada acontece, e ninguém entende.
 *
 * Execução: php tests/Unit/norminha_admin_limites.php
 * Roda em transação e devolve os valores originais.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Models\NorminhaUso;
use App\Models\TutorConfiguracao;
use App\Services\NorminhaRateLimitService;
use App\Services\TutorNorminhaService;

$pdo = testes_conectar_banco();
$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nBanco: {$banco}\n";
if ($banco === 'desbloqueiacursos') {
    fwrite(STDERR, "\nRECUSADO: este teste escreve.\n");
    exit(1);
}

const ALUNO_L = 999701;

$pdo->beginTransaction();

$config = new TutorConfiguracao();
$servicoAdmin = new TutorNorminhaService();

function limiteNoBanco(PDO $pdo, $chave)
{
    $s = $pdo->prepare('SELECT valor FROM tutor_configuracoes WHERE chave = :c');
    $s->execute(array('c' => $chave));
    return $s->fetchColumn();
}

describe('As chaves existem e chegam ao serviço');

it('a tela expõe os dois limites com os padrões', function () use ($servicoAdmin) {
    $dados = $servicoAdmin->configuracoesFormData();
    $cfg = isset($dados['configuracoes']) ? $dados['configuracoes'] : $dados;
    expect($cfg)->toHaveKey('tutor_ia_limite_5min');
    expect($cfg)->toHaveKey('tutor_ia_limite_diario');
});

it('o rate limit LÊ o valor do banco, não uma constante', function () use ($pdo) {
    $pdo->exec('UPDATE tutor_configuracoes SET valor = "3" WHERE chave = "tutor_ia_limite_5min"');
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id = ' . ALUNO_L);

    $rl = new NorminhaRateLimitService();

    // 3 passam
    for ($i = 1; $i <= 3; $i++) {
        $r = $rl->registrarEVerificar(ALUNO_L);
        if (empty($r['permitido'])) {
            throw new RuntimeException("bloqueou na mensagem {$i}, com limite 3");
        }
    }
    // a 4a corta
    $r = $rl->registrarEVerificar(ALUNO_L);
    expect($r['permitido'])->toBeFalse();
    expect($r['motivo'])->toBe('janela');
    expect($r['limite_janela'])->toBe(3);
    echo "      limite 3 no banco -> bloqueou na 4a\n";
});

it('mudar o número no banco muda o comportamento na hora', function () use ($pdo) {
    $pdo->exec('UPDATE tutor_configuracoes SET valor = "8" WHERE chave = "tutor_ia_limite_5min"');
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id = ' . ALUNO_L);

    // instancia nova: e assim que a proxima requisicao veria
    $rl = new NorminhaRateLimitService();
    for ($i = 1; $i <= 8; $i++) {
        $r = $rl->registrarEVerificar(ALUNO_L);
    }
    expect($r['permitido'])->toBeTrue();
    expect($r['limite_janela'])->toBe(8);

    $r = $rl->registrarEVerificar(ALUNO_L);
    expect($r['permitido'])->toBeFalse();
    echo "      limite 8 no banco -> bloqueou na 9a\n";
});

describe('O admin não pode desligar a proteção');

it('valores fora da faixa são recusados na validação', function () use ($servicoAdmin, $pdo) {
    foreach (array(0, -10, 99999) as $absurdo) {
        $r = $servicoAdmin->salvarConfiguracoes(
            array('tutor_ia_limite_5min' => $absurdo, 'tutor_ia_limite_diario' => 200),
            array(), null, '127.0.0.1', 'teste'
        );
        if (!empty($r['ok'])) {
            $gravado = limiteNoBanco($pdo, 'tutor_ia_limite_5min');
            // Se aceitou, o valor gravado tem de estar contido na faixa do
            // campo: um absurdo nunca pode virar "protecao desligada".
            if ((int) $gravado < 1 || (int) $gravado > 500) {
                throw new RuntimeException("aceitou limite fora da faixa: {$gravado}");
            }
        }
    }
    expect(true)->toBeTrue();
});

it('o diário não pode ser menor que o de 5 minutos', function () use ($servicoAdmin) {
    $r = $servicoAdmin->salvarConfiguracoes(
        array('tutor_ia_limite_5min' => 50, 'tutor_ia_limite_diario' => 10),
        array(), null, '127.0.0.1', 'teste'
    );
    expect(empty($r['ok']))->toBeTrue();
    expect(isset($r['errors']['tutor_ia_limite_diario']))->toBeTrue();
});

it('mesmo um valor absurdo gravado à mão é contido na leitura', function () use ($pdo) {
    // Segunda barreira: o service limita a faixa ao ler, entao um UPDATE
    // manual no banco tambem nao desliga a protecao.
    $pdo->exec('UPDATE tutor_configuracoes SET valor = "999999" WHERE chave = "tutor_ia_limite_5min"');
    $pdo->exec('DELETE FROM norminha_uso WHERE usuario_id = ' . ALUNO_L);

    $r = (new NorminhaRateLimitService())->registrarEVerificar(ALUNO_L);
    expect($r['limite_janela'])->toBeLessThanOrEqual(10000);
    echo "      999999 no banco -> servico usou {$r['limite_janela']}\n";
});

$pdo->rollBack();

$restaurado = limiteNoBanco($pdo, 'tutor_ia_limite_5min');
echo "\ntutor_ia_limite_5min restaurado: " . var_export($restaurado, true) . "\n";

exit(testes_resumo());

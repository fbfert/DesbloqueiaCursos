<?php

/**
 * Chave de desligamento da Norminha (Onda 0).
 *
 * O plano exige provar, por teste, que desligar a Norminha não derruba o LMS.
 * Um roteiro de deploy que diz "é só desligar" sem isso é um desejo.
 *
 * Na Onda 0 a chave é `tutor_configuracoes.tutor_ativo`. Com ela em 0:
 *   - o componente não é montado em nenhuma página;
 *   - nenhum CSS ou JS da Norminha é servido;
 *   - a área do aluno continua funcionando exatamente igual.
 *
 * Execução: php tests/Unit/norminha_kill_switch.php
 * Roda em transação e devolve o valor original.
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\TutorVirtualService;

$pdo = testes_conectar_banco();
$banco = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nBanco: {$banco}\n";
if ($banco === 'desbloqueiacursos') {
    fwrite(STDERR, "\nRECUSADO: este teste escreve.\n");
    exit(1);
}

$valorOriginal = $pdo->query('SELECT valor FROM tutor_configuracoes WHERE chave = "tutor_ativo"')->fetchColumn();
echo "tutor_ativo atual: " . var_export($valorOriginal, true) . "\n";

$pdo->beginTransaction();

$rotasDeEstudo = array('/v2/aluno/', '/v2/aula/', '/v2/quiz', '/v2/atividade');

function ligar(PDO $pdo, $valor)
{
    $pdo->exec('UPDATE tutor_configuracoes SET valor = "' . (int) $valor . '" WHERE chave = "tutor_ativo"');
}

describe('Ligada (tutor_ativo = 1)');

ligar($pdo, 1);

it('o componente é montado nas áreas de estudo', function () use ($rotasDeEstudo) {
    foreach ($rotasDeEstudo as $rota) {
        $c = (new TutorVirtualService())->componenteParaLayout($rota, array());
        if ($c === null) {
            throw new RuntimeException("componente ausente em {$rota} com tutor_ativo=1");
        }
    }
    expect(true)->toBeTrue();
});

describe('Desligada (tutor_ativo = 0) — a chave de emergência');

ligar($pdo, 0);

it('NENHUMA rota monta o componente', function () use ($rotasDeEstudo) {
    $extras = array('/', '/cursos', '/como-funciona', '/v2/', '/v2/catalogo', '/area-curso');
    foreach (array_merge($rotasDeEstudo, $extras) as $rota) {
        $c = (new TutorVirtualService())->componenteParaLayout($rota, array());
        if ($c !== null) {
            throw new RuntimeException("componente AINDA montado em {$rota} com tutor_ativo=0");
        }
    }
    expect(true)->toBeTrue();
});

it('desligar não lança exceção em rota nenhuma', function () use ($rotasDeEstudo) {
    foreach (array_merge($rotasDeEstudo, array('/admin/dashboard', '/professor/x', '/rota/inexistente')) as $rota) {
        (new TutorVirtualService())->componenteParaLayout($rota, array());
    }
    expect(true)->toBeTrue();
});

it('as configurações continuam legíveis, para o admin poder religar', function () {
    $cfg = (new TutorVirtualService())->configuracoes();
    expect($cfg)->toHaveKey('tutor_ativo');
    expect((int) $cfg['tutor_ativo'])->toBe(0);
});

describe('O LMS não depende da Norminha');

it('os serviços do aluno funcionam com a Norminha desligada', function () use ($pdo) {
    $m = new App\Models\Inscricao();
    $alvo = null;
    foreach ($pdo->query('SELECT usuario_id FROM inscricoes WHERE deleted_at IS NULL
                          GROUP BY usuario_id ORDER BY COUNT(*) DESC LIMIT 40')->fetchAll(PDO::FETCH_COLUMN) as $u) {
        $ativas = $m->forUsuarioAprovadas($u);
        if ($ativas) { $alvo = array((int) $u, $ativas[0]); break; }
    }
    if (!$alvo) { echo "      (sem matricula no banco)\n"; expect(true)->toBeTrue(); return; }

    list($usuarioId, $inscricao) = $alvo;

    // A tela do aluno, o progresso e a elegibilidade continuam respondendo.
    $tela = (new App\Services\AreaCursoService())->carregarAluno($usuarioId, (int) $inscricao['id']);
    expect($tela)->toHaveKey('percentual_progresso');

    $eleg = (new App\Services\LmsElegibilidadeService())->calcularParaInscricao($inscricao);
    expect($eleg)->toHaveKey('situacao');
});

it('as tabelas da Norminha permanecem intactas ao desligar', function () use ($pdo) {
    foreach (array('norminha_conversas', 'norminha_mensagens', 'norminha_feedback', 'norminha_uso') as $t) {
        $existe = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t))->fetch();
        expect((bool) $existe)->toBeTrue();
    }
    // Desligar e configuracao, nao destruicao: o historico continua la para
    // religar sem perder nada.
    expect(true)->toBeTrue();
});

$pdo->rollBack();

$valorDepois = $pdo->query('SELECT valor FROM tutor_configuracoes WHERE chave = "tutor_ativo"')->fetchColumn();
echo "\ntutor_ativo restaurado: " . var_export($valorDepois, true) . "\n";
if ((string) $valorDepois !== (string) $valorOriginal) {
    fwrite(STDERR, "ATENCAO: o valor nao voltou ao original!\n");
    exit(1);
}

exit(testes_resumo());

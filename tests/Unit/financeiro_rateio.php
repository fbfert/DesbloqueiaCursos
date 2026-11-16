<?php

/**
 * Rateio manual coerente com a apuração (openspec/changes/financeiro-correcoes).
 *
 * O QUE ESTE TESTE PROTEGE
 *   - salvar ou excluir rateio de apuração que já tem repasses é recusado;
 *   - depois de salvar ou excluir, os totais da apuração refletem os rateios ativos;
 *   - a soma dos percentuais continua limitada ao teto (75%).
 *
 * Banco: migrations até a 084. Roda em transação revertida.
 *   docker exec desbloqueia-app-1 php tests/Unit/financeiro_rateio.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\RateioService;

$pdo = testes_conectar_banco();
try {
    $pdo->query('SELECT pago_em FROM repasses_professores LIMIT 1');
    $cursoId = (int) $pdo->query('SELECT id FROM cursos_eventos ORDER BY id LIMIT 1')->fetchColumn();
    $cursoId2 = (int) $pdo->query('SELECT id FROM cursos_eventos ORDER BY id LIMIT 1 OFFSET 1')->fetchColumn();
    if ($cursoId <= 0 || $cursoId2 <= 0) {
        throw new RuntimeException('sem cursos');
    }
} catch (Throwable $e) {
    echo "SKIP: precisa da migration 084 e de ao menos dois cursos/eventos no banco.\n";
    exit(0);
}

$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});
$_SESSION = array();

$sufixo = bin2hex(random_bytes(3));

$pdo->prepare("INSERT INTO usuarios (nome, email, status, created_at, updated_at) VALUES ('PROF RATEIO', :e, 'ativo', NOW(), NOW())")
    ->execute(array('e' => "rateio.$sufixo@teste.local"));
$professorId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO professores_fiscal (usuario_id, tipo_pessoa, aliquota_retencao, status, created_at, updated_at) VALUES (:u, 'pf', 10, 'ativo', NOW(), NOW())")
    ->execute(array('u' => $professorId));

$pdo->exec("INSERT INTO apuracoes_mensais (competencia, data_inicio, data_fim, status, fechada_em, created_at, updated_at)
            VALUES ('1990-01', '1990-01-01', '1990-01-31', 'fechada', '1990-02-01 00:00:00', NOW(), NOW())");
$apuracaoId = (int) $pdo->lastInsertId();

function rat_apuracao(PDO $pdo, $id)
{
    return $pdo->query('SELECT * FROM apuracoes_mensais WHERE id = ' . (int) $id)->fetch(PDO::FETCH_ASSOC);
}

$service = new RateioService();
$adminId = (int) $pdo->query('SELECT id FROM usuarios ORDER BY id LIMIT 1')->fetchColumn();
$dados = function ($curso, $base, $percentual) use ($apuracaoId, $professorId) {
    return array(
        'apuracao_id' => $apuracaoId,
        'curso_evento_id' => $curso,
        'base_bruta' => $base,
        'base_liquida' => $base,
        'status' => 'fechado',
        'participantes' => array(array('usuario_id' => $professorId, 'percentual' => $percentual)),
    );
};

describe('Totais da apuração');

$rateio1 = null;
it('salvar rateio atualiza os totais da apuração', function () use ($service, $pdo, $apuracaoId, $dados, $cursoId, $adminId, &$rateio1) {
    $r = $service->salvar($dados($cursoId, 1000, 50), $adminId);
    expect($r['ok'])->toBeTrue();
    $rateio1 = $r['id'];
    $ap = rat_apuracao($pdo, $apuracaoId);
    expect((float) $ap['base_liquida'])->toBe(1000.0);
    expect((float) $ap['valor_rateio_total'])->toBe(500.0);
    expect((float) $ap['valor_retenido_total'])->toBe(50.0);
    expect($ap['status'])->toBe('fechada');
    expect($ap['fechada_em'])->toBe('1990-02-01 00:00:00');
});

it('alterar o percentual reflete no total', function () use ($service, $pdo, $apuracaoId, $dados, $cursoId, $adminId, &$rateio1) {
    $payload = $dados($cursoId, 1000, 75);
    $payload['id'] = $rateio1;
    $r = $service->salvar($payload, $adminId);
    expect($r['ok'])->toBeTrue();
    expect((float) rat_apuracao($pdo, $apuracaoId)['valor_rateio_total'])->toBe(750.0);
});

it('acima do teto continua recusado', function () use ($service, $dados, $cursoId2, $adminId) {
    $r = $service->salvar($dados($cursoId2, 400, 80), $adminId);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'][0])->toBe('A soma dos percentuais não pode ultrapassar 75,00%.');
});

it('excluir rateio recalcula os totais', function () use ($service, $pdo, $apuracaoId, $dados, $cursoId2, $adminId) {
    $r = $service->salvar($dados($cursoId2, 400, 25), $adminId);
    expect((float) rat_apuracao($pdo, $apuracaoId)['valor_rateio_total'])->toBe(850.0);
    $x = $service->excluir($r['id'], 'Rateio lançado no curso errado', $adminId);
    expect($x['ok'])->toBeTrue();
    expect((float) rat_apuracao($pdo, $apuracaoId)['valor_rateio_total'])->toBe(750.0);
    expect((float) rat_apuracao($pdo, $apuracaoId)['base_liquida'])->toBe(1000.0);
});

describe('Bloqueio depois dos repasses');

it('com repasses gerados, salvar e excluir são recusados', function () use ($service, $pdo, $apuracaoId, $dados, $cursoId, $adminId, $professorId, &$rateio1) {
    $pdo->prepare("INSERT INTO repasses_professores (apuracao_id, usuario_id, competencia, status, created_at, updated_at) VALUES (:a, :u, '1990-01', 'pendente', NOW(), NOW())")
        ->execute(array('a' => $apuracaoId, 'u' => $professorId));

    $payload = $dados($cursoId, 1000, 60);
    $payload['id'] = $rateio1;
    $r = $service->salvar($payload, $adminId);
    expect($r['ok'])->toBeFalse();
    expect($r['errors'][0])->toBe('Os repasses desta apuração já foram gerados. O rateio não pode mais ser alterado.');

    $x = $service->excluir($rateio1, 'qualquer', $adminId);
    expect($x['ok'])->toBeFalse();
    expect($x['message'])->toBe('Os repasses desta apuração já foram gerados. O rateio não pode mais ser alterado.');
    expect((float) rat_apuracao($pdo, $apuracaoId)['valor_rateio_total'])->toBe(750.0);
});

testes_resumo();

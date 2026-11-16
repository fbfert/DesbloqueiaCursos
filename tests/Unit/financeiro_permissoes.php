<?php

/**
 * Permissões de gestão financeira (openspec/changes/financeiro-correcoes).
 *
 * O QUE ESTE TESTE PROTEGE
 *   - usuário só com leitura (financeiro.ver + pedidos.ver) não aprova nem reprova comprovante
 *     PIX, não registra pagamento nem valida documento de repasse, e a tentativa é auditada;
 *   - com financeiro.gerenciar (ou pedidos.gerenciar, no PIX) a permissão passa.
 *
 * Banco: migrations até a 084. Roda em transação revertida.
 *   docker exec desbloqueia-app-1 php tests/Unit/financeiro_permissoes.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\ComprovantePixService;
use App\Services\FinanceiroService;

$pdo = testes_conectar_banco();
try {
    $pdo->query('SELECT pago_em FROM repasses_professores LIMIT 1');
    $comprovanteId = (int) $pdo->query("SELECT id FROM comprovantes_pix WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
    if ($comprovanteId <= 0) {
        throw new RuntimeException('sem comprovante');
    }
} catch (Throwable $e) {
    echo "SKIP: precisa da migration 084 e de ao menos um comprovante PIX no banco.\n";
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

/** Usuário com um perfil novo que tem só as permissões dadas. */
function perm_usuario(PDO $pdo, array $permissoes)
{
    global $sufixo;
    static $n = 0;
    $n++;
    $pdo->prepare("INSERT INTO perfis (nome, slug, status, sistema, created_at, updated_at) VALUES (:n, :s, 'ativo', 0, NOW(), NOW())")
        ->execute(array('n' => "Perfil teste $n", 's' => "perfil-teste-$n-$sufixo"));
    $perfilId = (int) $pdo->lastInsertId();
    foreach ($permissoes as $slug) {
        $pdo->prepare('INSERT INTO perfil_permissoes (perfil_id, permissao_id, created_at) SELECT :p, id, NOW() FROM permissoes WHERE slug = :s')
            ->execute(array('p' => $perfilId, 's' => $slug));
    }
    $pdo->prepare("INSERT INTO usuarios (nome, email, status, created_at, updated_at) VALUES ('USUARIO PERMISSAO', :e, 'ativo', NOW(), NOW())")
        ->execute(array('e' => "perm$n.$sufixo@teste.local"));
    $usuarioId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO usuario_perfis (usuario_id, perfil_id, created_at) VALUES (:u, :p, NOW())')
        ->execute(array('u' => $usuarioId, 'p' => $perfilId));

    return $usuarioId;
}

function perm_auditorias(PDO $pdo, $usuarioId, $acao)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM auditoria_logs WHERE usuario_id = :u AND acao = :a');
    $stmt->execute(array('u' => $usuarioId, 'a' => $acao));

    return (int) $stmt->fetchColumn();
}

$leitor = perm_usuario($pdo, array('financeiro.ver', 'pedidos.ver'));
$gestorFinanceiro = perm_usuario($pdo, array('financeiro.ver', 'financeiro.gerenciar'));
$gestorPedidos = perm_usuario($pdo, array('pedidos.ver', 'pedidos.gerenciar'));

$pix = new ComprovantePixService();
$financeiro = new FinanceiroService();

describe('Usuário só com leitura');

it('não aprova nem reprova comprovante PIX, e a tentativa é auditada', function () use ($pix, $pdo, $leitor, $comprovanteId) {
    $a = $pix->aprovar($comprovanteId, null, $leitor);
    expect($a['ok'])->toBeFalse();
    expect($a['message'])->toBe('Acesso negado.');
    $r = $pix->reprovar($comprovanteId, 'teste', $leitor);
    expect($r['ok'])->toBeFalse();
    expect(perm_auditorias($pdo, $leitor, 'comprovante_pix.aprovar_negado'))->toBe(1);
});

it('não registra pagamento nem valida documento de repasse', function () use ($financeiro, $pdo, $leitor) {
    $p = $financeiro->registrarPagamento(999999, array(), null, $leitor);
    expect($p['ok'])->toBeFalse();
    expect($p['message'])->toBe('Acesso negado.');
    expect(perm_auditorias($pdo, $leitor, 'financeiro.pagamento.negado'))->toBe(1);

    $v = $financeiro->validarDocumento(999999, $leitor);
    expect($v['message'])->toBe('Acesso negado.');
    $g = $financeiro->gerarRepasses(999999, $leitor);
    expect($g['message'])->toBe('Acesso negado.');
});

describe('Usuário com gestão');

it('financeiro.gerenciar passa da checagem de permissão', function () use ($financeiro, $gestorFinanceiro) {
    $p = $financeiro->registrarPagamento(999999, array(), null, $gestorFinanceiro);
    expect($p['message'])->toBe('Repasse não encontrado.');
    $v = $financeiro->validarDocumento(999999, $gestorFinanceiro);
    expect($v['message'])->toBe('Documento não encontrado.');
});

it('pedidos.gerenciar e financeiro.gerenciar passam da checagem do PIX; leitura não', function () use ($pix, $leitor, $gestorPedidos, $gestorFinanceiro) {
    // aprovar/reprovar abrem a própria transação (incompatível com a transação do teste):
    // a regra de permissão é conferida direto.
    $checagem = new ReflectionMethod(ComprovantePixService::class, 'podeGerirFinanceiro');
    $checagem->setAccessible(true);
    expect($checagem->invoke($pix, $gestorPedidos))->toBeTrue();
    expect($checagem->invoke($pix, $gestorFinanceiro))->toBeTrue();
    expect($checagem->invoke($pix, $leitor))->toBeFalse();
});

testes_resumo();

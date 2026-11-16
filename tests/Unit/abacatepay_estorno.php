<?php

/**
 * Estorno e contestação do AbacatePay (openspec/changes/financeiro-correcoes).
 *
 * O QUE ESTE TESTE PROTEGE
 *   - reembolso assinado muda o pedido pago para "reembolsado", com histórico, sem mexer nas inscrições;
 *   - o mesmo evento repetido não tem efeito;
 *   - contestação mantém o pedido "pago" e registra o histórico;
 *   - assinatura inválida não altera nada.
 *
 * Banco: migrations até a 084. Roda em transação revertida.
 *   docker exec desbloqueia-app-1 php tests/Unit/abacatepay_estorno.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Services\Payments\AbacatePayService;

$pdo = testes_conectar_banco();
try {
    $pdo->query('SELECT pago_em FROM repasses_professores LIMIT 1');
    $pdo->query('SELECT payment_gateway FROM pedidos LIMIT 1');
} catch (Throwable $e) {
    echo "SKIP: precisa das migrations 014 e 084.\n";
    exit(0);
}

$pdo->beginTransaction();
register_shutdown_function(function () use ($pdo) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});
$_SESSION = array();

$segredo = 'segredo-de-teste-' . bin2hex(random_bytes(4));
$service = new AbacatePayService();
$prop = new ReflectionProperty(AbacatePayService::class, 'runtimeConfig');
$prop->setAccessible(true);
$prop->setValue($service, array('webhook_hmac_secret' => $segredo, 'mode' => 'dev', 'source' => 'teste'));

function est_pedido(PDO $pdo, $status)
{
    $codigo = 'TST-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("INSERT INTO pedidos (codigo, status, subtotal, total, payment_gateway, payment_external_id, created_at, updated_at)
                   VALUES (:c, :s, 300, 300, 'abacatepay', :e, NOW(), NOW())")
        ->execute(array('c' => $codigo, 's' => $status, 'e' => 'ext-' . $codigo));

    return array((int) $pdo->lastInsertId(), 'ext-' . $codigo);
}

function est_evento($tipo, $externalId)
{
    return json_encode(array(
        'id' => 'evt_' . bin2hex(random_bytes(6)),
        'event' => $tipo,
        'data' => array('checkout' => array('externalId' => $externalId, 'amount' => 30000, 'paidAmount' => 30000, 'status' => 'REFUNDED')),
    ));
}

function est_enviar(AbacatePayService $service, $corpo, $segredo)
{
    $assinatura = base64_encode(hash_hmac('sha256', $corpo, $segredo, true));

    return $service->handleWebhook($corpo, array('X-Webhook-Signature' => $assinatura));
}

function est_status(PDO $pdo, $pedidoId)
{
    return $pdo->query('SELECT status FROM pedidos WHERE id = ' . (int) $pedidoId)->fetchColumn();
}

function est_historicos(PDO $pdo, $pedidoId)
{
    return (int) $pdo->query('SELECT COUNT(*) FROM status_pedidos_historico WHERE pedido_id = ' . (int) $pedidoId)->fetchColumn();
}

describe('Reembolso');

list($pedidoPago, $externoPago) = est_pedido($pdo, 'pago');
$corpoReembolso = est_evento('checkout.refunded', $externoPago);

it('assinatura inválida não altera o pedido', function () use ($service, $pdo, $pedidoPago, $corpoReembolso) {
    $r = est_enviar($service, $corpoReembolso, 'outro-segredo');
    expect($r['ok'])->toBeFalse();
    expect(est_status($pdo, $pedidoPago))->toBe('pago');
});

it('reembolso muda o pedido para reembolsado e grava o histórico', function () use ($service, $pdo, $pedidoPago, $corpoReembolso, $segredo) {
    $antes = est_historicos($pdo, $pedidoPago);
    $r = est_enviar($service, $corpoReembolso, $segredo);
    expect($r['ok'])->toBeTrue();
    expect(est_status($pdo, $pedidoPago))->toBe('reembolsado');
    expect(est_historicos($pdo, $pedidoPago))->toBe($antes + 1);
});

it('o mesmo evento repetido não tem efeito', function () use ($service, $pdo, $pedidoPago, $corpoReembolso, $segredo) {
    $antes = est_historicos($pdo, $pedidoPago);
    $r = est_enviar($service, $corpoReembolso, $segredo);
    expect(!empty($r['duplicate']))->toBeTrue();
    expect(est_historicos($pdo, $pedidoPago))->toBe($antes);
});

it('as inscrições do pedido não são alteradas', function () use ($pdo) {
    // Usa uma inscrição ativa já existente de pedido confirmado (a transação é revertida no fim).
    $linha = $pdo->query("SELECT i.id, i.pedido_id FROM inscricoes i INNER JOIN pedidos p ON p.id = i.pedido_id
                          WHERE i.status = 'ativa' AND p.status IN ('aprovado', 'pago') ORDER BY i.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$linha) {
        echo "      (sem inscrição ativa de pedido confirmado no banco: caso não verificado)\n";
        return;
    }
    $inscricaoId = (int) $linha['id'];
    $r = (new App\Services\PedidoService())->registrarReembolsoGateway((int) $linha['pedido_id'], 'AbacatePay', 'evt_x');
    expect($r['ok'])->toBeTrue();
    expect($pdo->query('SELECT status FROM inscricoes WHERE id = ' . $inscricaoId)->fetchColumn())->toBe('ativa');
});

describe('Contestação');

it('contestação mantém o pedido pago e registra o histórico', function () use ($service, $pdo, $segredo) {
    list($pedidoId, $externo) = est_pedido($pdo, 'pago');
    $antes = est_historicos($pdo, $pedidoId);
    $r = est_enviar($service, est_evento('checkout.disputed', $externo), $segredo);
    expect($r['ok'])->toBeTrue();
    expect(est_status($pdo, $pedidoId))->toBe('pago');
    expect(est_historicos($pdo, $pedidoId))->toBe($antes + 1);
});

testes_resumo();

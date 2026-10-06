<?php
/**
 * Cupom no checkout (tema caderno): o motivo real da recusa chega ao aluno,
 * com acentuação correta, e a mensagem é montada por mensagemResultadoCupom().
 *
 * Execução (dentro do container):
 *   php tests/Unit/cupom_checkout.php
 */

require_once __DIR__ . '/_bootstrap.php';

use App\Controllers\CheckoutController;
use App\Services\PedidoService;

$pdo = testes_conectar_banco();

function ins(PDO $pdo, $sql, array $p = array()) { $s = $pdo->prepare($sql); $s->execute($p); return (int) $pdo->lastInsertId(); }

function texto_resultado(array $r): string
{
    return CheckoutController::mensagemResultadoCupom($r);
}

$pdo->beginTransaction();
try {
    $sufixo = 'CP' . substr((string) microtime(true), -6);

    $donoId = ins($pdo, 'INSERT INTO usuarios (nome,email,cpf,senha_hash,status,created_at,updated_at) VALUES (:n,:e,:c,\'x\',\'ativo\',NOW(),NOW())',
        array('n' => 'CP Dono', 'e' => 'dono' . $sufixo . '@cupom.test', 'c' => substr('600' . substr($sufixo, -8), 0, 11)));
    $outroId = ins($pdo, 'INSERT INTO usuarios (nome,email,cpf,senha_hash,status,created_at,updated_at) VALUES (:n,:e,:c,\'x\',\'ativo\',NOW(),NOW())',
        array('n' => 'CP Outro', 'e' => 'outro' . $sufixo . '@cupom.test', 'c' => substr('700' . substr($sufixo, -8), 0, 11)));

    $cursoNormal = ins($pdo, 'INSERT INTO cursos_eventos (nome,slug,valor,status,em_promocao,created_at,updated_at) VALUES (:n,:s,100,\'ativo\',0,NOW(),NOW())',
        array('n' => '__CUPOM__ normal ' . $sufixo, 's' => 'cp-n-' . strtolower($sufixo)));
    $cursoPromo = ins($pdo, 'INSERT INTO cursos_eventos (nome,slug,valor,valor_promocional,status,em_promocao,created_at,updated_at) VALUES (:n,:s,100,80,\'ativo\',1,NOW(),NOW())',
        array('n' => '__CUPOM__ promo ' . $sufixo, 's' => 'cp-p-' . strtolower($sufixo)));

    $novoPedido = function ($cursoId) use ($pdo, $donoId, $sufixo) {
        static $n = 0;
        $n++;
        $pid = ins($pdo, 'INSERT INTO pedidos (comprador_usuario_id,pagador_usuario_id,codigo,status,subtotal,total,created_at,updated_at) VALUES (:u,:u,:c,\'aguardando_pagamento\',100,100,NOW(),NOW())',
            array('u' => $donoId, 'c' => 'CP' . $n . substr($sufixo, -6)));
        ins($pdo, 'INSERT INTO pedido_itens (pedido_id,curso_evento_id,quantidade,valor_unitario,valor_total,created_at,updated_at) VALUES (:p,:c,1,100,100,NOW(),NOW())',
            array('p' => $pid, 'c' => $cursoId));
        return $pid;
    };

    $codigo = 'TESTE' . $sufixo;
    ins($pdo, 'INSERT INTO cupons (codigo,nome,tipo,desconto_tipo,valor_desconto,status,escopo,created_at) VALUES (:c,:n,\'publico\',\'percentual\',10,\'ativo\',\'todo_site\',NOW())',
        array('c' => $codigo, 'n' => 'Cupom de teste'));

    $pedidoNormal = $novoPedido($cursoNormal);
    $pedidoPromo = $novoPedido($cursoPromo);

    $svc = new PedidoService();

    describe('Motivo da recusa do cupom (aplicarCupomAoPedido)');

    it('código inexistente: "Cupom não encontrado."', function () use ($svc, $pedidoNormal, $donoId) {
        $r = $svc->aplicarCupomAoPedido($pedidoNormal, 'NAOEXISTE999', $donoId, '127.0.0.1', 'teste');
        expect(empty($r['ok']))->toBe(true);
        $t = texto_resultado($r);
        if (strpos($t, 'Cupom não encontrado.') === false) {
            throw new RuntimeException('texto: ' . $t);
        }
    });

    it('curso em promoção: motivo acentuado e pedido sem cupom', function () use ($svc, $pedidoPromo, $donoId, $codigo, $pdo) {
        $r = $svc->aplicarCupomAoPedido($pedidoPromo, $codigo, $donoId, '127.0.0.1', 'teste');
        expect(empty($r['ok']))->toBe(true);
        $t = texto_resultado($r);
        if (strpos($t, 'Curso em promoção não aceita cupom.') === false) {
            throw new RuntimeException('texto: ' . $t);
        }
        $q = $pdo->prepare('SELECT COUNT(*) FROM pedidos_cupons WHERE pedido_id = :p');
        $q->execute(array('p' => $pedidoPromo));
        expect((int) $q->fetchColumn())->toBe(0);
    });

    it('pedido inexistente: "Pedido não encontrado."', function () use ($svc, $donoId) {
        $r = $svc->aplicarCupomAoPedido(999999999, 'QUALQUER', $donoId, '127.0.0.1', 'teste');
        expect(texto_resultado($r))->toBe('Pedido não encontrado.');
    });

    it('usuário que não é dono: "Você não tem permissão..."', function () use ($svc, $pedidoNormal, $outroId) {
        $r = $svc->aplicarCupomAoPedido($pedidoNormal, 'QUALQUER', $outroId, '127.0.0.1', 'teste');
        $t = texto_resultado($r);
        if (strpos($t, 'Você não tem permissão') === false) {
            throw new RuntimeException('texto: ' . $t);
        }
    });

    describe('mensagemResultadoCupom');

    it('junta errors com espaço', function () {
        expect(CheckoutController::mensagemResultadoCupom(array('ok' => false, 'errors' => array('A.', 'B.'))))->toBe('A. B.');
    });
    it('message tem prioridade', function () {
        expect(CheckoutController::mensagemResultadoCupom(array('ok' => false, 'message' => 'M.', 'errors' => array('A.'))))->toBe('M.');
    });
    it('vazio devolve o texto padrão', function () {
        expect(CheckoutController::mensagemResultadoCupom(array('ok' => false)))->toBe('Não foi possível aplicar o cupom.');
    });
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n  " . $GLOBALS['__testes_passou'] . " passaram, " . $GLOBALS['__testes_falhou'] . " falharam\n";
exit($GLOBALS['__testes_falhou'] > 0 ? 1 : 0);

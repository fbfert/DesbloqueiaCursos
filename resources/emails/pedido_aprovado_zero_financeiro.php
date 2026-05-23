<?php use App\Core\Helpers; ?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Pedido gratuito aprovado</title></head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Pedido gratuito aprovado automaticamente</h1>
            <p>O pedido <strong><?php echo Helpers::e(isset($pedido['codigo']) ? $pedido['codigo'] : ''); ?></strong> foi aprovado sem necessidade de pagamento, porque o total final ficou em R$ 0,00 após a aplicação do cupom.</p>
            <p><strong>Aluno:</strong> <?php echo Helpers::e(isset($pedido['pagador_nome']) ? $pedido['pagador_nome'] : ''); ?></p>
            <p><strong>Total:</strong> R$ <?php echo number_format((float) (isset($pedido['total']) ? $pedido['total'] : 0), 2, ',', '.'); ?></p>
            <p><strong>Desconto aplicado:</strong> R$ <?php echo number_format((float) (isset($pedido['desconto_total']) ? $pedido['desconto_total'] : 0), 2, ',', '.'); ?></p>
            <p>Não há comprovante PIX para análise neste pedido.</p>
            <p><a href="<?php echo Helpers::e(isset($admin_pedido_url) ? $admin_pedido_url : ''); ?>">Abrir pedido no admin</a></p>
        </div>
    </div>
</body>
</html>

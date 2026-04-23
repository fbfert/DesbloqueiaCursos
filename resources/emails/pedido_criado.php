<?php use App\Core\Helpers; ?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Pedido criado</title></head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Pedido criado</h1>
            <p>Seu pedido <strong><?php echo Helpers::e(isset($pedido['codigo']) ? $pedido['codigo'] : ''); ?></strong> foi criado.</p>
            <p>Total: <?php echo Helpers::e(isset($pedido['total']) ? number_format((float) $pedido['total'], 2, ',', '.') : '0,00'); ?></p>
            <p>Agora você pode concluir o fluxo com o comprovante PIX.</p>
        </div>
    </div>
</body>
</html>

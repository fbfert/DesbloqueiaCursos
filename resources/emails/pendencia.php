<?php use App\Core\Helpers; ?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Pendencia</title></head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Pedido em pendencia</h1>
            <p>O pedido <strong><?php echo Helpers::e(isset($pedido['codigo']) ? $pedido['codigo'] : ''); ?></strong> precisa de ajuste.</p>
            <?php if (!empty($observacao)): ?>
                <p>Observacao: <?php echo Helpers::e($observacao); ?></p>
            <?php endif; ?>
            <p>Revise os dados e reenvie o comprovante, se necessario.</p>
        </div>
    </div>
</body>
</html>

<?php use App\Core\Helpers; ?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Comprovante enviado</title></head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Comprovante enviado</h1>
            <p>Recebemos o comprovante do pedido <strong><?php echo Helpers::e(isset($pedido['codigo']) ? $pedido['codigo'] : ''); ?></strong>.</p>
            <p>Você pode acompanhar a situação em <a href="<?php echo Helpers::url('meus-cursos'); ?>">Meus Cursos</a>.</p>
        </div>
    </div>
</body>
</html>

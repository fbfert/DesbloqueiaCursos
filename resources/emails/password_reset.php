<?php use App\Core\Helpers; ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperacao de senha</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Recuperacao de senha</h1>
            <p>Olá, <?php echo Helpers::e(isset($usuario['nome']) ? $usuario['nome'] : ''); ?>.</p>
            <p>Use o link abaixo para redefinir sua senha. O token expira em 60 minutos.</p>
            <p><a href="<?php echo Helpers::e(isset($reset_url) ? $reset_url : '#'); ?>">Redefinir senha</a></p>
            <p style="word-break:break-all;">Token: <?php echo Helpers::e(isset($token) ? $token : ''); ?></p>
        </div>
    </div>
</body>
</html>

<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de e-mail do portal</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Teste de e-mail enviado com sucesso</h1>
            <p>Este é um e-mail de teste do Portal de Cursos e Eventos Polo Rainbow.</p>
            <p>Destinatário informado: <strong><?php echo htmlspecialchars((string) ($destinatario_email ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <p>Data do teste: <strong><?php echo htmlspecialchars((string) ($data_teste ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <p>Se esta mensagem chegou corretamente, a configuração SMTP está funcionando para envios básicos.</p>
        </div>
    </div>
</body>
</html>

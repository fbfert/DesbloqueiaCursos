<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curso recebido como presente</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Você ganhou acesso a um curso</h1>
            <p>Olá, <?php echo htmlspecialchars((string) ($nome_usuario ?? ''), ENT_QUOTES, 'UTF-8'); ?>!</p>
            <p>Você recebeu como presente o acesso ao curso <strong><?php echo htmlspecialchars((string) ($nome_curso ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
            <p><?php echo htmlspecialchars((string) ($nome_turma ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            <p>Prazo de acesso: <strong><?php echo htmlspecialchars((string) ($prazo_acesso ?? 'sem prazo definido'), ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <p><a href="<?php echo htmlspecialchars((string) ($link_meus_cursos ?? '/meus-cursos'), ENT_QUOTES, 'UTF-8'); ?>">Acessar meus cursos</a></p>
        </div>
    </div>
</body>
</html>

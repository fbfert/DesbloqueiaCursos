<?php use App\Core\Helpers; ?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Concluído</title></head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Curso concluído</h1>
            <p>Parabéns, <?php echo Helpers::e(isset($inscricao['participante_nome']) ? $inscricao['participante_nome'] : ''); ?>.</p>
            <p>Seu curso <?php echo Helpers::e(isset($inscricao['curso_nome']) ? $inscricao['curso_nome'] : ''); ?> foi concluído.</p>
        </div>
    </div>
</body>
</html>

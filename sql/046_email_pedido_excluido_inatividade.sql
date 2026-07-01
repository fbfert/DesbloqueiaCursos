-- Polo Rainbow - modelo de e-mail para exclusão por inatividade
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

INSERT INTO emails_modelos
    (evento, template, nome, assunto, corpo_html, gatilho_descricao, variaveis_json, ativo, editavel, created_at, updated_at, deleted_at)
VALUES
(
    'email.pedido_excluido_inatividade',
    'pedido_excluido_inatividade',
    'Pedido excluído por inatividade',
    'Pedido excluído por inatividade',
    '<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido excluído por inatividade</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 16px;">Pedido excluído por inatividade</h1>
            <p>O pedido <strong>{{pedido_codigo}}</strong> foi excluído por inatividade.</p>
            <p>Curso: {{curso_nome}}<br>
            Valor não pago: {{valor_nao_pago}}</p>
            <p>Se você acredita que isso ocorreu por engano, entre em contato com a equipe de atendimento.</p>
        </div>
    </div>
</body>
</html>',
    'Enviado quando um pedido é excluído por inatividade na rotina administrativa de limpeza.',
    '["{{pedido_codigo}}","{{curso_nome}}","{{valor_nao_pago}}","{{valor_total}}","{{valor_pago}}","{{aluno_nome}}","{{aluno_email}}"]',
    1,
    1,
    NOW(),
    NOW(),
    NULL
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    assunto = VALUES(assunto),
    corpo_html = VALUES(corpo_html),
    gatilho_descricao = VALUES(gatilho_descricao),
    variaveis_json = VALUES(variaveis_json),
    ativo = VALUES(ativo),
    editavel = VALUES(editavel),
    updated_at = NOW();

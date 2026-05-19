-- Desbloqueia Cursos - modelo de e-mail: novo comprovante PIX para o financeiro
-- Compatibilidade: MySQL 5.7

SET NAMES utf8mb4;

-- Modelo padrão (configurável em /admin/emails/modelos)
INSERT INTO emails_modelos
    (evento, template, nome, assunto, corpo_html, gatilho_descricao, variaveis_json, ativo, editavel, created_at, updated_at, deleted_at)
VALUES
(
    'email.comprovante_pix_novo_admin_financeiro',
    'comprovante_pix_novo_admin_financeiro',
    'Novo comprovante PIX enviado - Financeiro',
    'Novo comprovante PIX enviado - Pedido {pedido_codigo}',
    '<!doctype html>
<html lang=\"pt-BR\">
<head>
  <meta charset=\"UTF-8\">
  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
  <title>Novo comprovante PIX</title>
</head>
<body style=\"margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;\">
  <div style=\"max-width:640px;margin:0 auto;padding:24px;\">
    <div style=\"background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;\">
      <h1 style=\"margin:0 0 16px;\">Novo comprovante PIX enviado</h1>
      <p>Olá, financeiro.</p>
      <p>Um novo comprovante PIX foi enviado na plataforma <strong>{nome_plataforma}</strong> e está aguardando análise.</p>
      <h2 style=\"margin:16px 0 8px;font-size:16px;\">Dados do envio</h2>
      <ul style=\"margin:0;padding-left:18px;\">
        <li><strong>Aluno:</strong> {aluno_nome}</li>
        <li><strong>E-mail do aluno:</strong> {aluno_email}</li>
        <li><strong>Pedido:</strong> {pedido_codigo}</li>
        <li><strong>Curso:</strong> {curso_nome}</li>
        <li><strong>Turma:</strong> {turma_nome}</li>
        <li><strong>Valor do pedido:</strong> {pedido_valor}</li>
        <li><strong>Status do pedido:</strong> {pedido_status}</li>
        <li><strong>Data do envio:</strong> {comprovante_data_envio}</li>
        <li><strong>Status do comprovante:</strong> {comprovante_status}</li>
      </ul>
      <p style=\"margin-top:16px;\">
        <a href=\"{admin_comprovante_url}\" style=\"display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 14px;border-radius:6px;\">Abrir comprovantes no admin</a>
      </p>
      <p style=\"margin-top:12px;\">
        Pedido: <a href=\"{admin_pedido_url}\">{admin_pedido_url}</a><br>
        Arquivo do comprovante: <a href=\"{comprovante_arquivo_url}\">{comprovante_arquivo_url}</a><br>
        Financeiro: <a href=\"{admin_financeiro_url}\">{admin_financeiro_url}</a>
      </p>
      <hr style=\"border:none;border-top:1px solid #e5e7eb;margin:16px 0;\">
      <p style=\"margin:0;font-size:12px;color:#6b7280;\">Esta é uma mensagem automática do sistema {nome_plataforma}.</p>
    </div>
  </div>
</body>
</html>',
    'Enviado sempre que um comprovante PIX é enviado/reenviado pelo aluno (ComprovantePixService::enviarUpload).',
    '[
        \"{aluno_nome}\",
        \"{aluno_email}\",
        \"{aluno_id}\",
        \"{pedido_id}\",
        \"{pedido_codigo}\",
        \"{pedido_valor}\",
        \"{pedido_status}\",
        \"{pedido_data}\",
        \"{curso_id}\",
        \"{curso_nome}\",
        \"{turma_id}\",
        \"{turma_nome}\",
        \"{comprovante_id}\",
        \"{comprovante_status}\",
        \"{comprovante_data_envio}\",
        \"{comprovante_arquivo_nome}\",
        \"{comprovante_arquivo_url}\",
        \"{admin_comprovante_url}\",
        \"{admin_pedido_url}\",
        \"{admin_financeiro_url}\",
        \"{nome_plataforma}\",
        \"{url_site}\",
        \"{data_atual}\"
    ]',
    1,
    1,
    NOW(),
    NOW(),
    NULL
)
ON DUPLICATE KEY UPDATE
    id = id;


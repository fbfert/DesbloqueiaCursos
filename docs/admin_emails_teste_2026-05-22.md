# E-mail de teste em `/admin/emails` — 2026-05-22

## Objetivo

Adicionar um campo para informar um endereço de e-mail manualmente e disparar um envio de teste da configuração SMTP atual.

## O que foi alterado

- `resources/views/admin/emails/index.php`
  - inclusão do bloco compacto de teste com campo `email_teste`
  - botão `Enviar teste`
  - layout responsivo para mobile
- `app/Controllers/Admin/EmailsController.php`
  - nova ação `teste()`
  - validação de e-mail com mensagem de erro amigável
- `app/Services/EmailService.php`
  - novo método `sendTestEmail()`
  - reuso do fluxo SMTP/auditoria já existente
- `routes/web.php`
  - nova rota `POST /admin/emails/teste`
- `resources/views/emails/teste_smtp.php`
  - template simples para o e-mail de teste

## Comportamento

- O destinatário é informado manualmente na tela.
- O envio usa a configuração SMTP atual do ambiente.
- O envio de teste entra no mesmo histórico/auditoria dos demais e-mails.
- O formulário preserva o valor digitado quando há validação com erro.

## Validação

- `php -l` executado nos arquivos alterados.
- Upload concluído por FTP em `ftp.desbloqueiacursos.com.br`.


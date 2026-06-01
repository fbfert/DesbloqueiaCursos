# Correção do salvamento em /admin/configuracoes-pagamento

Data: 2026-06-01

## Problema

A tela administrativa de configurações de pagamento estava voltando para o dashboard em vez de salvar e permanecer na própria página.

## Correção aplicada

- Reescrita do controller `ConfiguracoesPagamentoController` para seguir o padrão dos demais formulários administrativos.
- O POST agora salva, grava log de auditoria e faz redirect para `/admin/configuracoes-pagamento`.
- Em caso de erro, a tela retorna com flash message de erro, sem cair no fallback do roteador para `/admin/dashboard`.
- A view administrativa foi regravada com acentuação correta em português brasileiro.

## Arquivos publicados por FTP

- `app/Controllers/Admin/ConfiguracoesPagamentoController.php`
- `resources/views/admin/configuracoes-pagamento/index.php`

## Validação

- `php -l` passou nos dois arquivos alterados.
- Upload por FTP concluído.


# Admin financeiro, cupom manual e repasses - 2026-06-01

## Escopo

- Reestruturação da tela `/admin/financeiro` com visão de entradas confirmadas, filtros e exportação CSV.
- Correção do fluxo de cupom manual para pedidos aprovados ou pagos, com auditoria e preservação do status.
- Ajuste das telas administrativas de pedidos, inscrições e repasses para manter o fluxo operacional consistente.

## Principais mudanças

### Financeiro

- A tela `/admin/financeiro` passou a exibir:
  - cards de resumo das entradas confirmadas
  - filtros por ano, mês, curso, turma e categoria
  - entradas confirmadas por mês
  - entradas por curso e turma
  - últimos pedidos confirmados
  - atalhos para pedidos, comprovantes PIX, configurações financeiras e exportação CSV
- A exportação CSV foi adicionada em `/admin/financeiro/entradas/exportar`.
- O cálculo de entradas confirmadas considera pedidos `aprovado` e `pago`, excluindo presentes e pedidos de valor zero.
- O resumo mensal passou a usar a melhor data de confirmação disponível:
  - `aprovado_em`
  - `comprovantes_pix.analisado_em`
  - `status_pedidos_historico.created_at`
  - `updated_at`
  - `created_at`

### Cupom manual

- O admin agora pode aplicar ou substituir cupom em pedido confirmado sem alterar o status do pedido.
- A remoção simples de cupom em pedido confirmado continua bloqueada.
- A operação exige justificativa obrigatória.
- O sistema bloqueia ajuste se a competência financeira já estiver fechada ou paga.
- A persistência do cupom agora valida se `cupom_codigo`, `desconto_total` e `total` realmente foram gravados no banco.
- A view do pedido exibe o cupom, desconto e total atualizados imediatamente após o ajuste.

### Rateio e repasses

- O rateio passou a considerar `p.status IN ("aprovado", "pago")`.
- A data de corte do rateio usa a melhor confirmação financeira disponível.
- A configuração do percentual máximo de rateio continua centralizada em `/admin/configuracoes-globais/financeiro`.
- A área de rateios e repasses continua disponível em `/admin/rateios` e `/admin/financeiro/repasses`.

### Pedidos e inscrições

- A listagem de inscrições ficou com ação única de `Ver Pedido`.
- A listagem de pedidos recebeu o atalho para `Criar pedido manualmente`.
- O pedido manual passa a nascer como `aguardando_pagamento`.

## Validação

- `php -l app/Controllers/Admin/FinanceiroController.php`
- `php -l app/Services/FinanceiroService.php`
- `php -l app/Services/RateioService.php`
- `php -l app/Models/Pedido.php`
- `php -l app/Services/ComprovantePixService.php`
- `php -l app/Services/PedidoService.php`
- `php -l resources/views/admin/financeiro/index.php`
- `php -l routes/web.php`
- `php -l app/Controllers/Admin/PedidosController.php`
- `php -l resources/views/admin/pedidos/show.php`
- `php -l resources/views/admin/pedidos/index.php`

## Observações

- O ambiente local não tinha banco configurado para validar o pedido `134` diretamente.
- O deploy por FTP foi feito para o arquivo `app/Services/FinanceiroService.php` nesta rodada.

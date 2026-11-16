# Financeiro: correções de repasses, estornos e permissões

Mudança OpenSpec `financeiro-correcoes` (fase 0 do financeiro gerencial). Corrige defeitos que
afetavam dinheiro de verdade antes da construção dos lançamentos, da DRE e do caixa.

## O que mudou para quem usa

### Repasses (`/admin/financeiro/repasses`)

- **Gerar repasses só funciona uma vez por apuração.** Uma segunda tentativa, mesmo com dois
  cliques ao mesmo tempo, mostra "Os repasses desta apuração já foram gerados."
- **Quando o repasse exige documento:** se o professor é PJ ou se o perfil fiscal dele tem
  "exige nota fiscal" marcado. Antes, esse campo era ignorado.
- **Fluxo do documento:**

```
 exige documento                          não exige
 aguardando documento --(enviar)--> documento recebido         pendente
        ^                                  |  (validar)            |
        +-------- (rejeitar + motivo) -----+                       |
                                           v                       |
                                       aprovado ---- (pagar) ------+--> pago
```

- Enviar o documento **não aprova** o repasse. O admin abre o arquivo ("Ver documento") e clica
  em **Validar**, ou em **Rejeitar** com o motivo, que é obrigatório.
- **Pagamento:**
  - só aparece para repasse aprovado, ou pendente que não exige documento;
  - o valor é o líquido do repasse e não pode ser alterado;
  - pagar duas vezes é recusado;
  - o comprovante pode ser aberto em "Ver comprovante";
  - a data do pagamento aparece na coluna "Pago em".

### Professor (`/professor/financeiro`)

- O professor abre o próprio espelho de RPA ("Abrir espelho"). O pedido de um espelho de outro
  professor é negado (403) e registrado na auditoria.

### Rateios (`/admin/rateios`)

- Um rateio só pode ser criado, alterado ou excluído **antes** de gerar os repasses da apuração.
- Cada alteração recalcula os totais da apuração. Antes, eles ficavam desatualizados.

### Estornos e contestações (AbacatePay)

- **Reembolso** (`checkout.refunded`):
  - o pedido passa a "reembolsado" e sai das entradas;
  - o histórico do pedido registra o reembolso;
  - o acesso do aluno **não** é removido automaticamente.
- **Contestação** (`checkout.disputed`): o pedido continua pago e o histórico registra a
  contestação.
- **Pendências no painel:** o bloco "Pendências de estorno e contestação" em `/admin/financeiro`
  lista o que precisa de decisão:
  - reembolsado com inscrição ativa;
  - contestado;
  - reembolsado numa competência que já gerou repasses ("ajuste de repasse pendente"). Esse
    ajuste ainda é manual e o automático vem numa fase futura.

### Exportação de entradas

- O CSV traz **todos** os pedidos confirmados do filtro, não só os 20 últimos.

### Permissões

| Ação | Antes | Agora |
|---|---|---|
| Aprovar ou reprovar comprovante PIX | `pedidos.ver` ou `financeiro.ver` bastavam | `pedidos.gerenciar` ou `financeiro.gerenciar` |
| Registrar pagamento, gerar repasses, validar documento, perfil fiscal | `financeiro.ver` ou `pedidos.ver` bastavam no serviço | `financeiro.gerenciar` |

Enviar comprovante PIX em nome do aluno continua como antes.

## Arquivos

- **Migration:** `sql/084_financeiro_correcoes.sql`.
- **Services:**
  - `RepasseProfessorService`: idempotência, estados, validação, pagamento;
  - `FinanceiroService`: permissões, arquivos, pendências, exportação;
  - `RateioService`: bloqueio e recálculo;
  - `PedidoService`: `registrarReembolsoGateway`, `registrarContestacaoGateway`;
  - `Payments/AbacatePayService`: efeitos de estorno e contestação;
  - `ComprovantePixService`: permissão de gestão.
- **Models:** `RepasseProfessor`, `RepasseDocumento`, `PagamentoProfessor` e `RpaEspelho`.
- **Controllers:** `Admin/FinanceiroController` e `Professor/FinanceiroController`.
- **Views:** `admin/financeiro/{index,repasses}.php` e `professor/financeiro/index.php`.
- **Rotas novas:**
  - `POST /admin/financeiro/documento/validar`
  - `POST /admin/financeiro/documento/rejeitar`
  - `GET /admin/financeiro/documento/arquivo`
  - `GET /admin/financeiro/pagamento/comprovante`
  - `GET /professor/financeiro/rpa`
- **Testes:**
  - `tests/Unit/financeiro_repasses.php`
  - `tests/Unit/financeiro_rateio.php`
  - `tests/Unit/abacatepay_estorno.php`
  - `tests/Unit/financeiro_permissoes.php`
  - três rotas novas no smoke.

## Limitação histórica

Até esta mudança, registrar o pagamento de um repasse gravava a data do pagamento em
`repasses_professores.documento_validado_em`.

- A migration 084 cria `pago_em` e o preenche para os repasses já pagos.
- O `documento_validado_em` desses repasses antigos **não** foi corrigido, porque não há como
  separar o valor real do sobrescrito.
- Nenhum relatório depende desse campo.

## Deploy

1. **Antes**, em produção, procurar repasses duplicados:

   ```sql
   SELECT apuracao_id, usuario_id, COUNT(*)
   FROM repasses_professores
   WHERE deleted_at IS NULL
   GROUP BY apuracao_id, usuario_id
   HAVING COUNT(*) > 1;
   ```

   Duplicatas encontradas são tratadas pela lixeira, com justificativa.

2. **Antes**, listar quem perde a aprovação de PIX e decidir quem recebe `pedidos.gerenciar`:

   ```sql
   SELECT DISTINCT u.id, u.nome, u.email, p.nome AS perfil
   FROM usuarios u
   JOIN usuario_perfis up ON up.usuario_id = u.id
   JOIN perfis p ON p.id = up.perfil_id
   WHERE p.slug <> 'superadmin'
     AND EXISTS (SELECT 1 FROM perfil_permissoes pp JOIN permissoes pe ON pe.id = pp.permissao_id
                 WHERE pp.perfil_id = p.id AND pe.slug IN ('pedidos.ver', 'financeiro.ver'))
     AND NOT EXISTS (SELECT 1 FROM perfil_permissoes pp JOIN permissoes pe ON pe.id = pp.permissao_id
                     WHERE pp.perfil_id = p.id AND pe.slug IN ('pedidos.gerenciar', 'financeiro.gerenciar'));
   ```

3. Aplicar `sql/084_financeiro_correcoes.sql`. É aditiva e pode rodar de novo.
4. Enviar o código.
5. **Rollback:** reverter o código. As colunas `pago_em` e `motivo_rejeicao` podem ficar.

## Situação do deploy (16/11/2026)

- Deploy `deploy-15115e5` feito em 16/11/2026 às 15:31, com a migration 084. Smoke de produção:
  57 de 57 (`SMOKE_GOOGLE=ativo`). Backup do deploy em `~/backups/deploy-20261116-153103`.
- **Pendentes:** as consultas dos passos 1 e 2 acima não foram rodadas antes do deploy. Rodar as
  duas e, para cada perfil que precise continuar aprovando PIX, dar `pedidos.gerenciar`.

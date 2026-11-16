# Proposal

## Why

A revisão do `/admin/financeiro` (16/11/2026) encontrou defeitos que afetam dinheiro de verdade.
Gerar repasses duas vezes duplica o pagamento dos professores. Um professor PJ pode ser pago sem
nota fiscal. Estornos e contestações do AbacatePay são ignorados e o pedido continua contando
como receita. Essas correções vêm antes do financeiro gerencial (`financeiro-gerencial-base`),
que vai construir lançamentos, DRE e caixa sobre esses mesmos dados.

## What Changes

- **Repasses não duplicam.** Gerar repasses de uma apuração que já tem repasses é recusado com
  mensagem clara, mesmo com dois cliques simultâneos.
- **Fluxo de documento completo.**
  - O admin consegue abrir o documento enviado e o comprovante de pagamento por rota protegida.
  - O admin valida ou rejeita o documento. Rejeitar exige motivo.
  - Um repasse que exige documento só fica "aprovado" com documento validado.
  - Passa a exigir documento: professor PJ ou perfil fiscal com "exige nota fiscal" marcado.
    Hoje esse campo é ignorado.
- **Pagamento só de repasse liberado.**
  - Pagar exige status "aprovado", ou "pendente" quando o repasse não exige documento.
  - O valor pago precisa ser igual ao valor líquido do repasse.
  - A data do pagamento é gravada em campo próprio (`pago_em`), e não mais em
    `documento_validado_em`.
- **Professor baixa o próprio espelho de RPA** em `/professor/financeiro`, sem ver arquivos de
  outros professores.
- **Estorno e contestação do gateway.**
  - Pagamento reembolsado (`checkout.refunded`) muda o pedido para "reembolsado", registra o
    histórico e o retira das entradas confirmadas.
  - Contestação (`checkout.disputed`) mantém o pedido como pago e registra alerta no histórico.
  - Os dois casos aparecem como pendência no painel financeiro.
  - Pedido de uma apuração já fechada fica sinalizado para ajuste manual. O ajuste automático do
    repasse vem numa mudança futura.
  - O acesso do aluno **não** é revogado automaticamente. O admin decide pela pendência.
- **Rateio manual coerente.**
  - Editar ou excluir rateio só é permitido enquanto a apuração não tem repasses gerados.
  - Os totais da apuração são recalculados após cada alteração.
- **Permissões.**
  - No serviço financeiro, gerir passa a exigir `financeiro.gerenciar`. Hoje basta
    `financeiro.ver` ou `pedidos.ver`.
  - Aprovar ou reprovar comprovante PIX passa a exigir `pedidos.gerenciar` ou
    `financeiro.gerenciar`. Permissões só de leitura deixam de bastar.
  - **BREAKING** para perfis que hoje operam só com permissão de leitura.
- **Exportação completa.** O CSV de entradas traz todos os pedidos confirmados do filtro, e não só
  os 20 últimos.

## Capabilities

### New Capabilities

- `apuracao-repasses`: apuração mensal, rateio por curso/turma, geração de repasses aos
  professores, documentos fiscais (NF/RPA) e registro de pagamento.
- `entradas-financeiras`: reconhecimento das vendas confirmadas como entradas, efeito de
  estornos e contestações, painel e exportação de entradas, permissões do financeiro.

### Modified Capabilities

Nenhuma.

## Impact

- **Código:**
  - `RepasseProfessorService`, `RateioService`, `FinanceiroService`, `ComprovantePixService`,
    `AbacatePayService` (eventos de estorno/contestação) e `PedidoService` (transição para
    `reembolsado`);
  - models `RepasseProfessor` e `RepasseDocumento`;
  - controllers `Admin/FinanceiroController` e `Professor/FinanceiroController`;
  - views `admin/financeiro/{index,repasses}.php` e `professor/financeiro/index.php`.
- **Rotas novas:**
  - `POST /admin/financeiro/documento/validar`
  - `POST /admin/financeiro/documento/rejeitar`
  - `GET /admin/financeiro/documento/arquivo`
  - `GET /admin/financeiro/pagamento/comprovante`
  - `GET /professor/financeiro/rpa`
- **Migration:** `sql/084_financeiro_correcoes.sql`. Aditiva: `repasses_professores.pago_em`,
  `repasses_documentos.motivo_rejeicao` e preenchimento de `pago_em` a partir de
  `pagamentos_professores`.
- **Produção:**
  - Antes do deploy, verificar se já existem repasses duplicados por apuração/professor.
  - Revisar os perfis que aprovam PIX só com permissão de leitura e dar a eles
    `pedidos.gerenciar`.
  - Rollback: reverter o código. As colunas novas podem ficar.

## Fora de escopo

- Lançamentos, contas, categorias, DRE e caixa (`financeiro-gerencial-base` e mudanças seguintes).
- Ajuste automático de repasse após estorno, saldo negativo do professor e pagamento parcial
  (mudança futura de repasses).
- Estorno iniciado pelo admin e devolução/MED do PIX manual.
- RPA em PDF com IRRF/INSS/ISS.
- Mudança na regra de rateio. Continua o teto aplicado e dividido igualmente pela apuração
  automática.

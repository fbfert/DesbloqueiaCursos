# Proposal

## Why

O responsável decidiu, em 16/11/2026, que o `/admin/financeiro` vai cuidar do **negócio inteiro**
como financeiro **gerencial**, com a contabilidade oficial a cargo de um contador externo. Hoje o
sistema só enxerga vendas e repasses. Não sabe quanto entrou em cada conta, quanto o gateway
cobrou, quanto se gastou nem em quê. Por isso não há como montar caixa, DRE nem uma exportação
mensal para o contador.

Esta mudança cria a base que as próximas fases (contas a pagar e receber, estornos, repasses 2.0,
DRE e conciliação) vão usar: **contas financeiras, plano de contas gerencial, centros de custo e
lançamentos**, com as vendas e os repasses entrando sozinhos.

Depende de `financeiro-correcoes` (entradas e repasses confiáveis).

## What Changes

- **Contas financeiras.**
  - Cadastro das contas por onde o dinheiro passa: banco, gateway e caixa. Cada uma tem saldo
    inicial e data de abertura.
  - O saldo atual é calculado a partir dos lançamentos realizados.
  - Contas sem uso podem ser arquivadas.
- **Plano de contas gerencial.**
  - Categorias de receita e despesa em até dois níveis.
  - Cada categoria tem um **grupo da DRE** e o campo **código da conta contábil** do contador.
  - Vem com um plano padrão pronto, que o admin pode editar.
- **Centros de custo.**
  - Áreas da empresa, como Administrativo, Marketing e Pedagógico.
  - Os lançamentos também podem ser ligados a curso/evento e turma, para medir resultado por
    curso.
- **Lançamentos.**
  - Receita, despesa ou transferência entre contas.
  - Cada lançamento tem data de caixa, competência, valor, conta, categoria, centro de custo,
    curso/turma opcionais, documento e anexo.
  - Lançamentos manuais podem ser editados, com auditoria, e excluídos pela lixeira, com
    justificativa.
  - Lançamentos automáticos não podem ser editados nem excluídos, só reclassificados (categoria
    ou centro de custo). Uma correção é feita com lançamento de estorno, nunca apagando.
- **Vendas entram sozinhas.**
  - Cada pedido confirmado gera, por item, uma receita bruta ligada ao curso/turma. Gera também a
    despesa da **taxa do meio de pagamento**, calculada pela tabela de taxas por meio (percentual
    e valor fixo, com vigência).
  - Um pedido reembolsado gera os estornos correspondentes.
  - Nada é lançado em duplicidade.
- **Repasses entram sozinhos.** Registrar o pagamento de um repasse gera a despesa "Repasse a
  professores" na conta escolhida no pagamento.
- **Vendas antigas.** Uma ação de admin importa as vendas confirmadas desde uma data escolhida, em
  lotes, sem estourar o tempo limite do PHP.
- **Exportação para o contador.**
  - CSV mensal dos lançamentos, por competência ou por caixa, com o código contábil.
  - Um resumo por categoria do período.
- **Rateio sobre o líquido de taxas (opcional, desligado por padrão).**
  - A regra vigente diz que o rateio é calculado sobre o valor líquido recebido. Hoje a base
    ignora a taxa do gateway porque ela não é registrada.
  - Uma configuração nova permite, a partir de uma competência escolhida, descontar as taxas da
    base do rateio.
  - **Ligar essa opção muda o valor pago aos professores e é decisão do responsável.**

## Capabilities

### New Capabilities

- `financeiro-gerencial`:
  - contas financeiras;
  - plano de contas gerencial com código contábil;
  - centros de custo;
  - lançamentos (manuais e automáticos de vendas, taxas, estornos e repasses);
  - saldos;
  - exportação mensal para o contador.

### Modified Capabilities

- `apuracao-repasses` (criada por `financeiro-correcoes`):
  - o registro de pagamento passa a exigir a conta financeira de saída;
  - a base do rateio pode, por configuração, descontar as taxas dos meios de pagamento.

## Impact

- **Código novo:**
  - `FinanceiroGerencialService`, para contas, categorias, centros de custo e lançamentos;
  - `LancamentoAutomaticoService`, para vendas, taxas, estornos e repasses;
  - models `FinConta`, `FinCategoria`, `FinCentroCusto`, `FinLancamento` e `FinTaxaMeio`;
  - controllers em `Admin/Financeiro*`;
  - views em `resources/views/admin/financeiro/`.
- **Código alterado:**
  - `PedidoService`: chamada à sincronização quando o pedido é confirmado ou reembolsado;
  - `RepasseProfessorService`: conta de saída e lançamento do pagamento;
  - `RateioService`: base com taxas, se configurada;
  - `ConfiguracaoGlobalService`: configurações novas;
  - menu do admin.
- **Rotas novas** em `/admin/financeiro/*`: contas, categorias, centros de custo, taxas,
  lançamentos, importação de vendas e exportação para o contador. Usam `financeiro.ver` e
  `financeiro.gerenciar`, sem permissão nova.
- **Migration:** `sql/085_financeiro_gerencial_base.sql`.
  - Cria as tabelas `fin_contas`, `fin_categorias`, `fin_centros_custo`, `fin_taxas_meio` e
    `fin_lancamentos`.
  - Insere o plano de contas, os centros de custo e as contas padrão.
  - Adiciona `pagamentos_professores.conta_id` e cinco colunas em `configuracoes_financeiras`
    (contas padrão e opção de rateio líquido).
  - Aditiva.
- **Produção:**
  - Depois do deploy, o admin confere as contas e os saldos iniciais, preenche os códigos
    contábeis com o contador e roda a importação de vendas antigas.
  - Rollback: reverter o código. As tabelas `fin_*` podem ficar ou ser removidas com `DROP`, pois
    nenhuma tabela existente depende delas.

## Fora de escopo

- Contas a pagar e a receber com vencimento, recorrência e parcelas (próxima fase). Aqui só há
  lançamentos já realizados.
- DRE formatada, fluxo de caixa previsto, gráficos e fechamento de período (fase de relatórios).
  O grupo da DRE já fica gravado em cada categoria.
- Conciliação bancária e importação de OFX.
- Layout de importação específico de um sistema contábil (Domínio, Alterdata etc.). A exportação
  é CSV genérico.
- Ajuste de repasse após estorno, extrato do professor e regras de repasse por curso.
- Emissão de NFS-e.

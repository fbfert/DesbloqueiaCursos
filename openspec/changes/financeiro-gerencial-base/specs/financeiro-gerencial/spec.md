# Spec Delta

## Purpose

Registrar todo o dinheiro que entra e sai da empresa em contas, categorias e centros de custo
gerenciais, com vendas e repasses lançados automaticamente, para dar saldo, resultado e uma
exportação mensal ao contador.

## ADDED Requirements

### Requirement: Contas financeiras
O admin com `financeiro.gerenciar` SHALL cadastrar contas financeiras do tipo banco, gateway ou
caixa, com saldo inicial e data de abertura. O saldo atual MUST ser o saldo inicial mais as
entradas menos as saídas dos lançamentos realizados, incluindo transferências. Conta com
lançamentos MUST NOT ser excluída, só arquivada.

#### Scenario: Saldo calculado
- **GIVEN** uma conta com saldo inicial de R$ 1.000,00
- **AND** receitas realizadas de R$ 500,00 e despesas realizadas de R$ 200,00 nela
- **WHEN** o admin abre a lista de contas
- **THEN** o saldo atual mostrado é R$ 1.300,00

#### Scenario: Arquivar conta usada
- **GIVEN** uma conta com lançamentos
- **WHEN** o admin tenta excluí-la
- **THEN** o sistema recusa e oferece arquivar
- **AND** uma conta arquivada não aparece para novos lançamentos

### Requirement: Plano de contas gerencial
O sistema SHALL manter categorias de receita e de despesa em até dois níveis. Cada categoria MUST
ter um grupo da DRE e MAY ter um código da conta contábil do contador. Na instalação, MUST existir
um plano padrão editável. Categoria com lançamentos MUST NOT ser excluída, só desativada.

#### Scenario: Plano padrão
- **WHEN** a migration da mudança é aplicada
- **THEN** existem as categorias padrão, incluindo:
  - "Venda de cursos" e "Venda de eventos" como receita;
  - "Estornos e reembolsos" como dedução;
  - "Taxas de meios de pagamento" e "Repasse a professores" como custo direto;
  - despesas operacionais básicas.

#### Scenario: Código contábil preenchido
- **WHEN** o admin informa o código "4.1.02.005" na categoria "Repasse a professores"
- **THEN** a exportação para o contador traz esse código em cada lançamento da categoria

### Requirement: Centros de custo
O sistema SHALL manter centros de custo, como Administrativo, Marketing e Pedagógico. Todo
lançamento MAY indicar um centro de custo e MAY se ligar a um curso/evento e a uma turma.

#### Scenario: Despesa de um curso
- **WHEN** o admin lança uma despesa ligada ao centro "Pedagógico" e ao curso X
- **THEN** a listagem filtrada pelo curso X mostra essa despesa

### Requirement: Lançamentos manuais
O admin com `financeiro.gerenciar` SHALL registrar receitas, despesas e transferências realizadas,
com data de caixa, competência, valor positivo, conta, categoria do tipo compatível e, opcionalmente,
centro de custo, curso/turma, número de documento, descrição e anexo. A transferência MUST ter conta
de origem e de destino diferentes e MUST NOT ter categoria. Toda criação e edição MUST gerar
auditoria. A exclusão MUST passar pela lixeira com justificativa.

#### Scenario: Despesa registrada
- **WHEN** o admin lança uma despesa de R$ 89,90 em "Hospedagem e software", paga pela conta
  "Banco"
- **THEN** o lançamento aparece na lista
- **AND** o saldo do "Banco" diminui R$ 89,90

#### Scenario: Categoria incompatível
- **WHEN** o admin tenta lançar uma despesa numa categoria de receita
- **THEN** o sistema recusa e explica o motivo

#### Scenario: Transferência entre contas
- **WHEN** o admin transfere R$ 2.000,00 da conta "AbacatePay" para a conta "Banco"
- **THEN** o saldo do "AbacatePay" diminui e o do "Banco" aumenta R$ 2.000,00
- **AND** o resultado (receitas menos despesas) não muda

#### Scenario: Exclusão sem justificativa
- **WHEN** o admin tenta excluir um lançamento manual sem justificativa
- **THEN** o sistema recusa

### Requirement: Lançamentos automáticos protegidos
Lançamentos gerados pelo sistema MUST NOT ser editados em valor, data ou conta, nem excluídos. O
admin SHALL apenas reclassificar categoria e centro de custo, com auditoria. Correções MUST ser
feitas por lançamento de estorno ligado ao original.

#### Scenario: Tentativa de editar venda
- **WHEN** o admin tenta mudar o valor de uma receita de venda
- **THEN** o sistema recusa
- **AND** permite apenas trocar categoria ou centro de custo

### Requirement: Receita e taxa das vendas
Para cada pedido que passa a entrada confirmada, o sistema MUST lançar, por item, a receita
bruta, com competência e data de caixa na confirmação, ligada ao curso/turma do item e à conta do
meio usado. A despesa da taxa MUST ser lançada pela tabela vigente de taxas do meio. O
processamento MUST ser idempotente.

#### Scenario: Venda pelo AbacatePay
- **GIVEN** a taxa do meio "AbacatePay PIX" vigente em 0,99% + R$ 0,00
- **WHEN** um pedido de R$ 300,00, de um item da turma T, é confirmado pelo gateway
- **THEN** há uma receita de R$ 300,00 em "Venda de cursos", na conta "AbacatePay", ligada à
  turma T
- **AND** há uma despesa de R$ 2,97 em "Taxas de meios de pagamento", na mesma conta e turma

#### Scenario: Pedido com dois itens
- **WHEN** um pedido de R$ 450,00 com cupom, de dois itens, é confirmado
- **THEN** a receita é dividida entre os itens na proporção do valor de cada um
- **AND** a soma é R$ 450,00

#### Scenario: Confirmação repetida
- **WHEN** a sincronização do mesmo pedido roda duas vezes
- **THEN** existe um único conjunto de lançamentos daquele pedido

#### Scenario: PIX manual
- **WHEN** um pedido pago por PIX manual é aprovado
- **THEN** a receita vai para a conta configurada como recebedora do PIX manual
- **AND** a taxa segue a tabela do meio "PIX manual", que pode ser zero

### Requirement: Estorno das vendas
Quando um pedido com lançamentos passa a "reembolsado", o sistema MUST lançar estornos ligados a
cada receita original, em "Estornos e reembolsos", com data do reembolso. A taxa original MUST
permanecer, porque o gateway não a devolve. O processamento MUST ser idempotente.

#### Scenario: Reembolso de venda
- **GIVEN** um pedido de R$ 300,00 com receita e taxa lançadas
- **WHEN** o pedido é reembolsado
- **THEN** há um estorno de R$ 300,00 ligado à receita original, com data do reembolso
- **AND** a despesa da taxa continua lançada

### Requirement: Pagamento de repasse lançado
Registrar o pagamento de um repasse MUST gerar uma despesa realizada em "Repasse a professores",
na conta de saída informada, com competência da apuração e data do pagamento.

#### Scenario: Repasse pago
- **WHEN** o admin paga um repasse de R$ 750,00 da competência 2026-10 pela conta "Banco" em
  05/11/2026
- **THEN** há uma despesa de R$ 750,00, com competência 2026-10 e data de caixa 05/11/2026, na
  conta "Banco"

### Requirement: Importação de vendas antigas
O admin com `financeiro.gerenciar` SHALL importar as vendas confirmadas a partir de uma data
escolhida. A importação MUST rodar em lotes iniciados pela tela, mostrar o progresso, poder ser
retomada e ser idempotente. Reembolsos já ocorridos MUST gerar os estornos correspondentes.

#### Scenario: Importação retomada
- **GIVEN** uma importação interrompida no meio
- **WHEN** o admin a inicia de novo com a mesma data
- **THEN** os pedidos já lançados não são duplicados
- **AND** os restantes são lançados

### Requirement: Listagem e saldos
A tela de lançamentos SHALL filtrar por período (caixa ou competência), tipo, conta, categoria,
centro de custo, curso/turma e origem (manual ou automático). Ela MUST mostrar o total de
receitas, despesas e resultado do filtro, e o total por categoria.

#### Scenario: Resultado do mês
- **WHEN** o admin filtra a competência 2026-10
- **THEN** a tela mostra o total de receitas, o total de despesas, o resultado e a soma por
  categoria do mês

### Requirement: Exportação para o contador
O admin com `financeiro.ver` SHALL exportar em CSV os lançamentos de um mês, por competência ou
por caixa. Cada linha MUST trazer:
- data de caixa e competência;
- tipo e valor com sinal;
- conta;
- categoria e código contábil;
- centro de custo e curso/turma;
- documento, descrição e origem.

O arquivo MUST trazer também o resumo por categoria. A exportação MUST seguir o padrão CSV do
projeto (separador `;`, UTF-8 com BOM).

#### Scenario: Exportação de outubro
- **WHEN** o admin exporta a competência 2026-10
- **THEN** o arquivo traz todos os lançamentos não excluídos dessa competência
- **AND** traz o código contábil de cada categoria e o resumo por categoria

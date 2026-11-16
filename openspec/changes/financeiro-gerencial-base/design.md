# Design

## Context

Depois de `financeiro-correcoes`, o financeiro tem entradas confiáveis (pedidos `aprovado`/`pago`)
e repasses com pagamento registrado em `pagamentos_professores`. Não existe registro de contas,
despesas ou taxas.

- O nome `categorias` já é usado pelas categorias de curso (`sql/003_catalogo.sql`). Por isso
  todas as tabelas novas usam o prefixo `fin_`.
- `configuracoes_financeiras` guarda configurações em colunas, não em pares chave/valor.
- O status do pedido muda em vários pontos, sem um único ponto central:
  - `PedidoService::confirmarPagamentoGateway` (L687);
  - `PedidoService::aprovarPedido` (L1520);
  - `ComprovantePixService::aprovar` (L173);
  - `PedidoService::registrarStatus` (L882);
  - o reembolso criado em `financeiro-correcoes`.

## Goals / Non-Goals

**Goals:**
- um modelo de lançamentos simples, que sirva às fases de contas a pagar, DRE e conciliação sem
  ser refeito;
- vendas, taxas, estornos e repasses lançados de forma automática e idempotente;
- exportação utilizável pelo contador.

**Non-Goals:** partidas dobradas, balanço patrimonial, lançamentos previstos (vencimentos),
fechamento de período, conciliação.

## Decisions

### D1. Lançamento único de partida simples (não partidas dobradas)
Uma linha em `fin_lancamentos` representa um movimento de uma conta.

- `tipo`: `receita`, `despesa` ou `transferencia`.
- `valor` é sempre positivo, e o sinal sai do tipo.
- A transferência guarda `conta_id` (origem) e `conta_destino_id` e não tem categoria.
- O saldo de uma conta é: saldo inicial + receitas − despesas − transferências que saem +
  transferências que entram.
- *Alternativa descartada:* ledger de partidas dobradas (Formance/TigerBeetle/ERPNext). Foi
  decisão do responsável que o financeiro é gerencial e a contabilidade fica com o contador. As
  partidas dobradas exigiriam plano de contas contábil completo, sem ganho para DRE e caixa
  gerenciais.
- O campo `codigo_contabil` da categoria faz a ponte para o contador.

### D2. Tabelas (MySQL 5.7, `DECIMAL(12,2)`, InnoDB, utf8mb4)

```
fin_contas          id, nome, tipo ENUM(banco,gateway,caixa), banco/agencia/numero (texto livre),
                    saldo_inicial, data_abertura, arquivada, timestamps, deleted_at

fin_categorias      id, parent_id NULL, nome, tipo ENUM(receita,despesa),
                    grupo_dre ENUM(receita_bruta,deducoes,custos_diretos,despesas_operacionais,
                    resultado_financeiro,impostos_lucro), codigo_contabil VARCHAR(40) NULL,
                    chave_sistema VARCHAR(40) NULL UNIQUE, ativa, ordem, timestamps

fin_centros_custo   id, nome, ativo, timestamps

fin_taxas_meio      id, meio ENUM(abacatepay_pix,abacatepay_cartao,pix_manual,outro),
                    percentual DECIMAL(6,4), valor_fixo, vigente_desde DATE, timestamps

fin_lancamentos     id, tipo, status ENUM(realizado,cancelado) DEFAULT realizado,
                    data_caixa DATE, competencia CHAR(7), valor,
                    conta_id, conta_destino_id NULL, categoria_id NULL, centro_custo_id NULL,
                    curso_evento_id NULL, turma_id NULL,
                    origem ENUM(manual,venda,taxa_venda,estorno_venda,repasse),
                    origem_tipo VARCHAR(40) NULL, origem_id BIGINT NULL,
                    chave_idempotencia VARCHAR(120) NULL UNIQUE,
                    estorno_de_id NULL, numero_documento, descricao, anexo_caminho,
                    anexo_nome_original, criado_por_usuario_id, timestamps, deleted_at
                    KEY (competencia), KEY (data_caixa), KEY (conta_id), KEY (categoria_id),
                    KEY (origem_tipo, origem_id), KEY (curso_evento_id, turma_id)
```

- `status` já prevê `previsto` na fase de contas a pagar, por `ALTER ... MODIFY` aditivo. Nesta
  fase só existe `realizado`.
- `chave_sistema` identifica as categorias que o código usa ("venda_cursos", "venda_eventos",
  "estornos", "taxas_meio", "repasse_professores"). O admin pode renomeá-las sem quebrar a
  automação. Uma categoria com `chave_sistema` não pode ser desativada.
- `chave_idempotencia` é UNIQUE e aceita vários NULL (lançamentos manuais).
- `pagamentos_professores` ganha `conta_id NULL`.
- `configuracoes_financeiras` ganha as colunas:
  - `conta_pix_manual_id`, `conta_abacatepay_id` e `conta_repasse_padrao_id`;
  - `rateio_descontar_taxas TINYINT DEFAULT 0` e `rateio_descontar_taxas_desde CHAR(7) NULL`.

### D3. Sincronização por estado, não por evento
`LancamentoAutomaticoService::sincronizarPedido($pedidoId)` olha o estado atual do pedido e
completa o que falta:

```
 pedido confirmado (aprovado/pago, não presente, total > 0)?
   sim --> para cada item: receita "pedido:{id}:item:{itemId}:receita"   (se não existe)
                           taxa    "pedido:{id}:item:{itemId}:taxa"      (se taxa > 0 e não existe)
 pedido reembolsado e tem receitas?
   sim --> para cada receita: estorno "pedido:{id}:item:{itemId}:estorno" (se não existe)
```

- O serviço é chamado ao fim de cada caminho que confirma ou reembolsa um pedido (ver Context),
  **depois** do commit da transação do pedido.
- Uma falha na sincronização só gera log (`Logger::error`) e não desfaz o pagamento.
- A mesma função alimenta a importação de vendas antigas. O painel mostra o contador "pedidos
  confirmados sem lançamento" com o botão "Sincronizar", que corrige qualquer chamada perdida.
- O `INSERT` usa a chave única. Uma colisão (`23000`) é tratada como "já existe", o que torna a
  operação segura sob concorrência.
- *Alternativa descartada:* criar os lançamentos dentro da transação do pedido. Acoplaria o
  webhook ao financeiro, e uma falha aqui recusaria um pagamento real.

### D4. Rateio por item e datas
- A receita de cada item é `pedido.total × item.valor / soma dos itens`, a mesma fórmula da
  apuração atual (`FinanceiroService` L412). A diferença de arredondamento vai para o último item.
- A taxa é calculada sobre a receita do item pela linha de `fin_taxas_meio` vigente na data de
  confirmação: `percentual × receita + valor_fixo` rateado. O valor fixo vai inteiro para o
  primeiro item.
- O meio é deduzido de `pedidos.payment_gateway`/`payment_provider_method`. Sem gateway, é
  `pix_manual`.
- `data_caixa` e `competencia` vêm da mesma data de confirmação já usada pelas entradas
  (`FinanceiroService` L687, `COALESCE` de `aprovado_em` etc.). Assim os totais do painel atual e
  os dos lançamentos batem.
- O estorno usa a data do histórico de status `reembolsado`.

### D5. Taxas configuradas, não lidas do gateway
O webhook do AbacatePay, como está integrado hoje, não traz a taxa cobrada. A taxa vem da tabela
`fin_taxas_meio`, mantida pelo admin com vigência.

- Se no futuro o gateway informar a taxa, `sincronizarPedido` passa a preferir o valor informado.
  O formato do lançamento não muda.
- Divergências entre a taxa estimada e a cobrada aparecem na conciliação (fase futura).

### D6. Rateio sobre o líquido de taxas (opcional)
- `RateioService::apurarCompetencia` passa a obter a base por item de uma consulta que soma
  `receita − taxa` dos lançamentos do item. Isso só acontece quando
  `rateio_descontar_taxas = 1` e a competência é igual ou posterior a
  `rateio_descontar_taxas_desde`.
- Antes de apurar com a opção ligada, a apuração exige que todos os pedidos da competência estejam
  sincronizados. Se não estiverem, recusa com o link para "Sincronizar".
- A opção vem **desligada**. Ligá-la é uma decisão do responsável, porque muda o valor pago aos
  professores. A tela mostra esse aviso.

### D7. Telas e rotas
Todas as telas usam o layout V1 do admin e ficam no submenu "Financeiro":

```
 /admin/financeiro                  painel (existente) + saldos das contas + "sem lançamento"
 /admin/financeiro/lancamentos      lista com filtros, totais e soma por categoria
     /criar /editar /excluir        manual (excluir via TrashService; editar automático = só
                                    reclassificar)
 /admin/financeiro/contas           CRUD + arquivar
 /admin/financeiro/categorias       árvore de 2 níveis + código contábil + grupo DRE
 /admin/financeiro/centros-custo    CRUD
 /admin/financeiro/taxas            tabela por meio com vigência
 /admin/financeiro/importar-vendas  tela + POST em lotes via AJAX (padrão TurmaEmailService)
 /admin/financeiro/exportar-contador  GET com competência/caixa e mês -> CSV em streaming
```

- GET usa `financeiro.ver` e POST usa `financeiro.gerenciar`.
- Os formulários seguem `redirectAfterCrudSave` ("salvar e continuar/sair/novo").

### D8. Importação de vendas antigas
- A tela recebe a data inicial. Cada POST processa um lote de 50 pedidos confirmados (ou
  reembolsados) com `id > ultimo_id`, ordenados por id, chamando `sincronizarPedido`.
- A resposta devolve `ultimo_id`, a quantidade processada e se terminou. O JS repete até o fim.
- Não há estado no servidor: retomar é recomeçar, e a idempotência garante que nada duplica.

## Risks / Trade-offs

- [Taxa estimada diferente da cobrada] → o admin mantém `fin_taxas_meio` com vigência. A
  divergência é ajustada por lançamento manual até a conciliação existir.
- [Chamada de sincronização esquecida em algum caminho de confirmação] → o indicador "pedidos
  confirmados sem lançamento" e o botão "Sincronizar" tornam a falha visível e reparável. Um teste
  cobre os caminhos conhecidos.
- [Totais do painel antigo e dos lançamentos divergirem] → ambos usam a mesma data de confirmação
  e a mesma fórmula por item. Um teste compara os totais de uma competência.
- [Volume] → `fin_lancamentos` cresce de 2 a 3 linhas por item vendido, o que é pequeno para o
  volume atual. Os índices por competência e data de caixa cobrem as consultas.
- [Ligar o rateio líquido muda a remuneração] → a opção vem desligada, tem competência inicial
  explícita e aviso na tela. Competências já apuradas não mudam.

## Migration Plan

1. Aplicar `sql/085_financeiro_gerencial_base.sql`. Ela cria as tabelas `fin_*` e insere o
   seguinte:
   - categorias padrão com `chave_sistema`;
   - os centros de custo Administrativo, Marketing e Pedagógico;
   - as contas "AbacatePay" (gateway) e "Banco" (banco), com saldo inicial 0;
   - as taxas iniciais com valor 0 e vigência desde 2020-01-01, a preencher pelo admin;
   - as colunas novas, com as configurações apontando para as contas criadas.
2. Fazer o deploy do código.
3. O admin preenche:
   - as taxas reais;
   - os saldos iniciais e as datas de abertura das contas;
   - os códigos contábeis, com o contador.
4. O admin roda "Importar vendas antigas" a partir da data combinada.
5. **Rollback:** reverter o código. As tabelas `fin_*` e as colunas novas ficam sem uso, e
   `DROP` é opcional.

## Open Questions

- Quais são as taxas reais do AbacatePay por meio (PIX/cartão) no contrato atual? O admin preenche
  depois do deploy, sem mudar o código.
- A partir de que data importar as vendas antigas: início de 2026 ou desde o começo? O responsável
  decide no uso, sem mudar o código.

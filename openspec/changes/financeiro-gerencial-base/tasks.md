# Tasks

> Pré-requisito: `financeiro-correcoes` implementada (usa `registrarReembolsoGateway`, as
> permissões corrigidas e o pagamento de repasse com status validado).

## 1. Migration e dados padrão

- [ ] 1.1 Criar `sql/085_financeiro_gerencial_base.sql` conforme D2 e o Migration Plan:
  - as tabelas `fin_*`, com `CREATE TABLE IF NOT EXISTS`;
  - as colunas novas, criadas só se não existirem (via `information_schema`);
  - os dados padrão, com `INSERT … WHERE NOT EXISTS` pela `chave_sistema`/nome.
  - Verificar: aplicar duas vezes no MariaDB local do Docker, sem erro e sem duplicar dados.

## 2. Cadastros (contas, categorias, centros de custo, taxas)

- [ ] 2.1 Criar os models `FinConta`, `FinCategoria`, `FinCentroCusto` e `FinTaxaMeio`, com PDO e
  prepared statements, e verificar com `php -l`.
- [ ] 2.2 Criar `FinanceiroGerencialService` para os cadastros, com estas regras:
  - conta com lançamento só pode ser arquivada;
  - categoria tem no máximo dois níveis, e o filho tem o mesmo tipo do pai;
  - categoria com `chave_sistema` não pode ser desativada;
  - taxa com vigência sem sobreposição por meio;
  - auditoria em toda alteração.
  - Verificar com o teste unitário `tests/Unit/financeiro_cadastros.php`.
- [ ] 2.3 Criar controllers, rotas e views de contas (com saldo atual), categorias (árvore, código
  contábil, grupo da DRE), centros de custo e taxas. Incluir esses itens no submenu "Financeiro".
  Verificar no Docker (porta 8010) a criação, edição e arquivamento de cada um.

## 3. Lançamentos manuais

- [ ] 3.1 Criar o model `FinLancamento` e as regras no service:
  - tipo compatível com a categoria;
  - transferência com contas diferentes e sem categoria;
  - valor > 0;
  - competência no formato `AAAA-MM`;
  - anexo privado via `FileStorageService`;
  - exclusão via `TrashService` com justificativa;
  - automático só reclassificável.
  - Verificar com o teste unitário `tests/Unit/financeiro_lancamentos.php`, cobrindo os cenários
    de lançamentos manuais e protegidos da spec.
- [ ] 3.2 Criar a tela `/admin/financeiro/lancamentos`:
  - filtros (período por caixa ou competência, tipo, conta, categoria, centro de custo,
    curso/turma, origem);
  - totais de receitas, despesas e resultado, e a soma por categoria;
  - formulário de criação e edição;
  - rota protegida para o anexo.
  - Verificar no Docker com dados de teste.
- [ ] 3.3 Implementar o saldo de conta (D1) e exibi-lo na lista de contas e no painel. Verificar
  no teste unitário o cenário "Saldo calculado" e a transferência.

## 4. Lançamentos automáticos

- [ ] 4.1 Criar `LancamentoAutomaticoService::sincronizarPedido` conforme D3/D4/D5, com
  idempotência por `chave_idempotencia`.
  - Verificar com o teste unitário `tests/Unit/financeiro_sincronizacao.php`:
    - venda AbacatePay com taxa;
    - pedido com dois itens e cupom (soma exata);
    - PIX manual;
    - execução repetida sem duplicar;
    - reembolso com estorno e taxa mantida.
- [ ] 4.2 Chamar `sincronizarPedido` depois do commit em todos os caminhos de confirmação e
  reembolso listados no design (Context), com falha só em log. Verificar com
  `grep sincronizarPedido app/` e com um teste por caminho no mesmo arquivo.
- [ ] 4.3 No `RepasseProfessorService::registrarPagamento`:
  - exigir uma conta ativa, pré-selecionando a configurada;
  - gravar `pagamentos_professores.conta_id`;
  - lançar a despesa "Repasse a professores" com chave `repasse_pagamento:{id}`.
  - Verificar no teste unitário e na tela de repasses do Docker.
- [ ] 4.4 Mostrar no painel o indicador "pedidos confirmados sem lançamento", com o botão
  "Sincronizar". Verificar no Docker apagando manualmente um lançamento de teste no banco local.

## 5. Importação de vendas antigas

- [ ] 5.1 Criar a tela `/admin/financeiro/importar-vendas` com o POST em lotes de 50 (D8) e o JS
  sem framework, com barra de progresso. Verificar com `node --check` no JS e no Docker:
  importação completa, interrupção e retomada sem duplicar.

## 6. Rateio sobre o líquido (opcional)

- [ ] 6.1 Em `admin/configuracoes-globais/financeiro`, adicionar:
  - as contas padrão (PIX manual, AbacatePay, repasses);
  - a opção "descontar taxas dos meios de pagamento no rateio", com a competência inicial e um
    aviso de que muda o valor pago aos professores.
  - Verificar no Docker.
- [ ] 6.2 No `RateioService::apurarCompetencia`, usar a base líquida de taxas quando a opção está
  ativa para a competência (D6) e recusar se houver pedido não sincronizado.
  - Verificar no teste unitário `tests/Unit/financeiro_rateio.php` os três cenários da spec
    `apuracao-repasses` desta mudança.

## 7. Exportação para o contador

- [ ] 7.1 Criar `GET /admin/financeiro/exportar-contador` (`financeiro.ver`), com CSV em
  streaming nas colunas da spec mais o resumo por categoria, no padrão `;` + BOM.
  - Verificar no Docker: abrir no Excel e conferir acentos, códigos contábeis e soma igual à da
    tela de lançamentos.
- [ ] 7.2 Adicionar ao smoke (`tests/Smoke/rotas.php`, grupo `protegidas`) as rotas GET novas:
  `/admin/financeiro/lancamentos`, `/contas`, `/categorias`, `/centros-custo`, `/taxas`,
  `/importar-vendas` e `/exportar-contador`. Verificar com
  `php tests/Smoke/smoke.php http://localhost:8010`.

## 8. Fechamento

- [ ] 8.1 Rodar `php -l` em todos os PHP alterados (também com PHP 8.4, zero `Deprecated`) e
  `node --check` nos JS.
- [ ] 8.2 Revisar todos os textos novos de interface e mensagens (acentuação PT-BR, conforme
  `docs/padrao-editorial-ptbr.md`).
- [ ] 8.3 Escrever `docs/financeiro-gerencial.md`:
  - como cadastrar contas e taxas;
  - plano de contas padrão e onde pôr o código contábil;
  - como importar vendas antigas;
  - como gerar a exportação mensal para o contador;
  - o que a opção de rateio líquido muda.
- [ ] 8.4 Conferir que os totais de entradas do painel atual e os de receitas de venda dos
  lançamentos batem numa competência de teste. Rodar todos os testes unitários novos e o smoke
  completo no Docker e registrar a saída.

## Workflow follow-up

- Arquivar depois de `financeiro-correcoes`, criando a spec `financeiro-gerencial` e somando os
  requisitos novos a `apuracao-repasses`.
- Próximas fases: contas a pagar e receber; estornos e MED; repasses 2.0; relatórios, DRE e
  fechamento; RPA fiscal; conciliação.

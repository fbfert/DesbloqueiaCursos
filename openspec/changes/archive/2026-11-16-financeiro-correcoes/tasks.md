# Tasks

## 0. Diagnóstico em produção (antes do deploy)

- [ ] 0.1 Rodar em produção uma consulta que procura repasses duplicados:
  `SELECT apuracao_id, usuario_id, COUNT(*) FROM repasses_professores WHERE deleted_at IS NULL GROUP BY apuracao_id, usuario_id HAVING COUNT(*) > 1`.
  Registrar o resultado nesta tarefa. Duplicatas encontradas são tratadas pela lixeira, com
  justificativa.
- [ ] 0.2 Listar os perfis RBAC que têm `pedidos.ver` ou `financeiro.ver` mas não têm
  `pedidos.gerenciar` nem `financeiro.gerenciar`. Combinar com o responsável quais deles recebem
  `pedidos.gerenciar` e registrar a decisão.

## 1. Migration

- [x] 1.1 Criar `sql/084_financeiro_correcoes.sql`, no padrão de cabeçalho das migrations 082/083.
  - Adicionar `repasses_professores.pago_em DATETIME NULL` e `repasses_documentos.motivo_rejeicao
    TEXT NULL`. Cada coluna só é criada se ainda não existir (consulta a `information_schema` +
    `PREPARE`).
  - Preencher `pago_em` dos repasses pagos com `MAX(pagamentos_professores.created_at)`.
  - Verificar: aplicar duas vezes no MariaDB local do Docker, sem erro.

## 2. Repasses: idempotência e estados

- [x] 2.1 Mudar `RepasseProfessor::updateStatus` para receber campos opcionais (`pago_em`,
  `documento_validado_em`) e gravar só os informados. Ajustar todos os chamadores.
  Verificar com `grep updateStatus app/` e `php -l`.
- [x] 2.2 Em `gerarRepassesDaApuracao`, travar a apuração com `SELECT … FOR UPDATE` e recusar se
  já houver repasses não excluídos. Gravar `documento_obrigatorio` = PJ ou `exige_nota_fiscal`.
  Verificar com o teste unitário `tests/Unit/financeiro_repasses.php`: segunda geração recusada,
  sem repasses ou RPAs extras, e PF com "exige nota fiscal" começando em `aguardando_documento`.
- [x] 2.3 Ajustar `anexarDocumento`.
  - Exige documento: o repasse vai para `documento_recebido`.
  - Não exige: só registra o arquivo, sem mudar o status.
  - Verificar no mesmo teste unitário.
- [x] 2.4 Criar `validarDocumento` e `rejeitarDocumento` (motivo obrigatório) no
  `RepasseProfessorService`, com auditoria, conforme a máquina de estados do design (D3).
  Verificar no teste unitário as transições e a recusa sem motivo.
- [x] 2.5 Em `registrarPagamento`:
  - permitir só `aprovado`, ou `pendente` sem documento obrigatório;
  - exigir valor igual ao líquido;
  - gravar `pago_em` e recusar repasse já pago.
  - Verificar no teste unitário: PJ sem validação, valor divergente, pagamento duplo e
    `documento_validado_em` intacto.

## 3. Rotas, telas e arquivos

- [x] 3.1 Criar as rotas `POST /admin/financeiro/documento/validar` e `/documento/rejeitar`, com
  `financeiro.gerenciar`, e as actions no `Admin/FinanceiroController`. Verificar com `php -l` e
  manualmente no Docker (porta 8010).
- [x] 3.2 Criar as rotas `GET /admin/financeiro/documento/arquivo` e
  `/admin/financeiro/pagamento/comprovante` (`financeiro.ver`), que entregam o arquivo pelo ID
  (D4). Verificar no Docker que o arquivo abre e que um ID inexistente devolve 404.
- [x] 3.3 Criar a rota `GET /professor/financeiro/rpa` (`financeiro.professor.ver`), que entrega
  só RPA do próprio professor e responde 403 com auditoria nos demais casos. Verificar no Docker
  com dois professores.
- [x] 3.4 Atualizar `admin/financeiro/repasses.php`.
  - Links "Ver documento" e "Ver comprovante".
  - Botões "Validar" e "Rejeitar", este com campo de motivo.
  - Formulário de pagamento só para repasse liberado, com valor preenchido e travado no líquido.
  - Coluna "Pago em".
  - Verificar no Docker.
- [x] 3.5 Atualizar `professor/financeiro/index.php` com o link de download do espelho de RPA.
  Verificar no Docker.
- [x] 3.6 Adicionar ao smoke (`tests/Smoke/rotas.php`, grupo `protegidas`) as rotas GET novas
  (`/admin/financeiro/documento/arquivo`, `/admin/financeiro/pagamento/comprovante`,
  `/professor/financeiro/rpa`). Verificar com `php tests/Smoke/smoke.php http://localhost:8010`.

## 4. Rateio manual

- [x] 4.1 Em `RateioService::salvar` e `excluir`, recusar quando a apuração tem repasses e
  recalcular `valor_rateio_total` e `valor_retenido_total` da apuração com `updateSummary`.
  Verificar no teste unitário `tests/Unit/financeiro_rateio.php`.

## 5. Estorno, contestação e entradas

- [x] 5.1 Criar `PedidoService::registrarReembolsoGateway`, que muda o status para
  `reembolsado`, grava histórico e auditoria e não mexe nas inscrições. Chamá-lo de
  `AbacatePayService` no `checkout.refunded` novo. No `checkout.disputed`, gravar só a observação
  no histórico.
  - Verificar com o teste unitário `tests/Unit/abacatepay_estorno.php`: payload assinado de
    reembolso, evento repetido sem efeito e contestação mantendo `pago`.
- [x] 5.2 Criar `FinanceiroService::listarPendenciasEstorno` (D5) e o bloco "Pendências de
  estorno e contestação" em `admin/financeiro/index.php`. Verificar no Docker com um pedido
  reembolsado de competência já apurada.
- [x] 5.3 Mudar a exportação CSV para trazer todos os pedidos do filtro (D7).
  Verificar no Docker com mais de 20 pedidos confirmados: contar as linhas do arquivo.

## 6. Permissões

- [x] 6.1 Em `FinanceiroService`, fazer `podeGerirFinanceiro` exigir `financeiro.gerenciar` e
  criar `podeVerFinanceiro`. Em `ComprovantePixService`, aprovar e reprovar passam a exigir
  `pedidos.gerenciar` ou `financeiro.gerenciar`.
  - Verificar com o teste unitário `tests/Unit/financeiro_permissoes.php`: usuário só de leitura
    é negado e a negativa é auditada.

## 7. Fechamento

- [x] 7.1 Rodar `php -l` em todos os PHP alterados e `node --check` se algum JS mudar. Rodar o
  lint também com PHP 8.4 e confirmar zero `Deprecated`.
- [x] 7.2 Revisar os textos de interface e as mensagens novas (acentuação PT-BR, conforme
  `docs/padrao-editorial-ptbr.md`).
- [x] 7.3 Documentar em `docs/` (nota da mudança):
  - fluxo de documento e pagamento;
  - limitação histórica de `documento_validado_em`;
  - passos de deploy (0.1, 0.2, migration 084).
- [x] 7.4 Rodar todos os testes unitários novos e o smoke completo no Docker e registrar a saída.

## Workflow follow-up

- Arquivar depois do deploy, criando as specs `apuracao-repasses` e `entradas-financeiras`.

# Design

## Context

O fluxo atual vai de pedido confirmado a entradas, depois apuração (`RateioService::apurarCompetencia`),
repasses (`RepasseProfessorService::gerarRepassesDaApuracao`), documento e pagamento. Os defeitos
corrigidos aqui foram confirmados na leitura do código em 16/11/2026:

- `RepasseProfessorService.php:48`: a geração não verifica repasses existentes.
  `RepasseProfessor::findByApuracaoAndUsuario` (model L96) nunca é chamado.
- `RepasseProfessorService.php:243` chama `updateStatus($id, 'pago', now)`, e o model grava o
  terceiro argumento em `documento_validado_em` (L121-135).
- `anexarDocumento` (L159) leva PF direto a "aprovado". Nada leva documento a "validado"/"rejeitado"
  nem repasse PJ a "aprovado". `registrarPagamento` não confere status nem valor.
- `AbacatePayService.php:451`: `checkout.refunded`/`checkout.disputed` só gravam a transação.
- `FinanceiroService.php:1127` e `ComprovantePixService.php:380`: `podeGerirFinanceiro` aceita
  permissões de leitura.
- `RateioService::salvar` (L306) não chama `ApuracaoMensal::updateSummary` e não verifica repasses.
- `FinanceiroService::exportarEntradasCsv` (L184) reaproveita `listarUltimosPedidosConfirmados(…, 20)`.

## Goals / Non-Goals

**Goals:** corrigir os defeitos acima sem mudar o modelo de dados além de duas colunas, deixando a
base confiável para `financeiro-gerencial-base`.

**Non-Goals:** redesenhar telas, mudar a regra de cálculo do rateio, ajustar repasse
automaticamente após estorno, aceitar pagamento parcial.

## Decisions

### D1. Idempotência por trava na apuração, não por chave única
`gerarRepassesDaApuracao` abre a transação com `SELECT … FROM apuracoes_mensais WHERE id = ? FOR
UPDATE`. Em seguida conta os repasses não excluídos da apuração. Se houver algum, recusa.

- *Alternativa descartada:* `UNIQUE(apuracao_id, usuario_id)` em `repasses_professores`.
  - A tabela usa `deleted_at`. Um repasse excluído logicamente impediria regerar.
  - Se já houver duplicatas em produção, a migration falharia.
- A trava de linha resolve a concorrência e funciona no MySQL 5.7/MariaDB 10.5 (InnoDB).

### D2. Coluna `pago_em` e `updateStatus` sem efeito colateral
- A migration 084 adiciona `repasses_professores.pago_em DATETIME NULL` e preenche com
  `MAX(pagamentos_professores.created_at)` dos repasses já pagos.
- `documento_validado_em` dos repasses pagos não é "consertado". Não dá para distinguir com
  segurança o valor real do valor sobrescrito. Fica registrado em `docs/` como limitação histórica.
- `RepasseProfessor::updateStatus` passa a receber um array de campos opcionais
  (`documento_validado_em`, `pago_em`) e só grava o que vier.

### D3. Máquina de estados do repasse

```
                 exige documento?
 gerar -------------+-------------------------------+
                    | sim                           | não
                    v                               v
          aguardando_documento               pendente --------------+
             |          ^                                           |
     anexar  |          | rejeitar (motivo)                         |
             v          |                                           |
          documento_recebido                                        |
             | validar                                              |
             v                                                      |
          aprovado ------------------ pagar (valor = líquido) ------+--> pago
```

- A exigência de documento vem de `tipo_pessoa = 'pj' OR exige_nota_fiscal = 1`, lida do perfil
  fiscal no momento da geração. O resultado é gravado em `documento_obrigatorio`.
- Para repasse sem documento obrigatório, anexar um documento só registra o arquivo e não muda o
  status.
- Ao validar, o documento vai para `validado` (`validado_por_usuario_id`, `validado_em`) e o
  repasse para `aprovado` (`documento_validado_em`).
- Ao rejeitar, o documento vai para `rejeitado` (`motivo_rejeicao`, coluna nova na 084) e o
  repasse volta para `aguardando_documento`.

### D4. Arquivos por rota protegida
Rotas GET novas leem o caminho relativo do banco e entregam o arquivo de `storage/private_uploads`
com `Content-Disposition`, como já faz o comprovante PIX (`/admin/pedidos/comprovante`).

- O caminho é sempre resolvido pelo ID do registro, nunca por parâmetro de caminho.
- A rota do professor confere se o `usuario_id` do repasse é o usuário logado. Se não for, devolve
  403 e grava auditoria via `registrarAcessoNegado`.

### D5. Estorno e contestação
`AbacatePayService::handleWebhook` continua gravando a transação, que já é idempotente por
(gateway, event_id).

- **Reembolso:** quando o evento é novo, chama um método novo
  `PedidoService::registrarReembolsoGateway($pedidoId, $origem)`. O método:
  - muda o status para `reembolsado` por `registrarStatus`;
  - grava o histórico;
  - não mexe em inscrições;
  - registra auditoria.
- **Contestação:** só grava no histórico de status uma observação com o status atual (o status
  não muda).
- **Pendências do painel:** `FinanceiroService::listarPendenciasEstorno()` é uma consulta
  derivada, sem tabela nova. Ela lista:
  - pedidos `reembolsado` com inscrição `ativa`;
  - pedidos cuja última transação de gateway é `DISPUTED`;
  - pedidos reembolsados cuja competência de confirmação já tem apuração com repasses ("ajuste de
    repasse pendente").
- *Alternativa descartada:* tabela de pendências. Seria estado duplicado, e a consulta resolve.

### D6. Permissões
- `FinanceiroService::podeGerirFinanceiro` passa a usar `financeiro.gerenciar`. Um novo
  `podeVerFinanceiro` usa `financeiro.ver`.
- `ComprovantePixService` separa a leitura (`pedidos.ver`/`financeiro.ver`) da gestão
  (`pedidos.gerenciar`/`financeiro.gerenciar`) para aprovar e reprovar.

### D7. Exportação
`exportarEntradasCsv` usa `listarUltimosPedidosConfirmados` sem `LIMIT` (parâmetro `null`). O
separador `;` e o BOM UTF-8 continuam.

- *Decisão na implementação:* o CSV continua montado em memória, sem streaming. `App\Core\Response`
  não tem suporte a resposta em fluxo, e o volume atual (centenas de pedidos por período) cabe com
  folga na memória. Mudar o núcleo da resposta só para isso seria excesso.

## Risks / Trade-offs

- [Perfis que hoje aprovam PIX só com leitura perdem a ação] → listar esses perfis antes do deploy
  e conceder `pedidos.gerenciar`. Está nas tarefas.
- [Duplicatas já existentes em produção] → consulta de diagnóstico antes do deploy. Duplicatas
  encontradas são tratadas manualmente pela lixeira, com justificativa.
- [Exigir `valor = líquido` impede ajustes legítimos] → até a mudança de repasses com pagamento
  parcial, a divergência é resolvida corrigindo o rateio antes de gerar os repasses.
- [`documento_validado_em` histórico corrompido] → limitação documentada. Nenhum relatório depende
  dele hoje.

## Migration Plan

1. Rodar em produção as consultas de diagnóstico: duplicatas de repasse e perfis com permissão de
   aprovar PIX.
2. Aplicar `sql/084_financeiro_correcoes.sql` (aditiva, idempotente com verificação em
   `information_schema`).
3. Fazer o deploy do código.
4. **Rollback:** reverter o código. As colunas `pago_em` e `motivo_rejeicao` são ignoradas pelo
   código antigo.

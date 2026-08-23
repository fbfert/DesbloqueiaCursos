# Norminha IA V1 — índice e estado

Documentação do projeto da assistente acadêmica. O plano mestre completo está fora do repositório
(`Norminha_IA_V1_Plano_Mestre_v2.md`); aqui ficam a auditoria, as decisões e o que já foi feito.

**Antes de escrever qualquer código, leia [`CONTEXTO-EXECUCAO.md`](CONTEXTO-EXECUCAO.md).**
Ele carrega as invariantes de segurança e onze correções factuais ao plano — seguir o plano ao pé
da letra em alguns pontos produz defeito.

## Documentos

| Arquivo | O que é |
|---|---|
| [`ONDA-0-ENTREGA.md`](ONDA-0-ENTREGA.md) | Relatório de entrega da Onda 0: o que foi feito e o que decidir |
| [`CONTEXTO-EXECUCAO.md`](CONTEXTO-EXECUCAO.md) | Lido por todas as etapas. Invariantes, decisões da auditoria e correções ao plano |
| [`00-auditoria-preflight.md`](00-auditoria-preflight.md) | Relatório completo dos 12 itens da Etapa 0, com evidência |
| [`AMBIENTE-DEV.md`](AMBIENTE-DEV.md) | Como trabalhar sem tocar na produção |
| [`08-telemetria.md`](08-telemetria.md) | O painel do Checkpoint 0: como ler e o que decidir |
| [`17-deploy-onda-0.md`](17-deploy-onda-0.md) | Roteiro de deploy e rollback da Onda 0 |
| [`../../tests/Smoke/README.md`](../../tests/Smoke/README.md) | A rede de regressão: uso, baseline, guarda de layout |

## Estado

| Etapa | Situação |
|---|---|
| 0 — Auditoria pré-flight | **concluída** |
| 0.5 — Rede de segurança (smoke tests) | **concluída** — 34 rotas, baseline gravado |
| Condições da seção 14.1 | **atendidas** — branch, backup e ambiente de dev |
| 1 — Migration e Models da conversa | **concluída** |
| 2 — `NorminhaContextService` | **concluída** |
| 3 — `NorminhaToolsService` | **concluída** |
| 4 — Orquestrador determinístico | **concluída** |
| 5 — API, `auth.api` e rate limit | **concluída** |
| 6 — Interface de chat e montagem na V2 | **concluída** |
| 7 — Contexto do LMS no frontend | **concluída** |
| 8 — Telemetria e painel | **concluída** |
| Deploy da Onda 0 | roteiro pronto — ver `17-deploy-onda-0.md` |
| **CP0 — Checkpoint: piloto sem IA** | **próximo — decisão sua, com dados reais** |
| Onda 1 (9 a 14) e Onda 2 (15 a 19) | pendentes |

## As três correções que mais importam

**C1 — `ProgressoService::resumoAluno()` retorna 0% para todos.** O Prompt 3 do plano manda usá-lo
como fonte de progresso. Ele lê o modelo legado de `aulas`, vazio desde a migração para o Conteúdo
Unificado: em 12 inscrições reais, 11 divergiram da tela. Implementado ao pé da letra, o botão "Ver
meu progresso" diria "você concluiu 0%" para quem terminou o curso. Use
`inscricoes.percentual_progresso`, que é o que a tela lê.

**C3 — Nenhum middleware devolve JSON.** Todos redirecionam 302. Um `fetch()` que recebe 302 e
depois 200 com o HTML do login interpreta como sucesso. A API da Norminha precisa de guard próprio.

**C5 — A Norminha está desligada** (`tutor_ativo=0`) e não renderiza em nenhuma página. Não há
baseline de uso, e o risco da Etapa 6 é menor do que o plano supõe.

## Pendências fora do código

Encontradas na auditoria, dependem de decisão ou acesso que o código não tem:

1. **Rotacionar credenciais** — `DB_PASSWORD`, `ABACATEPAY_API_KEY`, `ABACATEPAY_WEBHOOK_SECRET`.
   Ficaram expostas em backup público por tempo indeterminado.
2. **Mover `backups/` para fora do docroot.**
3. **Revogar `DEEPSEEK_API_KEY`** — chave viva no `.env`, sem nenhum consumidor no código.
4. **Verificar os logs do Apache** — houve download dos caminhos que vazaram?
5. **Avaliar comunicação à ANPD** — dado pessoal e financeiro esteve exposto.
6. **Revogar `GRANT ALL PRIVILEGES ON *.*`** de `desbloqueia_user` (hardening, Etapa 15).
7. **Corrigir `/v2/como-funciona-a-sala-virtual`** — rota viva e no sitemap, página excluída.
8. **Antes da Onda 1:** hard cap de gasto na conta da OpenAI e revisão da política de privacidade.

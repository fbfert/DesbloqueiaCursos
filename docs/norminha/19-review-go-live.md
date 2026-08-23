# Revisão independente pré go-live — Norminha V1

Data: 23/08/2026 · Branch `feat/norminha-v1` · Revisão conduzida como se fosse por terceiro

**Execução completa:** `318 casos · 318 passou · 0 falhou · 2.9s` · Smoke: `34 verificações · 34 PASS · 0 FAIL · 1 aviso(s) · 0.3s`

---

## 1. Definition of Done

### Onda 0

| Requisito | Situação |
|---|---|
| Suíte `tests/Smoke/` existe, roda nos dois modos, tem baseline e **falha de verdade** | **PASS** — provado com domínio inexistente e 4 modos de falha |
| Trabalho em branch dedicada, nunca em `frontend-v4` | **PASS** — worktree próprio |
| Backup antes da primeira migration | **PASS** — banco 11 MB / 121 tabelas + arquivos 184 MB, verificados |
| Migration aditiva, MySQL 5.7, numeração conferida | **PASS** — 073 a 076, todas idempotentes |
| Chat exige auth e CSRF; nenhuma rota confia em `usuario_id` do body | **PASS** — `auth.api`, teste HTTP |
| Ownership validado server-side | **PASS** — 7 cenários de IDOR |
| Progresso e certificado reutilizam serviços; `recalcularInscricao` nunca chamado | **PASS** — varredura + contador |
| Retomada declara `origem` e o texto corresponde | **PASS** — 3 casos reais |
| Nenhum fast-path chama IA | **PASS** — contador em zero |
| Perguntas livres registradas como `unresolved`, sem PII | **PASS** |
| Painel de telemetria protegido por permissão | **PASS** — 200/403/302 |
| Widget aparece 1× por página | **PASS** — guarda de layout |
| Nenhuma rota vaza erro de PHP no corpo | **PASS** |
| `norminha_tutor_minimized_v1` preservada | **PASS** |
| Rate limit ativo e testado | **PASS** — corte exato na 21ª |

### Onda 1

| Requisito | Situação |
|---|---|
| Hard cap de gasto ativo no painel da OpenAI | **NÃO ATENDIDO** — pendente com o responsável |
| Política de privacidade reflete o envio a operador externo | **NÃO ATENDIDO** — pendente |
| `OpenAIService` usa `/v1/responses`, modelo configurável, chave só no servidor | **PASS** |
| `OPENAI_ENABLED=false` impede qualquer chamada | **PASS** — provado por mutação |
| `tutor_ia_ativo=0` desliga sem derrubar Onda 0 nem LMS | **PASS** |
| KnowledgeService só fornece conteúdo publicado e autorizado | **PASS** |
| Gabaritos não entram no contexto | **PASS** — por estrutura, não por filtro |
| Prompt centralizado; complemento do admin não remove regra | **PASS** |
| Tools estritas, whitelisted, read-only, laço ≤ 3 ciclos | **PASS** |
| Memória em MySQL, janela curta, `store=false` | **PASS** |
| Tokens, latência e status observáveis sem logar segredo | **PASS** |

### Onda 2

| Requisito | Situação |
|---|---|
| Rate limit definitivo testado | **PASS** — três políticas |
| IDOR, XSS, prompt injection, avaliação, indisponibilidade | **PASS** — 18 cenários |
| Smoke comparado ao baseline, sem regressão | **PASS** |
| Documentação de deploy e rollback | **PASS** |
| Integração Claude removida só após auditoria | **PARCIAL** — ver § 4 |
| Tema `v4-claude` não renomeado | **PASS** — 35 arquivos intactos |
| Textos em PT-BR correto | **PASS** |

---

## 2. Defeitos encontrados nesta revisão

Dois, ambos **no próprio instrumento de auditoria**, não no sistema:

1. O script de auditoria dava falso positivo apontando a coluna `util` como tabela, por casar
   `ON DUPLICATE KEY UPDATE` com escrita. Corrigido, e a garantia revalidada por mutação: um
   `UPDATE inscricoes` escondido é detectado.
2. Duas verificações inline falhavam por escape de aspas, não por defeito. Reescritas como teste
   versionado (`norminha_arquitetura.php`), para não dependerem de alguém rodar um comando à mão.

Nenhum defeito novo de produto foi encontrado. Os defeitos reais desta implementação foram achados e
corrigidos durante as etapas — estão registrados nos commits e em `ONDA-0-ENTREGA.md`.

---

## 3. Riscos residuais

| # | Risco | Gravidade | Mitigação |
|---|---|---|---|
| 1 | **Nenhum teste toca o modelo real** | **Alta** | Checkpoint 1 exige revisão manual de amostra. Não automatizável |
| 2 | **Modelo não escolhido** — o padrão do plano não existe mais | **Alta** | `OPENAI_MODEL` vazio; o serviço recusa antes da rede |
| 3 | **Hard cap ausente** | **Alta** | Bloqueia a habilitação, por decisão registrada |
| 4 | Concorrência não testada sob carga real | Média | `ON DUPLICATE KEY UPDATE` é atômico; falta prova sob carga |
| 5 | Sem ambiente de homologação com URL própria | Média | Dev é servidor local, mesma instância MySQL |
| 6 | `desbloqueia_user` tem `ALL PRIVILEGES ON *.*` | Média | Pré-existente; injeção em qualquer ponto alcança o servidor |
| 7 | `AH00124` (laço de redirect) em todas as semanas de log | Média | Pré-existente, sem relação com este projeto |
| 8 | Credenciais expostas no vazamento ainda não rotacionadas | Média | Perícia não achou download externo, mas há janela cega |
| 9 | Sem CI | Baixa | Suíte roda à mão; o runner reduz o risco de erro |
| 10 | `/v2/como-funciona-a-sala-virtual` retorna 404 | Baixa | Defeito conhecido, registrado no smoke |

---

## 4. Uma ressalva sobre a Etapa 18

O plano condiciona a remoção do legado Claude a "a Norminha V1 estar **estável e homologada**".
Isso **não aconteceu** — ela não foi para produção.

A remoção foi feita porque a auditoria confirmou que não havia consumidor ativo e que as variáveis
`ANTHROPIC_*` nunca estiveram no `.env` de produção. Mas está em **commit isolado**, com revert
limpo verificado. Se preferir respeitar a condição do plano à risca, descarte esse commit — nada
mais depende dele.

---

## 5. Checklist de configuração de ambiente

Antes do go-live:

- [ ] Rotacionar `DB_PASSWORD`, `ABACATEPAY_API_KEY`, `ABACATEPAY_WEBHOOK_SECRET`
- [ ] Revogar a `DEEPSEEK_API_KEY` no painel do provedor
- [ ] Avaliar comunicação à ANPD
- [ ] Aplicar migrations 073 → 076, em ordem
- [ ] Confirmar backup novo e verificado
- [ ] `OPENAI_ENABLED=false` e `tutor_ia_ativo=0` no primeiro deploy
- [ ] **Não alterar a permissão do `.env`** — o arquivo é do `root` e o servidor web precisa lê-lo

Antes de ligar a IA:

- [ ] Hard cap de gasto configurado
- [ ] Política de privacidade atualizada
- [ ] Modelo escolhido e testado por `POST /api/openai/teste`

---

## 6. Recomendação

### APTO PARA HOMOLOGAÇÃO — da Onda 0

Justificativa: a Onda 0 não envia dado a lugar nenhum, não tem custo por mensagem, é desligável por
uma linha de SQL com efeito imediato, e o desligamento é provado por teste. O risco está concentrado
na migration (aditiva e idempotente), no layout global (coberto pela guarda de smoke) e nas rotas
novas (cobertas por teste HTTP). Há backup verificado e três níveis de rollback documentados.

### NÃO APTO PARA PILOTO COM IA — ainda

Três bloqueios, nenhum técnico:

1. **Hard cap de gasto** não configurado. O rate limit da aplicação é código novo sem histórico em
   produção; sem o limite do provedor, um defeito vira fatura.
2. **Política de privacidade** não reflete a transferência internacional de dado pessoal.
3. **Modelo não escolhido.**

E uma ressalva de método: a Onda 1 foi construída **antes** do Checkpoint 0, a pedido do responsável
e com a ressalva registrada na época. Se as perguntas não resolvidas se revelarem majoritariamente
de navegação, o caminho certo continua sendo escrever mais atalhos determinísticos — a existência do
código de IA não é, por si, razão para ligá-lo.

**Recomendação prática:** subir a Onda 0, ligar, rodar 10 a 14 dias, ler a lista de perguntas não
resolvidas. Só então decidir sobre a Onda 1, que já está pronta esperando.

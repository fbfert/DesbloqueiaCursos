# Deploy da Onda 0 — roteiro

Alinhado a `docs/deploy.md`, `docs/go-live-checklist.md` e `docs/rollback.md`.
Cobre **apenas a Onda 0**: nenhuma chamada a provedor de IA, nenhuma chave de API, custo zero por
mensagem. O deploy da Onda 1 é outro documento, com pré-requisitos próprios.

## A ideia que organiza tudo: subir ≠ ligar

O código pode ir para produção com a Norminha **desligada**, e nesse estado nada muda para o aluno.
Ligar é um `UPDATE` de uma linha, depois, quando você quiser, e reversível no mesmo segundo.

Separar as duas coisas é o que transforma um deploy arriscado em dois passos pequenos. **Não junte.**

---

## 1. Pré-requisitos que não são código

Estes vêm do relatório da Etapa 0 e **não bloqueiam o deploy**, mas nenhum deveria seguir aberto:

- [ ] **Rotacionar `DB_PASSWORD`, `ABACATEPAY_API_KEY` e `ABACATEPAY_WEBHOOK_SECRET`.** Ficaram
      dentro de um backup de 144 MB publicamente baixável por tempo indeterminado.
- [ ] **Mover `backups/` para fora do docroot.** O bloqueio do `.htaccess` resolve o sintoma; o
      diretório continua no lugar errado.
- [ ] **Revogar a `DEEPSEEK_API_KEY`** — chave viva no `.env`, sem nenhum consumidor no código.
- [ ] **Verificar nos logs do Apache** se houve download dos caminhos que vazaram.
- [ ] **Avaliar comunicação à ANPD** — dado pessoal e financeiro esteve exposto.

Decisão de quem responde pelo produto, não do deploy.

---

## 2. Antes de qualquer coisa: backup

```bash
DEST=/home/desbloqueiacursos/backups/pre-onda0-$(date +%Y%m%d-%H%M%S)
mkdir -p "$DEST" && chmod 700 "$DEST"

mysqldump --single-transaction --quick --routines --triggers --events \
  --default-character-set=utf8mb4 desbloqueiacursos | gzip -6 > "$DEST/banco.sql.gz"

tar --exclude='public_html/.git' --exclude='public_html/backups' \
    --exclude='public_html/icon' --exclude='public_html/tmp' \
    -czf "$DEST/arquivos.tar.gz" -C /home/desbloqueiacursos public_html

chmod 600 "$DEST"/*.gz
gunzip -t "$DEST/banco.sql.gz" && gunzip -t "$DEST/arquivos.tar.gz" && echo "backups integros"
zcat "$DEST/banco.sql.gz" | tail -2   # precisa terminar em "Dump completed"
```

Um backup que não foi verificado não é backup. Os dois `gunzip -t` e o `tail` são o que separa as
duas coisas.

Referência do backup anterior a este projeto: `backups/pre-norminha-20260822-202256/`, e a tag
`pre-norminha-20260822` no git.

---

## 3. Migrations

**Duas, nesta ordem.** Ambas aditivas e idempotentes — nenhuma altera tabela existente do LMS.

| Arquivo | O que faz |
|---|---|
| `sql/073_norminha_conversas.sql` | cria `norminha_conversas`, `norminha_mensagens`, `norminha_feedback`, `norminha_uso` |
| `sql/074_norminha_telemetria.sql` | `bloqueios_dia` em `norminha_uso` + três índices por data |

```bash
mysql -u <usuario> -p desbloqueiacursos < sql/073_norminha_conversas.sql
mysql -u <usuario> -p desbloqueiacursos < sql/074_norminha_telemetria.sql
```

Conferência:

```sql
SELECT table_name FROM information_schema.tables
 WHERE table_schema = DATABASE() AND table_name LIKE 'norminha%';        -- 4 linhas

SELECT COUNT(*) FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'norminha_uso'
   AND column_name = 'bloqueios_dia';                                     -- 1
```

Ambas podem ser reaplicadas sem efeito (`CREATE TABLE IF NOT EXISTS`, e a 074 checa
`information_schema` antes de cada `ALTER`). Se uma falhar no meio, corrija e rode de novo.

**A migration não muda o comportamento do site.** Tabelas vazias, ninguém as lê enquanto a Norminha
estiver desligada.

---

## 4. Código

Produção é um git worktree em `frontend-v4`, e o merge foi verificado sem conflito.

```bash
cd /home/desbloqueiacursos/public_html
git status --short                       # PRECISA estar vazio
git merge feat/norminha-v1
```

Se o working tree não estiver limpo, **pare**: alguém editou produção direto de novo, e o merge
sobrescreveria esse trabalho. Commit ou stash antes.

### O que muda

**25 arquivos novos** (services, models, controller, middleware, testes, docs) — risco baixo: não
existem em produção hoje.

**17 arquivos existentes.** Os que merecem atenção:

| Arquivo | Mudança | Por que é seguro |
|---|---|---|
| `resources/views/layout.php` | +39 −60 | o mais arriscado. Corrige o cache-busting e extrai o script inline para um parcial. A guarda de layout do smoke cobre |
| `resources/views/v2/layout.php` | +56 | monta a Norminha na V2, dentro de `try/catch` — falha vira componente ausente, nunca página quebrada |
| `app/Middleware/CsrfMiddleware.php` | +12 | retorno antecipado **só** para caminhos `/api/`. O fluxo web segue idêntico |
| `app/Core/Router.php` | +4 | uma entrada no mapa de aliases. Puramente aditivo |
| `routes/api.php`, `routes/web.php` | +23 | rotas novas; nenhuma existente é tocada |
| 4 controllers V2 | +~60 | acrescentam uma chave ao array da view. Nada removido |

---

## 5. Verificação — com a Norminha ainda desligada

```bash
php tests/Smoke/smoke.php https://desbloqueiacursos.com.br --modo=anonimo
```

Esperado: **34 PASS, exit 0**, com o aviso do defeito conhecido de
`/v2/como-funciona-a-sala-virtual`. A guarda de layout confirma que o componente não aparece mais de
uma vez e que nenhuma rota vaza erro de PHP.

Compare com o baseline (`tests/Smoke/baseline.json`). **Não regrave o baseline agora** — ele é a
referência do estado anterior.

Manualmente, porque o smoke não faz login:

- [ ] entrar como aluno e abrir `/v2/aluno/` — **sem** ícone da Norminha (ela está desligada)
- [ ] abrir uma aula, um quiz e uma atividade
- [ ] concluir um item e ver o progresso mudar
- [ ] catálogo, curso, checkout até a tela de pagamento
- [ ] admin: `/admin/tutor-norminha` continua abrindo

Se qualquer um falhar, vá para o rollback (seção 8) antes de investigar.

---

## 6. Ligar a Norminha

Passo separado, dias depois se preferir.

```sql
UPDATE tutor_configuracoes SET valor = '1' WHERE chave = 'tutor_ativo';
```

Ou pelo admin, em `/admin/tutor-norminha/configuracoes`.

Verificação imediata:

- [ ] `/v2/aluno/` mostra o launcher da Norminha
- [ ] abrir o chat e clicar **Ver meu progresso** — o número tem de bater com o da tela do curso
- [ ] **Continuar de onde parei** leva à aula certa
- [ ] **Meu certificado** diz o mesmo que a área de certificados
- [ ] escrever uma pergunta livre — resposta honesta de indisponibilidade, sem inventar
- [ ] abrir uma aula: aparece **Tirar dúvida desta aula**; na área geral, não aparece
- [ ] `/admin/tutor-norminha/telemetria` já registra a conversa

> O ponto 2 é o mais importante. Se o progresso divergir da tela, **desligue** e investigue: a
> Norminha discordar do LMS é pior do que não existir.

### Rollout gradual

Não há flag por aluno na Onda 0 — `tutor_ativo` é global. Se quiser começar pequeno, use os toggles
de contexto (`tutor_area_aluno`, `tutor_cursos`, `tutor_home`, `tutor_checkout`) para liberar só a
área do aluno, que é onde a Norminha serve para alguma coisa.

---

## 7. Primeiras 48 horas

```bash
grep -c '"norminha' storage/logs/app-$(date +%Y-%m-%d).log
grep '"level":"error"' storage/logs/app-$(date +%Y-%m-%d).log | grep norminha | tail -20
grep 'norminha.ratelimit.bloqueado' storage/logs/app-$(date +%Y-%m-%d).log | wc -l
```

E o painel: `/admin/tutor-norminha/telemetria`.

| Sinal | O que significa | O que fazer |
|---|---|---|
| `norminha.chat.excecao` no log | erro não previsto | investigar; desligar se for recorrente |
| bloqueios espalhados por vários alunos | limite apertado demais | subir `tutor_ia_limite_5min` |
| progresso divergindo da tela | o pior caso | **desligar imediatamente** |
| ninguém abrindo o chat | problema de descoberta, não de qualidade | mexer em posição e rótulo |
| latência do site subindo | improvável (o contexto custa 1–2 queries) | comparar com o baseline do smoke |

---

## 8. Rollback — três níveis, do mais barato ao mais caro

**Nível 1 — desligar (segundos, sem deploy).**

```sql
UPDATE tutor_configuracoes SET valor = '0' WHERE chave = 'tutor_ativo';
```

Resolve qualquer problema de comportamento da Norminha. O componente some de todas as rotas, nenhum
CSS ou JS dela é servido, e o LMS não sente. **Provado por teste**
(`tests/Unit/norminha_kill_switch.php`): com `tutor_ativo=0` nenhuma rota monta o componente, nada
lança exceção, `AreaCursoService` e `LmsElegibilidadeService` seguem respondendo, e as tabelas
permanecem intactas — desligar é configuração, não destruição, e religar não perde histórico.

**Nível 2 — voltar o código (minutos).**

```bash
cd /home/desbloqueiacursos/public_html
git log --oneline -3
git revert -m 1 <hash-do-merge>        # preserva histórico
# ou, se o merge foi o último commit e nada veio depois:
git reset --hard 5c4df81
```

As tabelas `norminha_*` podem ficar: são inertes sem o código. **Não as apague** — se você voltar a
subir, o histórico de conversas ainda estará lá.

**Nível 3 — restaurar o backup (última instância).**

```bash
zcat "$DEST/banco.sql.gz" | mysql -u <usuario> -p desbloqueiacursos
```

Só se algo além da Norminha tiver sido afetado. Perde tudo que aconteceu no portal desde o backup —
pedidos, progresso, matrículas. Por isso é o último recurso, e por isso o nível 1 existe.

---

## 9. Checkpoint 0

Depois de 10 a 14 dias com a Norminha ligada, o painel responde três das cinco perguntas do plano:

1. Quantos alunos abriram? Quantas mensagens por aluno ativo? → painel
2. Proporção `php` × `unresolved`? → painel
3. As não resolvidas são de **conteúdo** ou de **navegação**? → **leitura manual da lista**
4. Volume estimado de mensagens/mês que iriam para IA? → extrapolação sua
5. Esse volume cabe no custo por aluno, ao preço vigente? → decisão sua

Como ler cada número está em [`08-telemetria.md`](08-telemetria.md).

**Se a resposta 3 for "navegação", a decisão certa é escrever mais fast-paths, não ligar a IA.**
Mais barato, mais rápido e sem risco de resposta inventada.

---

## 10. O que este deploy NÃO faz

- Não envia dado nenhum para fora do servidor. Não há provedor de IA na Onda 0.
- Não altera nenhuma tabela existente do LMS.
- Não toca no legado Claude (`ClaudeService`, `/api/claude/teste`, bloco `claude` em `config/ai.php`).
  A limpeza é a Etapa 18, depois da estabilização.
- Não renomeia o tema `v4-claude` — identificador de frontend, sem relação com provedor de IA.
- Não cria permissão nova: a telemetria reutiliza `conteudo.ver`.

## Pré-requisitos da Onda 1 (não desta entrega)

Quando e se o Checkpoint 0 apontar para a IA, dois itens **precedem** qualquer código:

- **Hard cap de gasto** na conta da OpenAI, no painel do provedor. O rate limit da aplicação é
  código novo sem histórico em produção; o limite do provedor é a única coisa entre um bug e a fatura.
- **Política de privacidade** do portal (`/v2/politica-de-privacidade`) refletindo o envio de
  contexto de aluno a operador estrangeiro. É tratamento de dado pessoal.

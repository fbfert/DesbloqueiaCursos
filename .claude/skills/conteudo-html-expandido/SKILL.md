---
name: conteudo-html-expandido
description: Converte itens de conteúdo tipo=texto de um curso para tipo=html expandido (mais profundidade, exemplos) com cards, ícones SVG, mapas mentais, esquemas de fluxo, linhas do tempo, comparações e quadros/tabelas. Use quando o usuário pedir para "melhorar/expandir/enriquecer o conteúdo" de um curso, "transformar em HTML" ou "aplicar o mesmo padrão visual" usado nos cursos 12 e 13.
---

# Conversão de conteúdo texto → HTML expandido

Processo testado e validado nos cursos `curso_id=12` (19 itens, feito manualmente) e `curso_id=13`
(108 itens, feito via Workflow com 108 agentes em paralelo). Este arquivo é a receita completa —
siga na ordem. Os arquivos irmãos (`design-system.css`, `icons.php`, `apply-conversao.php`) são a
fonte de verdade do visual e do script de gravação; não reescreva-os do zero a cada curso.

## Quando usar

- Pedido explícito para melhorar/expandir/enriquecer o conteúdo de um curso.
- Pedido para "transformar em HTML" um curso que hoje usa `tipo=texto`.
- Pedido para aplicar "o mesmo padrão" usado nos cursos 12/13.

## Quando NÃO usar

- Cursos jurídicos/regulados onde o conteúdo precisa de disclaimer legal explícito
  (`alerta-educacional`) — use esse componente, mas não invente conteúdo jurídico específico.
  Peça revisão humana do teor jurídico antes de publicar.
- Itens que já são `tipo=html` (nada a fazer) ou tipos diferentes de `texto` (`arquivo`, `link`,
  `video`, `quiz`, `avaliacao_textual` — fora do escopo desta skill).

## Pré-requisito já resolvido (não precisa refazer)

`App\Support\HtmlSanitizer` já permite `<div>` e as classes `card-pratica`/`card-resumo`/
`alerta-educacional` nos perfis `basic`/`full` (usado pelo tipo `texto`). **Isso não afeta o tipo
`html`**: conteúdo `tipo=html` vai para `conteudo_htmls` e é renderizado num `<iframe sandbox>`
via `App\Support\HtmlEmbedRenderer::wrap()`, **sem passar pelo sanitizador** — por isso cada
página pode ter `<style>` próprio livremente. Não precisa mexer no sanitizador para esta skill.

## Passo 1 — Descobrir o escopo

```sql
SELECT ci.id, ci.titulo, m.titulo AS modulo_titulo
FROM conteudo_itens ci
LEFT JOIN conteudo_modulos m ON m.id = ci.modulo_id
WHERE ci.curso_evento_id = :curso_id AND ci.tipo = 'texto' AND ci.deleted_at IS NULL
ORDER BY m.ordem, ci.ordem;
```

Conte quantos itens vieram. **Isso decide a estratégia do Passo 2** — avise o usuário do volume
antes de começar se for grande (dezenas+ de itens), não assuma.

## Passo 2 — Escolher a estratégia de geração

- **Poucos itens (até ~20-25):** gere o conteúdo você mesmo, item por item, direto em PHP
  (heredocs), como foi feito no curso 12. Mais simples de revisar, não precisa de aprovação extra.
- **Muitos itens (dezenas+):** use o `Workflow` tool com um agente por item em paralelo — só se o
  usuário **explicitamente autorizar orquestração multi-agente** (ou já estiver em modo
  "ultracode"). Se não tiver certeza, pergunte antes de disparar dezenas de agentes. Foi assim que
  o curso 13 (108 itens) foi feito.

### Se for usar Workflow: armadilhas conhecidas

1. **`args` pode chegar como STRING JSON, não como objeto**, mesmo passando um objeto de verdade
   no parâmetro `args` da tool call. Sempre faça isso no início do script, antes de usar `args`:
   ```js
   let argsResolvidos = args
   if (typeof argsResolvidos === 'string') { argsResolvidos = JSON.parse(argsResolvidos) }
   const itens = argsResolvidos.itens
   ```
2. **Não** tente passar o conteúdo `texto` original de cada item via `args` (fica gigante). Em vez
   disso, instrua cada agente a buscar sozinho o conteúdo atual daquele item específico via Bash:
   ```
   php -r 'define("BASE_PATH","/home/desbloqueiacursos/public_html"); require BASE_PATH."/app/Core/Autoloader.php"; \App\Core\Autoloader::register(BASE_PATH); \App\Core\Env::load(BASE_PATH."/.env"); $s=App\Core\Database::connection()->prepare("SELECT conteudo FROM conteudo_textos WHERE item_id=:id"); $s->execute(["id"=>ITEM_ID]); echo $s->fetchColumn();'
   ```
   Isso é só leitura — deixe explícito no prompt do agente que ele **não deve escrever no banco**.
   A gravação real acontece centralizada, no Passo 4, fora do Workflow.
3. Cada agente deve devolver um **schema estruturado** `{icone, corpo_html}` (mais `item_id` que
   você já sabe de antemão) — não texto livre. Isso evita ter que fazer parsing frágil depois.
4. Depois que o workflow terminar, o resultado completo fica no arquivo apontado por
   `<output-file>` da notificação — pode passar de 1MB para uma centena de itens. Leia/valide via
   script PHP (não tente colar tudo na conversa).
5. Enquanto o workflow roda, **não fique repolling em loop curto**. Confira progresso via
   `grep -c '"type":"result"' <transcript-dir>/journal.jsonl` de vez em quando, mas o sinal
   principal é a notificação de conclusão. Use `ScheduleWakeup` com um fallback de 15-25min, não
   segundos.

## Passo 3 — Instruções de conteúdo para cada agente/geração (design brief)

Use este texto (adaptado ao tema do curso) como instrução para quem gerar o conteúdo — você mesmo
ou um subagente:

> Você está escrevendo o CORPO de uma aula. Devolva APENAS o HTML do corpo (parágrafos, seções,
> componentes visuais) — NÃO inclua `<html>`, `<head>`, `<body>`, nem repita o título da aula como
> heading (ele já aparece separado, no cabeçalho da página).
>
> Use `<h3>` para subtítulos, opcionalmente com ícone:
> `<h3><span class="h-icone">{{ICON:nome}}</span>Texto</h3>` — onde `nome` é um dos ícones
> permitidos (ver `icons.php`).
>
> Primeiro leia o conteúdo atual da aula (real, já publicado) para não perder nenhuma informação
> verdadeira que já está lá. EXPANDA de verdade: mais parágrafos explicando o "porquê", mais
> exemplos concretos e realistas. Não invente números/estatísticas específicas, nem cite
> ferramentas/empresas reais como garantidas — fique em conselhos e exemplos genéricos plausíveis.
>
> Escolha de 2 a 4 componentes visuais (catálogo abaixo) que façam sentido para o assunto
> ESPECÍFICO desta aula — nem toda aula pede todos os componentes. SEMPRE termine com um
> `card-resumo`. HTML deve ser bem formado (toda tag aberta fecha). Sem `<script>`, `<iframe>`,
> nem atributos `on*`.

### Catálogo de componentes (classes exatas, ver `design-system.css` para o CSS completo)

| Componente | Quando usar | Estrutura mínima |
|---|---|---|
| `card-resumo` | **Sempre**, ao final de toda aula | `<div class="card-resumo"><h3>...</h3><ul>...</ul></div>` |
| `card-pratica` | Exemplo prático, aplicação concreta | `<div class="card-pratica"><h3>...</h3><p>...</p></div>` |
| `card-dica` | Aviso/dica importante | `<div class="card-dica"><h3>...</h3><p>...</p></div>` |
| `alerta-educacional` | Disclaimer legal/educacional (cursos jurídicos) | `<div class="alerta-educacional"><strong>Aviso:</strong>...</div>` |
| `grade` / `grade-item` | Lista de exemplos, opções, erros (classe extra `erro` deixa vermelho) | `<div class="grade"><div class="grade-item"><strong>..</strong><p>..</p></div></div>` |
| `fluxo` / `fluxo-passo` | Processo sequencial, passo a passo | `<div class="fluxo"><div class="fluxo-passo"><span class="fluxo-num">1</span><strong>..</strong></div></div>` |
| `mapa-mental` | Tema com 3-5 categorias/ramos conceituais | `<div class="mapa-mental"><div class="mapa-central">..</div><div class="mapa-ramos">...</div></div>` |
| `linha-tempo` | Sequência de datas/prazos | `<div class="linha-tempo"><div class="linha-tempo-item"><strong>data</strong><span>..</span></div></div>` |
| `comparacao` | Antes/depois (`ruim`/`bom`) ou 2 opções lado a lado (`bom`/`bom`) | `<div class="comparacao"><div class="comparacao-lado ruim">..</div><div class="comparacao-lado bom">..</div></div>` |
| `checklist` | Lista de conferência | `<ul class="checklist"><li>..</li></ul>` |
| `quadro` | Comparar categorias/definições lado a lado (tabela) | `<div class="quadro"><table><thead>..</thead><tbody>..</tbody></table></div>` |

Ícones disponíveis (ver `icons.php`): `target, flag, search, users, message, building, check,
route, calendar, alert, book, file, edit, sparkles, compass, briefcase, mail, network, mic, star,
trending, shield, clipboard, monitor, clock, award, smile, send`. Se o tema pedir um ícone que não
existe, ou reuse o mais próximo semanticamente, ou desenhe um novo (mesmo estilo: `viewBox="0 0 24
24"`, `stroke="currentColor"`, `stroke-width="1.7"`) e acrescente em `icons.php` para reuso futuro.

## Passo 4 — Validar e gravar no banco

Use `apply-conversao.php` como base (copie para o scratchpad da sessão e ajuste). Ele já faz:

1. Cruza os resultados recebidos contra a lista real de itens `tipo=texto` do curso (detecta item
   faltando ou sobrando).
2. Valida ícone dentro da lista permitida (fallback silencioso para `sparkles` com aviso).
3. Substitui `{{ICON:nome}}` pelo SVG real.
4. Detecta placeholder de ícone malformado/incompleto — **sinal de conteúdo truncado**. Trate como
   erro, não como aviso: regenere aquele item isoladamente (um agente avulso, não o workflow
   inteiro de novo) antes de prosseguir.
5. Valida o HTML final via `DOMDocument` (ignorando falsos-positivos de tag `svg/rect/path/circle/
   line`, que o parser HTML4 legado do libxml não reconhece mas não são erros reais).
6. Bloqueia `<script>`, `<iframe>`, atributos `on*`.
7. Só grava se **todos** os itens passarem — sem gravação parcial. Rode primeiro com
   `DRY_RUN=1 php apply-conversao.php <curso_id> <json>` e só depois sem `DRY_RUN`.
8. Gravação em transação única: `ConteudoHtml::upsertByItemId()`, `UPDATE conteudo_itens SET
   tipo='html'`, `DELETE FROM conteudo_textos WHERE item_id=...` — tudo ou nada.

**Nunca pule a validação para "economizar tempo".** No curso 13, 1 de 108 itens veio com o
conteúdo cortado no meio (resposta do agente truncada) — a validação pegou isso antes de ir pro
banco, e o item foi regenerado isoladamente sem precisar re-rodar os outros 107.

## Passo 5 — Conferência visual antes de reportar como concluído

Renderize pelo menos 1-2 amostras reais (idealmente uma com `mapa-mental` ou `quadro`, que são os
componentes visualmente mais complexos) via Chromium headless, disponível neste ambiente:

```bash
chromium-browser --headless --disable-gpu --no-sandbox \
  --screenshot=/caminho/saida.png --window-size=760,3200 \
  "file:///caminho/pagina.html"
```

Leia o PNG resultante (tool `Read`) antes de reportar sucesso ao usuário. Os erros de dbus no
stderr do Chromium são inofensivos (sem sessão gráfica no sandbox) — ignore-os.

## Passo 6 — Limpeza

Apague os arquivos temporários do scratchpad (JSON de resultados, previews HTML/PNG, scripts
avulsos) depois de confirmado. Os únicos arquivos permanentes desta skill são os desta pasta
(`design-system.css`, `icons.php`, `apply-conversao.php`, `SKILL.md`) — não crie cópias por curso.

## Notas editoriais

- Português brasileiro com acentuação correta (regra obrigatória do projeto).
- Não repita o título da aula como heading solto no corpo (já aparece no cabeçalho da página).
- Não invente fatos específicos: nomes de empresas reais, estatísticas, citações legais precisas.
  Fique em orientação genérica e exemplos plausíveis, deixando claro quando algo é ilustrativo.
- Se o curso tiver conteúdo jurídico/regulado, sinalize ao usuário que o teor de `alerta-
  educacional` deve ser revisado por humano antes de publicar — a skill formata, não valida
  precisão jurídica.

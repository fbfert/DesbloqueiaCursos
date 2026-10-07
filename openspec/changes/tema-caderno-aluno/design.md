# Design

## Context

O tema caderno (arquivado em `openspec/changes/archive/2026-10-06-tema-caderno/`) já tem layout, partials (`topo`, `bnav`, `divisorias`, `recibo`, `carimbo`, `lombada`, `trilha-modulos`, `foto-curso`, `postit`), `caderno.css` (~15,0 KB gz) e `caderno.js` (~10,0 KB gz, `window.Caderno`). `TemaPublico::view($nome)` devolve `caderno/<nome>` só se o arquivo existir — sem o arquivo, a V2 continua respondendo. As cinco páginas do aluno são V2 (`View::render('v2/<x>', …)`), e o comportamento delas está todo no `v2-main.js` (3.549 linhas). O levantamento completo de rotas, chaves de dados, ids, names e endpoints está em `inventario-v2.md` (nesta pasta) (os pontos que são contrato estão repetidos abaixo).

## Goals / Non-Goals

**Goals:** as cinco páginas no tema, com paridade de dados, formulários e endpoints; estudo confortável no celular; JS do aluno isolado da vitrine; nenhuma regra nova.

**Non-Goals:** mexer em Services/Models/rotas; LMS legado; professor/admin; remover a V2; mudar textos de regra (mensagens de erro vêm do servidor).

## Decisions

### 1. Seleção de view e entrega por etapas
Todos os `View::render('v2/<x>', …)` dos cinco controllers (inclusive os de `estado()`) passam a `View::render(TemaPublico::view('<x>'), …)` já na primeira tarefa. Como `caminhoView` cai na V2 quando o arquivo do tema não existe, cada página pode entrar no tema numa tarefa separada sem quebrar as outras. O dado do controller não muda.

### 2. Layout: modo "aluno" e modo "estudo"
O shim de cada página define, além de `$contentView` e `$paginaTema`, `$cadernoAluno = true` (área do aluno, conta, aula, quiz, atividade) e `$cadernoEstudo = true` (aula, quiz, atividade). O layout:
- com `$cadernoAluno`: carrega `/assets/caderno/caderno-aluno.css` e `/assets/caderno/caderno-aluno.js` (defer, depois do `caderno.js`) e `/assets/css/conteudo-html-embed.css`, com versão por `filemtime` como os demais assets do tema;
- com `$cadernoEstudo`: não renderiza a `bnav` geral; a página renderiza a sua barra de estudo (`.barra-estudo`, fixa embaixo só abaixo de 900 px) e o `<body>` recebe `data-estudo`.
Nas demais páginas nada muda (nenhum dos dois arquivos é requisitado). Ruling: CSS do aluno também em arquivo separado (não só o JS), para a vitrine não pagar por ele; o orçamento de CSS (25 KB gz) vale para a soma nas páginas do aluno.

### 3. Linguagem visual por página (reuso dos partials)
- **Área do aluno — "Meus cadernos":** cabeçalho com saudação manuscrita; abas como `divisorias` (links GET `?aba=`, `role="tab"`/`aria-selected` como na V2, com contagem). Cursos: cada um uma lombada/caderno com `foto-curso` (capa colada), nome, turma, trilha de progresso (`Caderno.tracar` na barra) com a porcentagem em texto, `carimbo` "certificado" quando `tem_certificado`, ação "Continuar estudando"/"Revisar curso" → `lms_href`. Pedidos: cada um um `recibo` (código, data, itens, total, situação), "Retomar pagamento" → `resumo_v2_href`, cancelamento em `<details>` com o mesmo form. Certificados: selos com código, data e os três links. Perfil: ficha com dados mascarados + "Editar meus dados". Listas vazias: post-it com o CTA do catálogo.
- **Minha conta — "Ficha cadastral":** a mesma `ficha` do checkout (inputs sobre pauta), erros por campo em texto junto do campo (`aria-describedby`), UF/cidade, senha com `ver-senha`.
- **Aula — "Página do caderno":** desktop ≥ 900 px: sumário à esquerda (árvore de módulos como índice do caderno, módulo atual aberto, ✓ nos concluídos, item atual marcado com `aria-current="page"`, etiquetas como subtítulos sem link) e a página pautada à direita com título, módulo, tipo e o conteúdo; abaixo, anterior/próxima e a conclusão. Celular: o sumário vira `<details class="sumario">` no topo ("Sumário · 3 de 12 concluídos"), e a barra de estudo fixa oferece ← anterior, concluir/✓ e próxima →. A marcação de conclusão desenha o ✓ à caneta (`Caderno.tracar`) após o redirect com sucesso (flash), respeitando movimento reduzido.
- **Quiz — "Folha de prova":** cabeçalho com nome, módulo, tentativa e cronômetro (quando há duração) num "relógio de prova" com o tempo em texto (`role="timer"`); status do salvamento automático em texto discreto ("Salvo às 14:32"). Perguntas como questões numeradas com alternativas em letras (A, B, C…) sobre pauta; discursivas com área de resposta pautada e contador. Resultado: carimbo decorativo "aprovado"/"refazer" (`aria-hidden`) + o resultado completo em texto; gabarito/explicações quando liberados.
- **Atividade — "Folha de resposta":** enunciado e orientações em página pautada, prazo e situação como etiqueta; última entrega com nota em destaque manuscrito, devolutiva como post-it; imagens enviadas como fotos com fita; envio com área de resposta + contador + campo de imagens.

### 4. `caderno-aluno.js` (um arquivo, módulos por página)
Registra-se via `Caderno.pagina(nome, init)` (já existente) e só ativa o que encontra no DOM. Contratos (mesmos ids/names da V2, para os endpoints V1 continuarem iguais):
- **Envio protegido:** botões `[data-loading-label]` trocam o texto, `aria-busy` e desabilitam após o submit (via `setTimeout(0)`, para o valor do botão ir no POST); nenhum `preventDefault` genérico.
- **Aula:** foco no feedback após redirect; form com `data-autoconcluir` → `requestSubmit()` após 150 ms (texto e HTML, como a V2).
- **Conta:** máscara de CPF; cidades por UF via `https://servicodados.ibge.gov.br/api/v1/localidades/estados/<id>/municipios?orderBy=nome` com cache por UF e fallback para um `<input>` de texto se a busca falhar (estado/cidade atuais vêm de `data-*` no form, não de script inline).
- **Atividade:** contador de caracteres, limite de 5 imagens no `change` com mensagem em texto.
- **Quiz:** autosave (radio 600 ms, textarea 2.000 ms, `visibilitychange`/`pagehide` com `keepalive`, JSON `{tentativa_id,item_id,inscricao_id,curso_id,turma_id,respostas,discursivas,_token}` para `POST /aluno/cursos/quiz/rascunho`; `{expirada}` → recarrega; falha re-enfileira e mostra "Não foi possível salvar — tentando de novo"); cronômetro (1 s local, sincroniza a cada 60 s em `POST /aluno/cursos/quiz/tempo` com `_token` no corpo e `X-CSRF-TOKEN`; ≤ 300 s muda o visual e anuncia uma vez em `aria-live`; zerado → POST /tempo, mensagem em texto e recarrega); modo curto (uma pergunta por vez, progresso, "Próxima" exige resposta — aviso em texto, não `alert`); modo prova (navegação livre, índice por blocos, marcar para revisão via `POST /aluno/cursos/quiz/revisao`, revisão com "Sem resposta" e "Marcadas", discursiva vazia bloqueia o envio e abre a revisão, pendentes objetivas pedem confirmação num painel inline "Enviar mesmo assim").
Ruling: nenhum `alert()`/`confirm()` no tema — mensagens em texto e painel inline; o comportamento (bloquear/confirmar) é o mesmo da V2.

### 5. Segurança do conteúdo (inalterada)
Mesmas funções da V2: `Helpers::renderSafeHtml` com os mesmos perfis por tipo; HTML e vídeo incorporado em iframe com `sandbox="allow-scripts allow-popups"` (nunca `allow-same-origin`) e `srcdoc` escapado; `HtmlEmbedRenderer::wrap` para a altura; `arquivo_icone` só como já vem (lista fechada do Helper); gabarito, rubrica e explicações fora do HTML enquanto a tentativa está em andamento (o controller já não manda — a view não pode inventar).

### 6. Fixture local de estudo
`tests/Fixtures/tema_caderno_aluno.sql` (só local, como a da vitrine): um aluno de teste com matrícula num curso com módulos de todos os tipos (texto, HTML, vídeo resolvido, vídeo incorporado, arquivo, link, etiqueta, quiz curto, quiz em modo prova com bloco, discursiva e duração, avaliação textual), um pedido aguardando pagamento e um certificado emitido. Idempotente (apaga e recria pelos próprios ids/códigos marcados). Credenciais de teste registradas no próprio arquivo.

## Risks / Trade-offs
- [Reimplementar o quiz é a parte mais arriscada — perda de resposta] → mesmos endpoints e payloads; verificação manual em navegador headless de autosave, recarga com respostas restauradas, cronômetro e envio; sem JS o formulário continua funcionando.
- [Orçamento de JS] → `caderno-aluno.js` ≤ 15 KB gz (total ≤ 25 KB); medir ao fim de cada tarefa que mexe nele.
- [Duplicação de markup entre V2 e tema] → aceitável até a remoção da V2 (mudança seguinte).
- [Fixture depende do schema real do LMS] → a tarefa da fixture vem primeiro e é verificada abrindo as páginas V2 com ela.

## Migration Plan
Entra junto com o tema (mesma chave). Com `TEMA_PUBLICO=v2` nada muda; prévia do admin por `?tema=caderno`. Rollback: chave em `v2`.

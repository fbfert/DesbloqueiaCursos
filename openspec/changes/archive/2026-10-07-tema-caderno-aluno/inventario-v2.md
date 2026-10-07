# Inventário aluno (V2 → caderno) — levantado em 06/10/2026

Paths relativos a C:\Users\Dell\Desktop\DesbloqueiaCursos.

## 0. Fatos transversais
- Nenhum controller V2 de aluno usa `TemaPublico::view()`; todos fazem `View::render('v2/<x>', $data, false)`. Gancho: `TemaPublico::view('aluno'|'aula'|'quiz'|'atividade'|'conta')` + `resources/views/caderno/<x>.php` (shim) + `caderno/pages/<x>.php`. Exemplo de shim: `caderno/curso.php` (define `$contentView`, `$paginaTema`, requer `caderno/layout.php`).
- Auth: rotas V2 sem middleware em routes/web.php; cada controller checa `Session::get('usuario_id')` e redireciona p/ `/v2/login?origem=v2_aluno` (smoke exige esse destino).
- CSRF: `View::render` chama `Csrf::injectIntoHtml` (View.php:37,42), injeta `_token` em todo `<form method="post">` (pula se já houver `name="_token"`). JS de autosave/tempo/revisão lê `input[name=_token]` do form `#v2-quiz-answer-form` → esse form precisa continuar `method="post"`.
- Layout V2 inclui navbar.php e footer.php; footer inclui bottom-nav.php (form de logout `POST /v2/logout` com `Csrf::field()`). Quando há `.v2-lms-area`, CSS esconde `.v2-bnav` (v2-main.css:1156) e mostra `.v2-lms-bnav` (aula.php:284-296).
- Layout V2 carrega `/assets/css/conteudo-html-embed.css?v=20260717` e `/assets/js/conteudo-html-embed.js?v=20260717`; caderno layout só carrega o JS (layout.php:37) — falta o CSS.
- Dados de layout: cada controller monta `dadosLayout()` com `loggedIn, usuarioNome, usuarioPrimeiroNome, areaHref (/v2/aluno|/admin|/professor/dashboard), alunoHref, loginHref, registerHref, catalogoHref, categoriasHref, certificadosHref, sobreHref, contatoHref, homeHref`. ContaController usa `categoriasHref=/v2/categorias/` e não define `alunoHref`.
- `v2/assets/js/v2-main.js` (3549 linhas) roda tudo no DOMContentLoaded (3505-3547). `initFormGuard` (3498) faz preventDefault em todo form SEM `data-native-submit`; todo form real do aluno tem `data-native-submit`.
- `initAlunoV2` (1232) e `initAulaV2` (1596) são demo (window.V2_ALUNO, #v2-lms), retornam cedo nas páginas reais. Área do aluno real é 100% PHP com links GET.

## 1. Rotas (routes/web.php)
| Método | Path | Controller | Linha |
|---|---|---|---|
| GET | /v2/aluno?aba=cursos\|pedidos\|certificados\|perfil | V2\Aluno@index | 101 |
| POST | /v2/aluno/pedidos/cancelar | cancelarPedido | 102 |
| GET | /v2/minha-conta | V2\Conta@editar | 103 |
| POST | /v2/minha-conta | atualizar | 104 |
| GET | /v2/aula | V2\Aula@index | 105 |
| POST | /v2/aula/concluir | concluir | 106 |
| GET | /v2/quiz | V2\Quiz@index | 107 |
| POST | /v2/quiz/iniciar | iniciar | 108 |
| POST | /v2/quiz/enviar | enviar | 109 |
| GET | /v2/atividade | V2\Atividade@index | 110 |
| POST | /v2/atividade/enviar | enviar | 111 |
| POST | /v2/logout (auth.v2) | V2\Login@logout | 97 |

Endpoints V1 JSON usados pelo quiz (middleware auth, CSRF `_token` no body; header `X-CSRF-TOKEN` no /tempo):
- POST /aluno/cursos/quiz/rascunho (web.php:230 → QuizController::salvarRascunho, app/Controllers/QuizController.php:78)
- POST /aluno/cursos/quiz/tempo (233, QuizController.php:139)
- POST /aluno/cursos/quiz/revisao (232, QuizController.php:123)

Links GET V1 reutilizados: /aluno/cursos/conteudo/arquivo/download?id&inscricao_id&curso_id&turma_id; /aluno/cursos/conteudo/link/acessar?...; /aluno/cursos/conteudo/avaliacao/imagem?id=; /aluno/curso/{insc}/{curso}/{turma}/modulo/{m}/conteudo/{c} (oficial_url); /certificados/show?codigo=, /certificados/pdf?codigo=, /v2/certificados/validar?codigo=; /v2/checkout/resumo?pedido_id=.

## 2. Controllers e View::render (app/Controllers/V2/)
**AlunoController** (render :109). Chaves: title, pageTitle, pageDescription, norminhaContexto(AREA_ALUNO), abaAtiva, abas[{chave,label,total,href}], cursos, certificados, pedidos, perfil, success, errors, coursePalette + dadosLayout.
- cursos[]: inscricao_id, curso_id, turma_id, nome, thumbnail, turma, turma_codigo, modalidade, progresso, status, status_label, status_classe, concluida, tem_certificado, lms_href.
- certificados[]: curso, codigo, emitido_em, status, online_href, validar_href, pdf_href.
- pedidos[]: id, codigo, data, total, total_formatado, status, status_label, status_classe, total_itens, cursos_nomes, titulo_curso, multiplos_cursos, resumo_v2_href, pode_cancelar.
- perfil: nome, email, cpf_mascarado, telefone_mascarado, editar_href.
- cancelarPedido (118-167): sempre redirect /v2/aluno/?aba=pedidos com flash.

**AulaController** (render :209 normal; :572 estado() 200|404). Chaves: norminhaContexto (AULA com item / CURSO sem), title, pageTitle, pageDescription, estado, cabecalho{curso_nome,turma_nome,progresso}, arvore, navegacao{anterior,proximo}, item, itemInacessivel, temConteudo, formCtx, overviewUrl, success, errors. estado() NÃO passa norminhaContexto/success/errors/formCtx/overviewUrl.
- arvore[]: id, titulo, status_label, total_itens, concluidos_itens, aberto, itens[{id,titulo,tipo,tipo_label,concluido,atual,etiqueta,url}].
- item (montarItem 339-404): id, titulo, tipo, tipo_label, status_label, status_class, concluido, pode_concluir, auto_leitura, modulo_titulo, texto_html, video_embed, video_url, video_embed_resolvido{provider,embedUrl}, video_incorporado_conteudo, arquivo_extensao, arquivo_nome, arquivo_tamanho, arquivo_icone (HTML sem escape), link_host, descricao_curta, acao_url, eh_interativo, oficial_url, quiz_url, atividade_url.
- formCtx: inscricao_id, curso_id, turma_id, modulo_id, item_id, concluir_action.
- concluir() (223-316): POST → redirect urlV2(...item); flash success/errors e `v2_aula_sem_autocompletar_item_id` (só desmarcar).

**QuizController** (render :171 e :505 estado()). Chaves: norminhaContexto(AVALIACAO+formCtx), title, pageTitle, pageDescription, estado, cabecalho{curso_nome,quiz_nome,modulo_nome,progresso}, quiz, formCtx(+iniciar_action, enviar_action), voltarAulaUrl, success, errors.
- quiz.estado: indisponivel | antes | andamento | resultado (montarEstadoQuiz 340-410). Comuns: titulo, instrucoes, total_perguntas, tentativas_usadas, tentativas_maximas, pode_nova_tentativa, estrutura, limite_caracteres_discursiva. andamento: tentativa_ativa_id, numero_tentativa, perguntas, tempo{segundos_restantes}. resultado: perguntas, resultado{mostrar_resultado,mostrar_gabarito,mostrar_comentarios,total_acertos,total_perguntas,percentual,aprovado}.
- perguntas[] (perguntasParaView 420-454): id, enunciado, obrigatoria, alternativas[{id,texto,(correta só no resultado)}], alternativa_id_respondida, explicacao (só resultado), tipo, bloco_codigo, bloco_titulo, texto_resposta, marcada_para_revisao. Rubrica/gabarito nunca no DOM na prova.
- iniciar() (178) e enviar() (225) redirecionam /v2/quiz?... (&tentativa_id= após enviar). enviar lê tentativa_id, respostas[], discursivas[].

**AtividadeController** (render() :314; estado() :366). Chaves: norminhaContexto(AVALIACAO, só render), title, pageTitle, pageDescription, estado, cabecalho{curso_nome,atividade_nome,modulo_nome}, atividade, formCtx, voltarAulaUrl, success, errors.
- atividade.estado: externo {titulo, oficial_url} | avaliacao {titulo, enunciado_html, orientacoes_html, prazo, status_code, status_label, pode_enviar, ja_enviada, ultima{resposta,tentativa,enviado_em,nota,feedback,imagens[{id,nome_original}]}}.
- enviar() (181-258): lê resposta e $_FILES['imagens'] → redirect /v2/atividade?...

**ContaController** (render :55). Chaves: title, pageTitle, pageDescription, conta, old, success, errors (assoc por campo) + dadosLayout; sem norminhaContexto. atualizar() (58): flash errors/old → /v2/minha-conta; sucesso flash success.

## 3. Views
Shims (3-4 linhas): v2/aluno.php, aula.php, quiz.php, atividade.php, conta.php (os 4 do LMS setam $disableV2AutoRenderHome=true). Páginas v2/pages/: aluno.php 228, aula.php 307, quiz.php 415, atividade.php 165, conta.php 172. Partials: v2/partials/lms-arvore.php (41; incluído 2× em aula.php :69 visão geral mobile e :303 aside; usa $arvore), bottom-nav.php, navbar.php (form POST /v2/logout), norminha_montagem.php.

## 4. Formulários e hooks JS
**aluno.php**: form (:158) POST /v2/aluno/pedidos/cancelar data-native-submit: hidden pedido_id, text motivo_cancelamento (required, id=v2-motivo-<id>), dentro de <details class="v2-pedido-cancelar">. Abas: <a> GET ?aba= com role=tab. Sem JS específico.

**aula.php**: form de conclusão (3 variantes) POST /v2/aula/concluir data-native-submit: hidden inscricao_id, curso_id, turma_id, modulo_id, item_id, acao(marcar|desmarcar); botão [data-complete-btn][data-loading-label="Concluindo…"]. Classes .v2-lms-complete (marcar), .v2-lms-complete-form (desmarcar); auto-leitura: atributo data-v2-autocomplete (:252). Hooks: #v2-aula-feedback (tabindex -1, aria-live), .v2-mod-head (acordeão), iframes #conteudo-html-frame-<id>.js-conteudo-html-frame e #conteudo-video-incorporado-frame-<id>. JS initLmsV2 (2430-2465): foca feedback; no submit marca data-submitting, troca texto p/ loading-label, desabilita via setTimeout; form[data-v2-autocomplete] → requestSubmit() após 150 ms. initAccordion (2420): clique .v2-mod-head alterna is-open.

**quiz.php**:
- Form iniciar (:158 e :406 "Nova tentativa"): POST /v2/quiz/iniciar data-native-submit .v2-quiz-form: hidden inscricao_id, curso_id, turma_id, modulo_id, item_id; botão [data-quiz-btn][data-loading-label].
- Form enviar (:198): id=v2-quiz-answer-form class=v2-quiz-form POST /v2/quiz/enviar data-native-submit: os 5 hidden + hidden tentativa_id, radio name=respostas[<pid>] value=<alt_id>, textarea name=discursivas[<pid>] (maxlength=limite_caracteres_discursiva, id v2-discursiva-<pid>).
- IDs: v2-quiz-feedback, v2-quiz-autosave, v2-quiz-perguntas (+data-modo-prova="1"); modo curto: v2-quiz-progress, -fill, -text, -dots, v2-quiz-prev-btn, v2-quiz-next-btn, v2-quiz-submit-btn; modo prova: v2-quiz-prova-nav, -bloco, -pos, -fill, -resumo, -indice-btn, -indice, -prev-btn, -next-btn, -revisar-btn, v2-quiz-prova-revisao (+-resumo, -listas), -voltar-btn, -enviar-btn; cronômetro: v2-quiz-cronometro (data-tentativa, data-restante, role=timer), v2-quiz-cronometro-valor.
- Fieldset: .v2-quiz-pergunta[data-pergunta-id][data-tipo=objetiva|discursiva][data-bloco][data-bloco-titulo][data-revisao=0|1]; botão revisão [data-flag-pergunta=<pid>][aria-pressed] + .v2-quiz-flag__texto; .v2-quiz-bloco-titulo.
- Pegadinha: [hidden] + style.display (porque .v2-btn é inline-flex).
- JS initQuizV2 (2467-2499) chama Wizard, Cronometro, Autosave, Prova:
  - initQuizAutosaveV2 (2501-2630): só se #v2-quiz-answer-form e tentativa_id≠0; change radio debounce 600 ms, input textarea 2000 ms; fetch POST /aluno/cursos/quiz/rascunho JSON {tentativa_id,item_id,inscricao_id,curso_id,turma_id,respostas:{pid:altId},discursivas:{pid:texto},_token}; resposta {ok}|{expirada}(reload); falha re-enfileira; dispara em visibilitychange/pagehide com keepalive; expõe v2QuizSalvarAgora; mensagens em #v2-quiz-autosave.
  - initQuizCronometroV2 (2633-2705): setInterval 1 s a partir de data-restante (HH:MM:SS; ≤300 s borda vermelha; 0 → encerrarPorTempo); setInterval 60 s POST /aluno/cursos/quiz/tempo JSON {tentativa_id,_token} + header X-CSRF-TOKEN → {ok,encerrada,tempo.segundos_restantes} (encerrada → reload; ressincroniza). Ao zerar: POST /tempo, alert, reload (servidor decide envio automático).
  - initQuizWizardV2 (2707-2804): modo curto, 1 pergunta por vez, só se não modo prova e >1 pergunta; current/maxReached; começa na 1ª não respondida; dots clicáveis até maxReached; Próxima exige resposta (alert); sem fetch.
  - initQuizProvaV2 (2806-3126): modo prova, navegação livre, índice por blocos, revisão ("Sem resposta"/"Marcadas"), chama v2QuizSalvarAgora antes de trocar; fetch POST /aluno/cursos/quiz/revisao {tentativa_id,pergunta_id,marcada,_token} fire-and-forget; no submit: falta discursiva → preventDefault + abre revisão; pendentes → confirm(); abre na 1ª pendente.
- Sem JS: perguntas roláveis, envio nativo.

**atividade.php**: form (:135) id=v2-atividade-form class=v2-atv-form POST /v2/atividade/enviar enctype multipart data-native-submit: 5 hidden, textarea name=resposta (required, minlength 3, maxlength 50000, id v2-atv-resposta, [data-char-counter]), input file name="imagens[]" id=v2-atv-imagens accept=.jpg,.jpeg,.png,.webp multiple (até 5, 1,5 MB cada — texto; validação no service). Hooks: #v2-atividade-feedback, [data-char-count] (#v2-atv-contador), #v2-atv-imagens-erro[data-imagens-erro], [data-atv-btn][data-loading-label], #v2-atv-enunciado. JS initAtividadeV2 (3128-3178): foco feedback, contador, valida ≤5 imagens no change, anti clique duplo.

**conta.php**: form (:49) id=v2-conta-form POST /v2/minha-conta data-native-submit: nome, email, cpf (data-mask-cpf, maxlength 14), telefone, estado (select 27 UF), cidade (select via JS), nova_senha, nova_senha_confirmacao (minlength 8); botão [data-checkout-btn][data-loading-label="Salvando…"]. Máscara CPF initAuthMasksV2 (3262). Script inline (conta.php:106-171): fetch GET https://servicodados.ibge.gov.br/api/v1/localidades/estados/<ibgeId>/municipios?orderBy=nome com cache por UF, json_encode estado/cidade atual. Breadcrumb /v2/aluno/?aba=perfil.

## 5. Renderização de conteúdo (aula.php)
Todos via `Helpers::renderSafeHtml($html, $perfil)` (Helpers.php:22 → decodeEditorHtml → HtmlSanitizer::clean; sem tag → nl2br(e()); vazio → texto puro). Perfis: minimal, basic (tabelas/div), full (h1-h6); 'reading' (aula.php:134) não existe e cai em basic. Sanitizador remove script/style/iframe/form/svg e class (exceto div card-pratica, card-resumo, alerta-educacional).
- texto: renderSafeHtml(texto_html,'reading') em .v2-lms-prose; controller remove h1-h3 inicial igual ao título (413); auto_leitura=true.
- html: iframe sandbox="allow-scripts allow-popups" class=js-conteudo-html-frame srcdoc=HtmlEmbedRenderer::wrap($html,$frameId) (escapado com e()); wrap injeta postMessage({source:"desbloqueia-html-embed",frameId,height}); assets/js/conteudo-html-embed.js ajusta altura (máx 85% viewport) conferindo contentWindow===event.source; auto_leitura=true.
- video: (1) video_embed → renderSafeHtml(...,'full') (iframe removido); (2) video_embed_resolvido via VideoEmbedResolver (YouTube nocookie/Vimeo/Drive): iframe src=embedUrl, allow=..., referrerpolicy=strict-origin-when-cross-origin + link "Abrir no site original"; (3) botão "Assistir vídeo"; (4) "Não há vídeo...".
- video_incorporado: iframe sandbox="allow-scripts allow-popups" srcdoc com <style> reset + conteúdo bruto (só escape de atributo). Manter sandbox sem allow-same-origin.
- arquivo: descrição basic + card com arquivo_icone (HTML de Helpers::iconeArquivo, sem escape), nome, extensão, tamanho, <a href=acao_url>Baixar material</a>.
- link: descrição basic (texto_html ou descricao_curta) + card link_host + acao_url.
- quiz: link quiz_url; avaliacao_textual: link atividade_url; outros interativos: link oficial_url (LMS legado).
- etiqueta: só na árvore como <div> sem link (lms-arvore.php:124).
- Atividade: enunciado_html full, orientacoes_html basic. Resposta/feedback/instruções do quiz: nl2br(e()). Quiz: enunciado/alternativas/explicação e()/nl2br(e()). Imagens da entrega: /aluno/cursos/conteudo/avaliacao/imagem?id= (rota autenticada).

## 6. Ramos de estado
- Aluno: abas (inválida → cursos); listas vazias com CTA /v2/catalogo/; "Revisar curso" (concluida) vs "Continuar estudando"; selo tem_certificado; status_classe v2-badge-novo|gratis|destaque; pedido resumo_v2_href (rascunho/pendencia/aguardando_reenvio/aguardando_pagamento), pode_cancelar (rascunho, aguardando_pagamento, pendencia, aguardando_reenvio, comprovante_enviado, em_analise); 1/vários/nenhum curso no pedido; certificados só com código; flash.
- Aula: estado (404/200 "Selecione um curso"); itemInacessivel; mostrarVisaoGeral; "Conteúdo a caminho"; concluído + "Desmarcar conclusão" (pode_concluir); auto-leitura (texto/html) ou "Marcar como concluído"; quiz/avaliação/atividade sem conclusão manual; anterior/próxima (pula etiquetas); flash v2_aula_sem_autocompletar_item_id.
- Quiz: estado genérico; quiz.estado indisponivel/antes/andamento/resultado; "Antes de começar" se duracao>0 ou por_blocos (estrutura: duracao_minutos, total_questoes, total_objetivas, total_discursivas, exige_aprovacao, percentual_minimo, tentativas_restantes, tentativas_maximas, acao_ao_expirar, blocos[{titulo,quantidade,tipo_questao,conta_para_percentual}]); pode_nova_tentativa false → "máximo de tentativas"; andamento com tempo → cronômetro; modo prova se discursiva ou bloco_codigo; resultado com mostrar_resultado (acertos, %, aprovado/"Não atingiu o percentual mínimo") ou "Quiz enviado com sucesso"; gabarito/comentários se liberados; "Nova tentativa"; resultado só com ?tentativa_id= do aluno. Sem view de "tempo esgotado" (JS alert+reload; service envia conforme acao_ao_expirar).
- Atividade: estado genérico; externo; status_code nao_enviada | em_correcao | devolvida | corrigida | aprovada | reprovada | cancelada (classe v2-atv-status--<code>); pode_enviar false → mensagem; "Enviar"/"Reenviar resposta", "Sua"/"Nova resposta"; nota/feedback se preenchidos.
- Conta: erros por campo; old repopula; sucesso.

## 7. Testes e smoke
- tests/Unit sem teste de view/controller dos 5 fluxos; quiz_system.php, quiz_simulado_pnd.php, quiz_rascunho.php (autosave), quiz_sorteio.php testam service/banco. Relacionados: tema_publico.php, norminha_hints/rotas/context/arquitetura.php.
- Smoke (tests/Smoke/rotas.php): protegidas anônimas → /v2/login: /v2/aluno, /v2/aula, /v2/quiz, /v2/atividade, /v2/minha-conta, /v2/pos-login (78-83); autenticado (SMOKE_USER/PASS): /v2/aluno, /v2/minha-conta, /meus-cursos, /area-curso (101-104). Exige folha de estilo base (app.css|v2-main.css|caderno.css). aula/quiz/atividade autenticados sem cobertura.

## 8. Norminha
- Montagem única no LAYOUT (v2/partials/norminha_montagem.php; caderno já via caderno/partials/layout-dados.php:23). Variáveis $tutorNorminha, $tutorNorminhaJsVersion, $tutorNorminhaCssVersion, $norminhaPath, $norminhaContexto. Caderno head já tem tutor_norminha_head.php; layout já monta components/tutor_norminha.php + tutor-norminha.js com guarda if ($tutorNorminha). Nunca montar na página.
- Contexto via $norminhaContexto do controller (aluno area_aluno; aula aula/curso com item resolvido; quiz/atividade avaliacao com ids de formCtx, nunca gabarito; conta sem contexto → TutorVirtualService.php:189 mapeia /v2/minha-conta). Estados de erro não passam contexto (intencional).
- O que o layout caderno NÃO tem e a fase 2 precisa tratar: /assets/css/conteudo-html-embed.css (iframes html: .conteudo-item-html, .conteudo-item-html__frame); bnav do caderno sem logout (conferir caderno/partials/topo.php); o JS do v2-main.js (data-native-submit, quiz autosave/cronômetro/wizard/prova, auto-leitura, contador, máscara CPF, cidades IBGE, acordeão) precisa ser reimplementado no caderno.js ou num JS dedicado; esconder a bnav global em aula/quiz/atividade (paralelo ao body:has(.v2-lms-area) .v2-bnav) ou replicar a v2-lms-bnav; window.V2_DISABLE_AUTORENDER_* desnecessário.

## 9. Armadilhas
- Helpers::iconeArquivo impresso sem e() — só se vier de lista fechada.
- video_incorporado_conteudo sem sanitização dentro de srcdoc; manter sandbox="allow-scripts allow-popups" sem allow-same-origin.
- ids e names (respostas[], discursivas[], tentativa_id, item_id...) são contrato com endpoints V1 JSON; não renomear.
- perfil mascara CPF/telefone; conta recebe completos (por design).
- Redirects usam /v2/aluno/?aba=... com barra; lms_href → /v2/aula/?inscricao_id=&curso_id=&turma_id=.

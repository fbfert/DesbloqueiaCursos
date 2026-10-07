# Tarefas

Contexto obrigatório para cada tarefa: `proposal.md`, `specs/tema-publico/spec.md`, `design.md` desta mudança e `inventario-v2.md` (nesta pasta) (ids, names, endpoints e ramos de estado da V2). Rodar PHP só no container `desbloqueia-app-1` (http://127.0.0.1:8010). A V2 (`resources/views/v2/pages/<x>.php` + `v2/assets/js/v2-main.js`) é a referência de paridade de cada página.

## 1. Base

- [ ] 1.1 Fixture `tests/Fixtures/tema_caderno_aluno.sql` conforme design.md §6; aplicar no banco local e conferir abrindo `/v2/aluno`, `/v2/aula`, `/v2/quiz` (curto e prova) e `/v2/atividade` da V2 com o aluno de teste (todos os estados alcançáveis listados no arquivo).
- [ ] 1.2 Trocar todos os `View::render('v2/<x>'…)` de `AlunoController`, `ContaController`, `AulaController`, `QuizController` e `AtividadeController` (inclusive `estado()`) por `TemaPublico::view('<x>')`; teste em `tests/Unit/tema_publico.php` (ou novo) confirmando o fallback para V2 enquanto a view do tema não existe.
- [ ] 1.3 Layout: `$cadernoAluno` / `$cadernoEstudo` conforme design.md §2; criar `assets/caderno/caderno-aluno.css` e `assets/caderno/caderno-aluno.js` (envio protegido de §4 + registro por página), com versão por `filemtime`; conferir que home e catálogo não requisitam esses arquivos.

## 2. Área do aluno e minha conta

- [ ] 2.1 `caderno/aluno.php` + `caderno/pages/aluno.php`: abas, cursos, pedidos (retomar e cancelar), certificados, perfil, vazios, flash — design.md §3.
- [ ] 2.2 `caderno/conta.php` + `caderno/pages/conta.php` + módulo conta do `caderno-aluno.js` (máscara de CPF, cidades por UF com cache e fallback): mesmos campos, erros por campo e `old`.
- [ ] 2.3 Verificar por HTTP: as quatro abas, aba inválida → cursos, cancelamento de pedido (com e sem motivo), salvar conta com e-mail inválido e com dados válidos; restaurar o banco local.

## 3. Aula e atividade

- [ ] 3.1 `caderno/aula.php` + `pages/aula.php` + partials do sumário e do conteúdo por tipo: todos os tipos, item inacessível, estado sem curso (404/200), concluído/desmarcar, auto-conclusão, anterior/próxima, sumário no celular e barra de estudo.
- [ ] 3.2 Módulo aula do `caderno-aluno.js`: foco no feedback, auto-conclusão em 150 ms, ✓ à caneta após concluir.
- [ ] 3.3 `caderno/atividade.php` + `pages/atividade.php` + módulo atividade (contador, limite de 5 imagens): externo, avaliação, todos os `status_code`, `pode_enviar` falso, última entrega com imagens.
- [ ] 3.4 Verificar por HTTP: cada tipo de conteúdo da fixture, iframes com `sandbox="allow-scripts allow-popups"`, concluir e desmarcar, enviar atividade com texto e uma imagem; restaurar o banco local.

## 4. Quiz

- [ ] 4.1 `caderno/quiz.php` + `pages/quiz.php`: estados indisponível, antes (com estrutura e blocos), andamento (curto e prova, cronômetro, marcar revisão, sem JS rolável), resultado (com e sem nota, gabarito/explicações quando liberados, nova tentativa); conferir que gabarito e rubrica não aparecem no HTML em andamento.
- [ ] 4.2 Módulo quiz do `caderno-aluno.js`: autosave, cronômetro, modo curto e modo prova conforme design.md §4, sem `alert`/`confirm`.
- [ ] 4.3 Verificar em navegador headless (perfil temporário, nunca o do usuário): marcar resposta → rascunho gravado; recarregar → resposta restaurada; modo prova com discursiva vazia bloqueia; envio com pendência pede confirmação inline; cronômetro sincroniza; envio final mostra resultado. Restaurar o banco local.

## 5. Fechamento

- [ ] 5.1 Smoke (`tests/Smoke/rotas.php`): páginas do aluno no tema quando `TEMA_PUBLICO=caderno` (folha `caderno.css` + `caderno-aluno.css`), anônimo → `/v2/login`; aula, quiz e atividade autenticados com a fixture quando disponível (SKIP sem ela).
- [ ] 5.2 Orçamento: medir gz de `caderno.js` + `caderno-aluno.js` (≤ 25 KB) e `caderno.css` + `caderno-aluno.css` (≤ 25 KB); Lighthouse celular na área do aluno e numa aula (LCP ≤ 2,5 s, CLS ≤ 0,1); registrar no doc.
- [ ] 5.3 Acessibilidade: foco visível, navegação por teclado no sumário, abas e quiz; `prefers-reduced-motion`; contraste dos estados (concluído, atual, erro, tempo acabando); 360 px sem rolagem horizontal.
- [ ] 5.4 `php -l` (container) e lint sem `Deprecated` no PHP 8.4 do host em todos os PHP tocados; testes `tema_publico`, `norminha_arquitetura`, `quiz_rascunho` e smoke passam.
- [ ] 5.5 Atualizar `docs/2026-10-06-tema-caderno.md` (escopo, fase 2 entregue, orçamento medido, pendências) e o README do tema se houver.

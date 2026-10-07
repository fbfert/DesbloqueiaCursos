# Proposal

## Why

O tema caderno (mudança arquivada `2026-10-06-tema-caderno`) cobre a vitrine, a autenticação e o checkout, mas o aluno que acabou de comprar cai numa área com outro visual: área do aluno, aula, quiz, atividade e minha conta continuam na V2. É justamente onde o aluno passa a maior parte do tempo — estudando no celular. Esta fase completa o site público no tema, para que a experiência "estudo como avanço de fase" continue depois da compra, e para que a V2 possa ser removida das páginas migradas em seguida.

## What Changes

- **Área do aluno** (`/v2/aluno`, abas cursos, pedidos, certificados e perfil) no tema: cada curso como um caderno em andamento (capa colada, trilha de progresso), pedidos como recibos, certificados como selos, perfil como ficha.
- **Aula** (`/v2/aula`) no tema: sumário do caderno (árvore de módulos) ao lado no desktop e numa folha que sobe no celular; o conteúdo como página pautada; barra inferior própria no celular com anterior, próxima e concluir; marcação de conclusão com o ✓ à caneta. Todos os tipos de conteúdo atuais (texto, HTML isolado em iframe, vídeo, vídeo incorporado, arquivo, link, quiz, avaliação textual, atividades legadas).
- **Quiz** (`/v2/quiz`) no tema, como folha de prova: estados antes, em andamento, resultado e indisponível; modo curto (uma pergunta por vez com progresso) e modo prova (blocos, discursivas, índice, revisão, marcar para revisar); cronômetro; salvamento automático das respostas. Mesmos formulários e os mesmos endpoints de salvamento, tempo e revisão.
- **Atividade** (`/v2/atividade`) no tema, como folha de resposta: enunciado, orientações, prazo, situação, última entrega com nota e devolutiva, envio com texto e até 5 imagens.
- **Minha conta** (`/v2/minha-conta`) no tema, como ficha cadastral: mesmos campos, máscara de CPF e carregamento das cidades por UF.
- Um arquivo de JavaScript e um de CSS do tema só para essas páginas (`assets/caderno/caderno-aluno.js` e `.css`), para não pesar na vitrine.

**Fora de escopo:** área do professor, revisor e admin; LMS legado (`/aluno/curso/...`, `/meus-cursos`, `/area-curso`); mudanças em regras de progresso, correção, tentativas ou certificados; remoção das views V2 (mudança seguinte, depois da virada da chave); novos tipos de conteúdo.

## Capabilities

### New Capabilities
(nenhuma)

### Modified Capabilities
- `tema-publico`: o escopo passa a incluir área do aluno, aula, quiz, atividade e minha conta; o orçamento de JavaScript ganha a regra das páginas do aluno; novos requisitos de paridade e comportamento dessas páginas.

## Impact

- Código novo: `resources/views/caderno/{aluno,aula,quiz,atividade,conta}.php` + `pages/` e partials (sumário, conteúdo por tipo, barra da aula, pergunta, cronômetro); `assets/caderno/caderno-aluno.js`; `assets/caderno/caderno-aluno.css` (CSS só dessas páginas).
- Código alterado: uma linha de seleção de view em `app/Controllers/V2/{Aluno,Aula,Quiz,Atividade,Conta}Controller.php` (todos os pontos de `View::render`, inclusive `estado()`); layout do tema (CSS dos conteúdos HTML incorporados, barra inferior nas páginas de estudo, carregamento do `caderno-aluno.js`); `tests/Smoke/rotas.php`; fixture local (curso com módulos de todos os tipos, quizzes e matrícula de teste).
- Sem migration, sem rota nova, sem mudança em Service ou Model.
- Ativação: a mesma chave `TEMA_PUBLICO`; com `v2`, nada muda.

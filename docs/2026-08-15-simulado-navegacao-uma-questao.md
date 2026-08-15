# Simulado: uma questão por vez, gravação a cada passo e revisão antes do envio

Data: 2026-08-15
Superfície: LMS V2 (`/v2/quiz`)
Aplica-se a: simulados PND (cursos 118, 119 e 120). Quizzes curtos não mudam.

O simulado exibia as 81 questões numa página só, com navegação por rolagem.
Agora é uma questão por vez, com índice, marcação para revisão e conferência
antes do envio.

## Por que a mudança foi menor do que parecia

Quase tudo já existia e estava desligado ou sem interface:

- o **assistente de uma questão por vez** (`initQuizWizardV2`) existia, mas
  tinha um `return` explícito quando `data-modo-prova="1"`;
- o **autosave** já era robusto: envia só o que mudou, com debounce, fila de
  reenvio em caso de falha e `keepalive` para não perder resposta ao fechar a
  aba;
- **marcar para revisão** já estava pronto de ponta a ponta — coluna
  `marcada_para_revisao`, service, rota `/aluno/cursos/quiz/revisao` — e o
  controller da V2 já entregava o campo à view. Só a interface não usava.

O trabalho foi ligar isso e escrever a navegação específica de prova.

## O que foi feito

**`initQuizProvaV2()`** em `v2/assets/js/v2-main.js`, separada do assistente
dos quizzes curtos para não misturar as duas regras:

- uma questão por vez, com **navegação livre**: avança sem responder, volta e
  pula direto pelo índice. É como funcionam ENEM, concurso e a própria PND;
  travar o aluno numa questão difícil em 81 questões seria hostil;
- **grava a cada troca de questão**. O autosave passou a expor
  `v2QuizSalvarAgora`, que descarrega a fila na hora em vez de esperar o
  debounce;
- **índice agrupado por bloco** (Formação Geral, Componente Específico,
  Discursiva). Em 81 questões uma fileira de bolinhas seria inútil, então é
  grade: cheia quando respondida, contorno quando pendente, ponto quando
  marcada;
- **abre na primeira pendente**, para quem retoma uma sessão interrompida cair
  onde parou e não no começo;
- **revisão antes de enviar**, listando "Sem resposta" e "Marcadas para
  revisão" com botão que leva direto à questão.

Envio: a discursiva em branco **bloqueia** e abre a revisão, porque o servidor
recusaria de qualquer forma e o aluno descobriria só depois de clicar.
Objetiva em branco apenas confirma.

Progressive enhancement preservado: sem JavaScript, as questões continuam todas
visíveis e o envio direto funciona. Verificado.

## Defeitos encontrados

**Já existia antes desta mudança:** em modo prova apareciam botões "Anterior" e
"Próxima" que não faziam nada. O atributo `hidden` não os escondia porque
`.v2-btn` define `display:inline-flex`, e CSS de autor vence o do user-agent com
a mesma especificidade — o próprio código já tinha um comentário sobre isso em
outro ponto. Como `initQuizWizardV2` saía cedo em modo prova, nunca chegava a
alternar o `style.display`. Agora esses botões não são mais renderizados em modo
prova, em vez de apenas escondidos.

**Introduzido e corrigido durante o trabalho:** o handler genérico de
"Enviando…" é registrado antes e desabilita o botão no submit. Ao barrar o envio
por discursiva em branco, o aluno ficaria com um botão morto escrito
"Enviando…". A liberação agora usa `setTimeout` para entrar na fila depois do
handler genérico e restaurar rótulo, `disabled` e `data-submitting`.

**Ordem no PHP:** `$modoProva` estava sendo usado antes de ser calculado.
Variável indefinida avalia como falsa, então a condição passaria despercebida.
O cálculo subiu para antes do primeiro uso.

## Cache busting da V2

Os assets da V2 eram servidos **sem versionamento**, então a mudança não
chegaria em quem já visitou o site — o navegador continuaria com o JS em cache.
`resources/views/v2/layout.php` passou a versionar por `filemtime`, o mesmo
padrão de `resources/views/layout.php`.

Sem isso o restante desta entrega seria invisível para a maior parte dos alunos.

## Verificação

A view real foi renderizada com dados sintéticos (10 questões, 2 blocos e uma
discursiva) e o Chromium foi dirigido por script, com `fetch` interceptado para
observar as chamadas de rede:

| Verificação | Resultado |
|---|---|
| Navegação 2 → 3 → 2 | ok |
| Gravação ao avançar | 1 chamada a `/rascunho` com a resposta no corpo |
| Marcar para revisão | 1 chamada a `/revisao` |
| Contadores | "3 de 10 respondidas · 7 pendentes · 1 marcada" |
| Envio sem discursiva | bloqueado, abre a revisão, botão não trava |
| Sem JavaScript | 10 questões visíveis, envio direto presente |
| Quiz curto | assistente antigo intacto, zero elemento de prova |

Os dois modos são mutuamente exclusivos na marcação: o simulado renderiza a
navegação de prova e nenhum elemento do assistente antigo, e o quiz curto o
contrário.

## Pendência

Falta o teste com aluno real numa tentativa de verdade — a verificação acima usa
dados sintéticos e `fetch` interceptado, então cobre a mecânica da interface,
não a integração com uma tentativa gravada no banco.

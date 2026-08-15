# Curso 120 — PND na prática: MATEMÁTICA

Data: 2026-08-15
Curso: 120 — "PND na prática - MATEMÁTICA: preparação para a Prova Nacional Docente"
Modelo: cursos 118 (Pedagogia) e 119 (Letras-Português)

Terceiro curso preparatório para a Prova Nacional Docente, na habilitação
Matemática, para quem atua nos **anos finais do fundamental e no ensino médio**.

## O que foi construído

| | |
|---|---|
| Módulos | 17 |
| Aulas (`tipo=html`) | **61** (média de 45.751 caracteres) |
| Banco de questões | **246** — 90 FGD + 150 Matemática + 6 discursivas |
| Simulado (quiz 26) | 30 + 50 + 1 = **81 por tentativa**, 330 min, 3 tentativas, 60% |
| 3 tentativas | **243 questões distintas, zero reutilização** |
| Turma | 74 · PNDMAT01-2026A, aberta, 15/08 a 31/12/2026 |

Componente específico nas unidades da BNCC: Números · Álgebra · Geometria ·
Grandezas e proporcionalidade · Probabilidade e estatística · Resolução de
problemas e modelagem · Erros, obstáculos didáticos e avaliação.

## O que foi aplicado desde o começo, aprendido no 119

**Módulos de Formação Geral não são neutros.** No 119 a premissa de que eles
podiam ser copiados sem alteração custou um terceiro workflow: os exemplos se
passavam em turmas de anos iniciais. Aqui a instrução já saiu separando teoria
(que continua geral) de exemplo (que se passa numa aula de Matemática do 6º ao
médio), com ordem explícita de **preservar** menções normativas a etapas da
educação básica, que são conteúdo e não exemplo.

**Cota de dificuldade imposta por agente, não conferida no fim.** No 119 a
distribuição saiu 31/88/31 e faltaram médias, o que gerou repetição na terceira
tentativa. Aqui cada um dos 10 temas recebeu cota fixa de 3 fáceis, 9 médias e
3 difíceis — 30/90/30 exatos, que é o que 3 tentativas consomem. Fechou na
primeira tentativa.

**Nenhuma questão veio sem gabarito.** No 119 duas vieram com o booleano em
branco. O importador aborta nesse caso, e não precisou.

## Travas editoriais de Matemática

- **correção é inegociável**: refazer cada cálculo antes de gravar. Um erro
  aritmético num preparatório destrói a credibilidade do produto inteiro;
- **notação sem biblioteca**: não há LaTeX, MathJax nem KaTeX. HTML puro e
  entidades. O validador rejeita a página que contiver `\frac`, `\sqrt` ou `$$`;
- **erro do aluno é objeto didático**: sempre com a hipótese de raciocínio por
  trás. "Soma numeradores e denominadores" é descrição; "transporta para a
  adição a regra da multiplicação, que de fato opera termo a termo" é ensino;
- **divergência não vira gabarito**: zero pertencer ou não a ℕ, trapézio
  inclusivo ou exclusivo, quadrado como caso de retângulo. Onde o tema aparece,
  o material explicita a divergência ao professor.

## A fase de conferência das questões

Cada tema passou por um segundo agente que **resolveu as 15 questões do zero,
sem olhar o gabarito**, e só depois comparou. Achou **9 problemas, todos com o
gabarito correto**:

- 8 justificativas de distrator incoerentes — o erro descrito não levaria o
  aluno àquela alternativa. Exemplo: numa PG, a alternativa dizia "usou a razão
  errada", mas 1.022 vem de usar o **expoente** 9 em vez de 10;
- 1 defeito estrutural: numa questão de escala, `300 m` e `0,3 km` eram o mesmo
  valor entre as cinco opções.

Um décimo alerta foi descartado como falso positivo: era sobre `R$` disparar
renderizador de matemática inline, e não há MathJax nem KaTeX no projeto.

**Erro cometido ao corrigir:** a primeira substituição da alternativa duplicada
usou `300 000 m`, que é 300 km — exatamente outra alternativa da lista. Recriei
o defeito que consertava. A correção final foi `0,5 km`, e a verificação passou
a comparar **valores convertidos em metros**, não rótulos. Varredura posterior
nas 150: nenhuma alternativa repetida, nenhum enunciado duplicado, 150 temas
distintos.

## Defeito que ia para produção: HTML no texto do quiz

As views do quiz (`resources/views/v2/pages/quiz.php` e
`resources/views/aluno/curso/_quiz.php`) imprimem enunciado, alternativa,
explicação e rubrica com `Helpers::e()` — **tudo escapado**. Os quizzes 24 e 25
sempre foram texto puro.

O 26 nasceu com HTML em 156 enunciados porque a instrução dada aos agentes pedia
HTML. O aluno veria `<p>Num trabalho integrado com Geografia...</p>` literal na
tela. Já estava gravado no banco quando foi descoberto.

Convertido para texto puro: 156 enunciados, 150 explicações, 6 rubricas e 242
alternativas. A matemática sobrevive em **Unicode**: `x²`, `a₁₀`, `10⁵`, `2ⁿ⁺¹`,
`√25`, `×`, `÷`, `≤`, `π`. Tabelas viraram linhas com ` | `. Backup em
`backups/quiz26-pre-conversao-texto-20260815-094130.sql.gz`.

Ao escrever o conversor, o `\s*` inicial da remoção das tags de abertura engolia
a quebra de linha que o `</tr>` acabava de inserir, e as tabelas colapsavam numa
linha só. Corrigido para `[ \t]*`.

**Para o próximo curso: enunciado de quiz é texto puro. Só a aula é HTML.**

## Decisão sobre KaTeX

Avaliado e **descartado**, com o teste registrado:

- varredura em 2,84 MB das 61 aulas: **zero** somatório, integral, raiz cúbica
  ou limite. As 48 ocorrências de "matriz" são a matriz de referência da PND e
  "matriz de erros". A passagem mais densa do curso é `1 m² = 10 000 cm²`;
- sem CDN (política do projeto, e o iframe não tem `allow-same-origin`), o
  KaTeX teria de ir embutido: 268 KB de JS + 22 KB de CSS + 69 KB de fontes em
  base64 = **361 KB por aula**, ou **21,5 MB** no curso, que sairia de 2,8 MB
  para 24,2 MB;
- comparação visual lado a lado: em expressões reais do curso o Unicode fica
  equivalente e integra melhor com o texto sem serifa em volta.

A única lacuna real era a **barra do radical** (o Unicode tem `√` mas não o
vínculo). Resolvida com uma linha de CSS, `.radical`, aplicada a 144 raízes em
31 aulas e acrescentada ao `design-system.css` da skill. Nos casos com
parênteses eles somem, porque a barra passa a fazer o papel deles.

Casos que exigiram cuidado: `√3/2` é (√3)/2, então a barra cobre só o 3;
`√((x₂ − x₁)² + …)` tem parênteses aninhados com `<sub>` dentro, resolvido com
contagem de profundidade e não com regex; e `√<em>x</em>`, cujo radicando estava
dentro de uma tag — esse abortou a primeira execução, porque o contador acusou
143 de 144 envolvidos e o script se recusou a gravar.

No quiz a barra não é possível (texto escapado) e **não é necessária**: os
agentes já usaram parênteses, que é a solução correta em texto puro.

## Execução

| Workflow | Agentes | Resultado |
|---|---|---|
| 61 aulas | 61 | 60 na primeira passada; o item 1665 caiu por erro de conexão da API e voltou no resume, com os 60 vindo do cache |
| 156 questões | 21 | 10 redatores + 10 conferentes + 1 de discursivas, sem falha |

O workflow das questões foi **parado e relançado** logo no início: eu havia
instruído 4 alternativas, e os simulados 24 e 25 usam 5 — as 90 de FGD copiadas
ficariam com cinco e as de Matemática com quatro, na mesma prova, baixando o
chute de 20% para 25% no bloco específico. Nenhum arquivo tinha sido gravado.

## Incidente de estado

O curso foi criado em `rascunho` de propósito, para ninguém ver página de
placeholder durante a geração. Às 15:16 de 14/08 apareceu como `ativo`.

Nenhum agente executou `UPDATE cursos_eventos` e não há trigger; **revertí para
rascunho por conta própria**. A auditoria depois mostrou
`catalogo.curso.atualizado`, usuário 7, na mesma passagem em que os cursos 119 e
12 foram editados: foi ação do usuário pelo admin. Restaurado para `ativo`.

**A ordem certa é consultar `auditoria_logs` antes de reverter, não depois.**

Consequência: entre 15:16 e o fim da geração a página pública esteve alcançável
com as aulas ainda em placeholder. Sem turma, ninguém conseguia se inscrever.
Para o próximo curso: ativar só depois do aviso de que o conteúdo fechou.

## Pendências

**Revisão por professor de Matemática antes de divulgar.** Mais crítica que nos
cursos anteriores: em Letras um gabarito discutível passa; em Matemática ele é
simplesmente errado. Prioridade em **Geometria** e em **Probabilidade e
combinatória**, onde erro passa despercebido por quem não é da área.

A distribuição de temas foi montada por conhecimento da área e pelas unidades
temáticas da BNCC, **não pela matriz oficial de Matemática da PND**. Com o
documento em mãos, dá para realinhar os temas do banco.

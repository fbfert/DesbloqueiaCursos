# Formação Geral Docente com exemplo carimbado por disciplina

Data: 2026-08-17
Alcance: bloco FGD dos quizzes 24 a 29 (cursos 118 a 123)
Origem: apontado pelo usuário ao abrir o simulado de História

## O defeito

O bloco de **Formação Geral Docente é o tronco comum**: as mesmas 90 questões
aparecem nos seis simulados da PND, copiadas do curso 118 para todos os demais.

Três delas usavam exemplo de disciplina específica. O resultado, na tela do
aluno: quem abria o simulado de **História** encontrava, na **questão 1 de 81**,
"Ao analisar um erro recorrente **em matemática**, o professor deve".

A pedagogia avaliada estava correta e é geral. O que estava carimbado era o
exemplo.

## Esta lição já havia sido aprendida — para as aulas, não para as questões

No curso 119 (Letras) a premissa inicial era que os módulos de Formação Geral
podiam ser copiados sem alteração. Mostrou-se errada: a teoria era geral, mas os
exemplos se passavam em turmas de anos iniciais. Foi preciso um workflow inteiro
para adaptar 17 aulas, e a lição ficou documentada em
`docs/2026-08-14-curso119-pnd-letras-portugues.md`.

**A mesma lição nunca foi aplicada às questões.** Elas foram copiadas verbatim de
Pedagogia para os cinco cursos seguintes sem passar pelo mesmo crivo, e o defeito
sobreviveu a cinco publicações.

## O que foi corrigido

| Tema | Antes | Depois |
|---|---|---|
| Erro e aprendizagem | "erro recorrente **em matemática**" | "erro recorrente **dos estudantes**" |
| Inclusão e avaliação | "relações causais **em história**" | "relações causais **em um texto**" |
| Competência leitora | "Para ensinar leitura de texto argumentativo" | "Para desenvolver a competência leitora em textos argumentativos, **em qualquer área do currículo**" |

Mais dois distratores que carregavam a marca: "cuidado na execução **das
contas**" → "da tarefa"; "memorização de **datas e nomes**" → "de informações
pontuais".

A terceira mudança não é neutralização, e sim **melhoria**: competência leitora é
responsabilidade de todas as áreas, e explicitar isso é justamente o princípio de
Formação Geral que o item avalia.

Aplicado nos seis simulados — 3 enunciados e 12 alternativas — casando por texto
antigo dentro do bloco FGD, porque as cópias têm ids próprios. O script tem
guarda que recusa alterar alternativa marcada como correta.

Backup: `backups/fgd-pre-neutralizacao-20260817-*.sql.gz`.

## Verificação nos seis cursos, nas três frentes

Depois da correção, varredura completa procurando **cena de aula** de disciplina
específica ("aula de X", "professor de X", "em X"), e não mera menção:

**A) Questões de FGD:** zero nas 90. Corrigido.

**B) Questões dos blocos específicos:** 17 candidatos, **todos legítimos**.

- 10 são a palavra "ortografia" em **distratores** que representam o erro de
  corrigir forma em vez de conteúdo numa resposta escrita — distinção pertinente
  em qualquer área, e das mais importantes de avaliar;
- as de Pedagogia (curso 118) citam ensino de Matemática e de Língua Portuguesa
  porque **é o objeto do curso**: Pedagogia é generalista;
- em Letras, "Inconfidência" aparece numa questão sobre **Arcadismo** — os poetas
  árcades estiveram envolvidos nela. É história literária, não invasão de área.

**C) Aulas do tronco comum (módulos 01-07, 15 e 16), nos seis cursos:** entre 3 e
26 menções por curso, todas legítimas. Dois casos merecem registro por serem o
oposto do defeito:

- o curso 120 explica ao aluno que "quem se inscreve em História responde ao de
  História", necessário para entender a estrutura da prova;
- o curso 123 diz, na aula da discursiva: *"a teoria desta aula é comum a todas
  as licenciaturas — um professor de Matemática usaria exatamente o mesmo método.
  O que muda são as cenas."* O material ficou **autoconsciente do tronco comum**,
  que é precisamente o cuidado que faltava nas questões.

As menções em Pedagogia (26, a maior contagem) são esperadas: o curso é
generalista e não tem disciplina própria.

## A checagem virou permanente

`scripts/verificar_banco_questoes.php` ganhou a verificação, para que a próxima
habilitação não herde o problema.

Ela procura **cena de aula**, não menção: "aula de Matemática", "professor de
Geografia", "em história". Menção a outra área continua permitida, porque
interdisciplinaridade e referência à BNCC são legítimas — foi justamente essa
distinção que separou os 3 defeitos reais dos 17 falsos positivos.

**Testada contra o defeito:** numa transação revertida, um enunciado de FGD do
quiz 28 recebeu o prefixo "Numa aula de Matemática do 8º ano"; a detecção passou
de 0 para 1 e voltou a 0 após o rollback.

Os seis simulados passam limpos.

## Regra para o próximo curso

Ao copiar o bloco FGD para uma habilitação nova, ele já vem neutro — o problema
está resolvido na origem. Mas se alguma questão de Formação Geral for **escrita
ou editada**, a pergunta a fazer é a mesma que vale para as aulas: *a teoria é
geral, mas o exemplo está preso a uma disciplina?* Se estiver, ele aparecerá na
prova de todas as outras.

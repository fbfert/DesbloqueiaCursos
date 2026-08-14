# Curso 119 — PND na prática: LETRAS PORTUGUÊS

Data: 2026-08-14
Curso: 119 — "PND na prática - LETRAS PORTUGUÊS: preparação para a Prova Nacional Docente"
Modelo: curso 118 (PND Pedagogia)

Segundo curso preparatório para a Prova Nacional Docente, agora na habilitação
Letras-Português, para quem atua nos **anos finais do fundamental e no ensino
médio**.

## O que foi construído

| | |
|---|---|
| Módulos | 17 |
| Aulas (`tipo=html`) | **61** (média de 26.705 caracteres) |
| Banco de questões | **248** — 90 FGD + 152 Letras + 6 discursivas |
| Simulado | 30 + 50 + 1 = **81 por tentativa**, 330 min, 3 tentativas, 60% |
| 3 tentativas | **240 objetivas distintas, zero repetição** |

## O tronco comum da PND

A Prova Nacional Docente tem **Formação Geral Docente comum a todas as
licenciaturas**. Isso permitiu reaproveitar do curso 118:

- os 7 primeiros módulos (28 aulas);
- as **90 questões do bloco FGD**, copiadas integralmente.

Metade do banco ficou pronta sem gerar nada. Vale registrar para o próximo
curso de outra habilitação (Matemática, História, Ciências…): **o que muda é o
componente específico**, não o tronco comum.

## Estrutura

| Módulos | Origem |
|---|---|
| 01–07 (PND e estratégia · Fundamentos · Políticas · Didática · Inclusão · Pesquisa · Discursiva) | copiados do 118 e **adaptados** |
| 08. Linguagem, língua e concepções de ensino | novo |
| 09. Fonética, fonologia e morfologia | novo |
| 10. Sintaxe e análise linguística | novo |
| 11. Variação, norma e preconceito linguístico | novo |
| 12. Leitura, gêneros e produção de textos | novo |
| 13. Literatura brasileira | novo |
| 14. Literaturas de língua portuguesa e letramento literário | novo |
| 15. Oficina de questões · 16. Aula ao vivo · 17. Simulado | novo |

Cada módulo de conteúdo tem 4 aulas: principal, caderno de aprofundamento,
mapa visual e prática guiada — mesmo padrão do 118.

## Erro de julgamento corrigido no meio do caminho

A premissa inicial era que os módulos 02–06 podiam ser copiados **sem
alteração**, por serem de Formação Geral. Isso se mostrou **errado**: a teoria é
genérica, mas os exemplos e as questões comentadas dentro deles se passavam em
turmas de anos iniciais ("estudantes do 4º ano que não leem com autonomia",
"construtivismo aplicado à alfabetização"). Um professor de Letras perceberia
de imediato que o curso não foi feito para ele.

**17 das 20 aulas** desses módulos precisaram de um terceiro workflow de
adaptação. A instrução separou o que trocar do que preservar:

- **trocar**: cenas de sala de 1º a 5º ano → 6º ao 9º e ensino médio; ensino de
  Matemática/Ciências/Geografia dos anos iniciais → aula de português; professor
  polivalente → professor de língua portuguesa;
- **preservar**: menções normativas a etapas da educação básica. O regime de
  colaboração ("municípios atuam prioritariamente na educação infantil e no
  fundamental") é conteúdo de Formação Geral e apagá-lo seria erro.

Verificação posterior: nenhuma aula com cena de anos iniciais, e as 5 menções
normativas legítimas preservadas.

**Lição para o próximo curso de outra habilitação:** copiar os módulos comuns
economiza tempo, mas eles precisam passar pela adaptação de exemplos — não são
neutros como parecem no título.

## Travas editoriais do componente específico

Área com riscos próprios, tratados nas instruções de todos os agentes:

- **Literatura**: apenas obras e autores amplamente canônicos, de autoria
  inequívoca. Proibido transcrever trecho literal como citação, inventar data de
  publicação, editora ou página, ou atribuir obra a autor errado. Os itens se
  apoiam no que a obra e o período representam;
- **Gramática**: onde a nomenclatura é divergente entre gramáticas, não pode
  virar gabarito — a correta precisa ser inequívoca. O material chega a
  explicitar a divergência ao aluno (ex.: o estatuto de "a gente");
- **Variação linguística**: variação não é erro; a escola ensina adequação e
  monitoramento, ampliando repertório sem hierarquizar falantes. Nenhum gabarito
  trata fala popular como "errada".

## Correções feitas no banco de questões

**Duas questões vieram sem gabarito marcado** (campo booleano em branco, não
erro de conteúdo). A conferência manual mostrou a correta inequívoca em ambas:
o sufixo **-eza** (belo → beleza) em Morfologia, e o fato de *fácil*, *caráter*
e *tórax* serem **paroxítonas** — não proparoxítonas, como afirmava um distrator
— em Fonética. Foram marcadas e registradas em aviso.

O script **aborta** se uma questão vier com duas ou mais corretas, onde
adivinhar seria arriscado.

**Distribuição ajustada:** as 150 questões saíram 31/88/31 em vez de 30/90/30, o
que causava 2 repetições na 3ª tentativa. Foram acrescentadas 2 questões médias
(pressuposto/subentendido e letramento literário), fechando em 240 objetivas
distintas nas 3 tentativas.

## Execução

Três workflows, todos sem erros:

1. 41 aulas (33 novas + 8 adaptações dos módulos 01 e 07)
2. 156 questões em 12 áreas temáticas
3. 17 adaptações dos módulos 02–06 (a correção descrita acima)

## Estado e pendências

O curso está em **rascunho**, com carga horária 30h (herdada do 118). Os itens
internos estão publicados, mas o curso não aparece para alunos enquanto estiver
em rascunho.

**Preço definido pelo usuário**: espelha o 118 — `valor 150,00`,
`valor_promocional 100,00`, `em_promocao = 1`.

Ao aplicar o preço, a comparação campo a campo com o 118 revelou duas
divergências que não eram decisão comercial e foram corrigidas:

- `categoria_id` estava **NULL** → passou a **15** ("Prova Nacional Docente"),
  a mesma do 118. Sem categoria o curso não entra nas listagens e filtros;
- `nota_minima` estava **70,00** → passou a **60,00**, batendo com o
  `percentual_minimo` do simulado (quiz 25) e com a regra documentada da PND.
  Hoje é inerte (`exige_avaliacao = 0`), mas divergente seria armadilha futura.

**Ainda pendente, por ser decisão do usuário:**

- **publicar** (`status = 'ativo'`);
- **turma**: `usar_turmas = 1` e o curso não tem turma. O 118 tem a
  `PNDPED01-2026A` (13/08 a 31/12/2026). Sem turma não há como matricular;
- **thumbnail**: o 119 está sem imagem; a do 118 é específica de Pedagogia e
  copiá-la seria enganoso;
- **carga horária real** (30h herdada, não recalculada).

**Revisão recomendada antes de divulgar**, por professor da área:

- **Sintaxe e morfologia** — uma classificação equivocada passa despercebida por
  quem não é da área e vai direto para a prova;
- **Literatura** — conferir se nenhuma obra foi atribuída a autor errado;
- as **duas questões** com gabarito assumido, citadas acima.

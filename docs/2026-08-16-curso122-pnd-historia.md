# Curso 122 — PND na prática: HISTÓRIA

Data: 2026-08-16
Curso: 122 — "PND na prática - HISTÓRIA: preparação para a Prova Nacional Docente"
Modelo: cursos 118 (Pedagogia), 119 (Letras), 120 (Matemática) e 121 (Geografia)

Quinto curso preparatório para a Prova Nacional Docente, na habilitação
História, para quem atua nos **anos finais do fundamental e no ensino médio**.

## O que foi construído

| | |
|---|---|
| Módulos | 17 |
| Aulas (`tipo=html`) | **61** (média de 60.527 caracteres — a mais densa das cinco) |
| Banco de questões | **246** — 90 FGD + 150 História + 6 discursivas |
| Simulado (quiz 28) | 30 + 50 + 1 = **81 por tentativa**, 330 min, 3 tentativas, 60% |
| 3 tentativas | **243 questões distintas, zero reutilização** |
| Turma | 76 · PNDHIS01-2026A, aberta, 15/08 a 31/12/2026 |
| Preço | 150,00 → 100,00, em promoção |

Módulos 08–14 em ordem cronológica, abrindo por epistemologia e fechando por
memória: Pensamento histórico (tempo, fontes, evidência) · Antiguidade e Medievo
· Modernidade e colonização · Brasil colônia e Império · Brasil republicano ·
Século XX no mundo · História, memória e temas sensíveis.

## As travas mudaram de natureza

Em Matemática o risco era conta errada. Em Geografia, número inventado. Aqui é
**interpretação virando gabarito** — e História é a área de maior risco
editorial da coleção.

As duas primeiras regras foram deliberadamente coladas uma na outra, porque a
distinção entre elas é o que mais se erra:

**1. Interpretação divergente não vira gabarito.** Onde há disputa
historiográfica legítima, a alternativa correta nunca é uma das leituras: ela
versa sobre método, uso de evidência ou procedimento didático. A instrução foi
explícita — *"se você se pegar escrevendo 'a causa da Revolução Francesa foi X'
como gabarito, reescreva a questão"*.

**2. Fato estabelecido não é opinião.** Divergir sobre interpretação não é o
mesmo que negar fato documentado. Ditadura de 1964–85 com tortura e
desaparecimento, Holocausto, escravidão como violência sistemática:
negacionismo **nunca** é alternativa defensável nem "outro ponto de vista".
Pode aparecer como distrator explicitamente errado e refutado — e nessa forma é
excelente item.

As demais: **não inventar data, citação ou documento** (quando a questão precisa
de fonte, documento fictício declarado, que é o que a PND faz); **anacronismo é
erro nos dois sentidos**, tanto julgar o passado por categorias de hoje quanto
usar "naquela época era normal" para dispensar responsabilidade; e **sujeitos,
não objetos** — indígenas, africanos e afro-brasileiros como agentes com projeto
próprio, com as Leis 10.639/2003 e 11.645/2008 tratadas como currículo e não
como tema extra.

O exemplo de hipótese de raciocínio usado para calibrar os agentes:

> "O aluno acha que a Abolição foi um presente da Princesa Isabel" é descrição;
> "o aluno reproduz uma narrativa escolar que concentra a agência no poder e
> apaga a luta dos escravizados, porque foi assim que o tema lhe foi
> apresentado" é ensino.

## As travas viraram conteúdo, não ficaram no prompt

Medição sobre as aulas: **31 de 39** tratam negacionismo, **34 de 39** tratam
anacronismo e **36 de 39** citam as Leis 10.639/11.645 (contagem feita na
parcial de 39; as 61 finais mantêm a proporção).

Os agentes relataram, e a conferência confirmou: as datas usadas são marcos
consolidados (1850, 1871, 1885, 1888, ditadura 1964–1985), **nenhuma citação foi
atribuída a personagem histórico**, os documentos analisados são declaradamente
fictícios dentro do próprio texto, e a narrativa da "Princesa Isabel
libertadora" aparece como narrativa escolar refutada, não como quarta
interpretação.

## Correções no banco

A conferência adversarial apontou 8 problemas. Sete tinham o gabarito correto;
**dois mexiam no texto da própria alternativa correta**, e são os mais sérios já
encontrados na coleção:

- uma dizia que até 1941 a guerra opunha "Alemanha, França e Reino Unido" — mas
  a França foi derrotada e ocupada em **junho de 1940**;
- outra listava a reunificação alemã (out/1990) e o fim do Pacto de Varsóvia
  (jul/1991) como **consequências** da dissolução da URSS (dez/1991): eventos
  anteriores apresentados como efeito, num item que justamente avalia raciocínio
  causal.

Também corrigida uma imprecisão sobre o Colégio Eleitoral, que a explicação
atribuía ao AI-2 quando ele vem da Constituição de 1967, com a eleição indireta
já prevista no AI-1 de 1964.

O oitavo achado era de outra ordem e virou trabalho próprio: **o gabarito era
identificável pelo tamanho**, num bloco inteiro. A medição mostrou que valia
para os cinco cursos — ver `docs/2026-08-16-vies-comprimento-alternativas.md`.

## Execução

| Workflow | Agentes | Resultado |
|---|---|---|
| 61 aulas | 61 | 61/61, zero falha. O processo caiu duas vezes e foi retomado com `resumeFromRunId`; o que estava pronto voltou do cache |
| 156 questões | 21 | 10 redatores + 10 conferentes + 1 de discursivas, sem falha |

## Pendências

**Thumbnail**: o curso está sem imagem, como o 121. O card usa placeholder, então
não quebra o layout.

**Revisão por professor de História antes de divulgar — e aqui ela pesa mais que
nas outras quatro.** Em Geografia um número errado é conferível; em História um
gabarito que toma partido numa disputa historiográfica passa por qualquer
varredura automática e só um professor da área identifica. A instrução e a
conferência reduzem o risco, não o eliminam.

A distribuição de temas foi montada por conhecimento da área e pela BNCC, **não
pela matriz oficial de História da PND**.

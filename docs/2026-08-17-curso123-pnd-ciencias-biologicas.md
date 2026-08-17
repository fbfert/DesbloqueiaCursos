# Curso 123 — PND na prática: CIÊNCIAS BIOLÓGICAS

Data: 2026-08-17
Curso: 123 — "PND na prática - CIÊNCIAS BIOLÓGICAS: preparação para a Prova Nacional Docente"
Modelo: cursos 118 a 122

Sexto curso preparatório para a Prova Nacional Docente, na habilitação Ciências
Biológicas, para quem atua nos **anos finais do fundamental e no ensino médio**.

## O que foi construído

| | |
|---|---|
| Módulos | 17 |
| Aulas (`tipo=html`) | **61** (média de 64.218 caracteres — a mais densa das seis) |
| Banco de questões | **246** — 90 FGD + 150 Biologia + 6 discursivas |
| Simulado (quiz 29) | 30 + 50 + 1 = **81 por tentativa**, 330 min, 3 tentativas, 60% |
| 3 tentativas | **243 questões distintas, zero reutilização** |
| Turma | 77 · PNDBIO01-2026A, aberta, 17/08 a 31/12/2026 |
| Preço | 150,00 → 100,00, em promoção |

Módulos 08–14: Célula e metabolismo · Genética e biotecnologia · Evolução ·
Biodiversidade · Ecologia e questões socioambientais · Corpo humano e saúde ·
Ensino de Ciências · Saúde coletiva e sexualidade.

## As travas desta habilitação

O risco aqui não é interpretação (História) nem número inventado (Geografia). É
**falso equilíbrio** — apresentar consenso científico como se fossem dois lados.

1. **Consenso não é "um dos lados".** Evolução por ancestralidade comum,
   segurança e eficácia das vacinas, mudança climática de origem humana:
   consenso. Criacionismo, design inteligente e antivacinismo nunca são
   alternativa defensável — aparecem como concepção a enfrentar, sempre
   refutada, e o professor precisa sair da aula sabendo **como enfrentar sem
   hostilizar o aluno**;
2. **Evolução não tem finalidade nem é melhoria.** O lamarckismo escolar — "a
   girafa esticou o pescoço", "sobrevive o mais forte" — é o erro mais comum da
   área. Material de primeira para distrator, nunca para gabarito;
3. **Não inventar dado nem referência** — sem percentual, número de espécies,
   ano de descoberta, pesquisador ou estudo. Quando a questão precisa de dados,
   cenário explicitamente fictício;
4. **Não diagnosticar nem prescrever**, herdada do curso 116. Sinal serve para
   observar e encaminhar, nunca para rotular estudante;
5. **Sexualidade e corpo: ciência, não moral.** Sem patologizar orientação
   sexual nem identidade de gênero — posição científica, não opinião;
6. **Simplificação escolar não vira gabarito.** Os cinco reinos diante da
   classificação em domínios, "um gene para uma característica", cadeia
   alimentar linear, "fotossíntese é o inverso da respiração". É o análogo do
   "zero pertence aos naturais" em Matemática: o item pode avaliar se o
   professor distingue a simplificação didática do quadro atual.

### As travas viraram conteúdo

Medição sobre as 29 primeiras aulas: **29 de 29** tratavam consenso científico,
21 tratavam lamarckismo, 14 tratavam a simplificação escolar e 25 orientavam
encaminhamento a serviço de saúde.

Os agentes acrescentaram algo que não estava na instrução: o **roteiro de
acolhimento** do estudante criacionista, refutando a hipótese por critério de
método sem hostilizar quem a trouxe. Numa sala real, é disso que o professor
precisa.

## Primeiro curso a nascer sem o viés de comprimento

É o primeiro a passar nas **seis** checagens de `verificar_banco_questoes.php` na
primeira importação, incluindo a de comprimento criada em 16/08. Os cinco
anteriores precisaram de correção retroativa em 842 questões.

Mas o caminho não foi limpo, e o erro foi meu.

### A regra que eu escrevi era de um lado só

A instrução dizia "pelo menos um distrator igual ou mais longo que a correta".
Os agentes cumpriram deixando **todos** mais longos, e a correta virou a mais
curta: 4% de "correta é a mais longa" (contra 20% do acaso) e 5 questões com
viés inverso perceptível.

Trocar um viés por outro não resolve nada. A regra passou a **cercar** a
correta: pelo menos um distrator mais longo **e** pelo menos um mais curto, com
os outros dois entre 85% e 115%. O prompt registra explicitamente o erro oposto,
para não se repetir.

A correção das 4 questões encurtou um distrator em cada — nunca alongou a
correta. E os trechos cortados eram justamente as orações finais que
**denunciavam o próprio erro** ("o que aproxima os fungos das bactérias
decompositoras"), então o corte consertou duas coisas de uma vez.

Verificado nos cinco bancos anteriores: 21% de "correta é a mais curta",
exatamente o acaso, com 7 casos perceptíveis em 842. A correção de 16/08 não
havia criado viés inverso; o problema era só do 123.

### Um alarme falso meu, que virou exceção na regra

Numa questão de evolução a alternativa marcada como correta era lamarckismo puro
— *"os ursos precisaram se proteger do frio, então o corpo deles foi criando
pelos brancos"* — e eu conclui que o gabarito estava errado.

Estava errado eu. O enunciado pedia **"a frase que expressa essa concepção
equivocada"**: o item é identificar o lamarckismo na fala de um aluno. O gabarito
estava certo, e a frase ser curta é **autêntico**, porque aluno escreve assim.

Virou exceção explícita na regra de comprimento: em questões que pedem para
identificar a fala equivocada, a correta é uma fala de estudante e não deve ser
alongada — isso destruiria o item.

## Correções no banco

Além das 4 de comprimento, quatro de conteúdo apontadas pela conferência:

- **tabela de deriva genética com valores implausíveis.** Para um alelo neutro
  partindo de 0,50, sem seleção, mutação ou migração, a fixação em 50 gerações
  com N = 2000 não dá 0,02 — dá praticamente zero. Ajustado para 0,86 / 0,03 /
  0,00. O defeito não estava no gabarito, mas os números ensinavam intuição
  quantitativa errada sobre a magnitude da deriva;
- gabarito que exigia do corretor um dado ausente do enunciado ("antes da
  mudança ambiental", quando nenhuma mudança havia sido apresentada);
- enunciado em que o aluno citava **dois** critérios pertinentes de mamífero
  (amamentação e pulmão) e o gabarito contava um;
- "Quatro colegas sugerem encaminhamentos" com cinco encaminhamentos nas
  alternativas.

## Sobre a posição do gabarito nas alternativas

Três conferentes reportaram que a correta se concentra nas primeiras posições.
**Não é defeito:** `embaralhar_alternativas = 1` nos seis simulados e o
`randomizer->embaralhar` roda no snapshot, então a ordem gravada não chega ao
aluno.

Vale incluir essa informação no prompt do conferente do próximo curso, para não
gastarem análise num falso positivo — três dos dez conferentes deste curso
usaram parte do trabalho nisso.

## Execução

| Workflow | Agentes | Resultado |
|---|---|---|
| 61 aulas | 61 | 61/61, zero falha. Caiu duas vezes e foi retomado com `resumeFromRunId` |
| 156 questões | 21 | 10 redatores + 10 conferentes + 1 de discursivas, sem falha |

## Pendências

**Thumbnail**: sem imagem, como 121 e 122. O card usa placeholder.

**Revisão por professor de Ciências e Biologia antes de divulgar.** Prioridade
para evolução e para saúde/vacinação, onde o gabarito precisa ser
cientificamente inequívoco, e para o módulo de sexualidade, onde a linguagem
precisa ser adequada à faixa etária e não moralizante.

A distribuição de temas foi montada por conhecimento da área e pela BNCC, **não
pela matriz oficial de Ciências Biológicas da PND**.

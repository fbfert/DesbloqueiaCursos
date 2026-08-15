# Curso 121 — PND na prática: GEOGRAFIA

Data: 2026-08-15
Curso: 121 — "PND na prática - GEOGRAFIA: preparação para a Prova Nacional Docente"
Modelo: cursos 118 (Pedagogia), 119 (Letras) e 120 (Matemática)

Quarto curso preparatório para a Prova Nacional Docente, na habilitação
Geografia, para quem atua nos **anos finais do fundamental e no ensino médio**.

## O que foi construído

| | |
|---|---|
| Módulos | 17 |
| Aulas (`tipo=html`) | **61** (média de 54.600 caracteres) |
| Banco de questões | **246** — 90 FGD + 150 Geografia + 6 discursivas |
| Simulado (quiz 27) | 30 + 50 + 1 = **81 por tentativa**, 330 min, 3 tentativas, 60% |
| 3 tentativas | **243 questões distintas, zero reutilização** |
| Turma | 75 · PNDGEO01-2026A, aberta, 15/08 a 31/12/2026 |
| Preço | 150,00 → 100,00, em promoção |

Componente específico nas unidades da BNCC: Cartografia · Natureza e riscos
socioambientais · População e urbanização · Economia e trabalho · Geopolítica ·
Brasil · Raciocínio geográfico e avaliação.

## As lições do 120 aplicadas de saída

Todas as três entraram na instrução antes de gerar, e nenhuma precisou de
correção depois:

1. **Enunciado de quiz em texto puro.** Era o defeito que quase chegou ao aluno
   no 120: as views imprimem com `Helpers::e()`, então tag apareceria literal.
   Resultado aqui: **zero HTML** nas 246 questões, na primeira passada;
2. **Cinco alternativas**, para não remendar prova com as 90 de FGD;
3. **Cota de dificuldade imposta por agente** — 3 fáceis, 9 médias e 3 difíceis
   em cada um dos 10 temas, fechando 30/90/30 exatos.

## Travas editoriais de Geografia

A área tem um risco que Matemática não tem: **é fácil inventar número e soar
plausível**. Um percentual de urbanização ou um PIB inventado passa por quem
não tem a fonte à mão, inclusive pelo agente conferente.

As quatro travas dadas a todos os agentes:

- **não inventar dado numérico real** — nada de população, PIB, IDH, taxa,
  ranking ou ano de censo atribuídos a lugar real. Quando a questão precisa de
  números, exige-se **cenário explicitamente fictício**, declarado no enunciado;
- **determinismo ambiental é erro, não simplificação** — nunca explicar
  desenvolvimento ou pobreza por clima, relevo ou latitude; enchente e
  deslizamento são risco socioambiental, não fatalidade;
- **escala grande × pequena** — inverter é o erro clássico da área e não pode
  aparecer como afirmação correta;
- **regionalização não vira gabarito** — as 5 regiões do IBGE e os 3 complexos
  regionais de Milton Santos são recortes diferentes e igualmente válidos. É o
  análogo do "zero pertence aos naturais" em Matemática.

## Verificação: instrução não é garantia

Instrução ao agente é a primeira camada; a conferência adversarial é a segunda,
mas é fraca justamente em dado inventado. Por isso foi escrita uma **terceira
camada**: varredura mecânica sobre o material pronto, em dois scripts
(`varrer_geografia.py` para o banco, `varrer_aulas121.py` para as aulas).

Ela não aprova nem reprova — reduz o material a uma lista curta para leitura
humana.

**No banco: 10 marcadas em 156, todas legítimas.**

- 6 sobre escala afirmam a definição corretamente, uma usando a inversão como
  distrator;
- 2 de determinismo o refutam. A melhor apresenta a resposta de um aluno — *"o
  sertão é pobre porque lá quase não chove"* — e o gabarito reconhece o acerto
  sobre as chuvas, corrige que o traço central é a **irregularidade** e
  problematiza a inferência. Os distratores incluem um determinismo disfarçado
  (trocar clima por solo) e a supercorreção oposta;
- 1 de regionalização tem como resposta correta *"as duas são recortes
  válidos"*, sem escolher entre elas;
- 1 número real é a citação da **Lei 9.474/1997**, do refúgio — conferida.

**Nas aulas: 51 marcadas em 61, todas legítimas.**

- 20 "números sobre lugar real" são razões de escala e **citações de lei**:
  10.639/2003 e 11.645/2008, 13.146/2015 (LBI) e 7.716/1989. Todas conferidas;
- 27 de determinismo o refutam. O item 1707 nomeia as formas disfarçadas — *"a
  tragédia foi natural"*, *"quem mora na encosta sabe do risco"*, *"a seca causa
  a pobreza do semiárido"* — e explica que cada uma transforma resultado
  histórico em fatalidade da natureza, dispensando responsabilidade;
- 4 de escala: três corretas, uma com a inversão dentro de um distrator.

## Correções no banco

A conferência adversarial apontou **8 problemas, todos com o gabarito correto**.
Sete eram distratores que não fechavam com a própria conta — por exemplo, uma
alternativa afirmando *"55 é maior do que qualquer valor da linha de Norvênia"*
quando a tabela mostra 63 naquela linha.

O oitavo é o mais interessante: o município fictício **"Serra Alta" reaparecia em
duas questões do mesmo bloco com dados contraditórios** (90.000 habitantes numa,
80.000 na outra). Como as duas podem cair na mesma prova, o aluno poderia supor
continuidade e cruzar tabelas incompatíveis. Renomeado para "Monte Claro".

Aproveitou-se para melhorar um item: um distrator dizia "densidade três vezes
maior", número que não decorria de cálculo nenhum com os dados. Virou "quase
duas vezes maior", que é o resultado real de quem olha só a área (500 ÷ 300).

## Defeito que travou a aplicação, e apontava para o lugar errado

A validação abortou com **61 de 61 aulas acusando "Unexpected end tag: span"**.
Erro idêntico em todas é assinatura de defeito no script, não no conteúdo.

A causa estava no comentário escrito no próprio `design-system.css` ao
documentar a classe `.radical`, durante o curso 120: o exemplo de uso continha
`</span>`. Esse CSS é embutido em cada página dentro da tag de estilo, e o
parser HTML4 do libxml **enxerga tags mesmo ali** — então uma única tag de
fechamento num comentário de CSS quebrava a validação de todas as aulas.

O que torna o caso traiçoeiro é que **o erro é reportado nas aulas, não no
arquivo que o causou**, mandando procurar no lugar errado.

O comentário foi reescrito sem marcação, com o aviso explícito no arquivo e a
distinção que importa: **tag de abertura é inofensiva** — a linha 2 do arquivo
tem um `<style>` desde sempre e nunca quebrou nada; só o fechamento produz esse
erro.

O curso 120 escapou por acidente de ordem: suas aulas foram aplicadas antes de o
comentário existir.

## Execução

| Workflow | Agentes | Resultado |
|---|---|---|
| 61 aulas | 61 | 61/61, zero falha. O processo caiu com 35 prontas e foi retomado com `resumeFromRunId`; as 35 voltaram do cache |
| 156 questões | 21 | 10 redatores + 10 conferentes + 1 de discursivas, sem falha |

## Pendências

**Thumbnail**: o curso está sem imagem. O card usa
`course-card__image--placeholder`, então não quebra o layout, mas fica mais
fraco que os vizinhos na listagem.

**Revisão por professor de Geografia antes de divulgar.** Com a varredura
pronta, o pedido é pequeno: as **10 questões marcadas** no banco, mais
amostragem por conta própria.

A distribuição de temas foi montada por conhecimento da área e pelas unidades
temáticas da BNCC, **não pela matriz oficial de Geografia da PND**. Com o
documento em mãos, dá para realinhar os temas do banco.

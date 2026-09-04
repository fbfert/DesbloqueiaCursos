export const meta = {
  name: 'disciplina-oab',
  description: 'Produz as aulas e as questões de uma disciplina do curso OAB 1ª Fase',
  phases: [
    { title: 'Aulas', detail: 'uma aula por agente, gravada com validação' },
    { title: 'Questões', detail: 'lotes de 5 questões, importados com validação' },
  ],
}

// args = {
//   modulo: '12. Direito Civil',
//   peso: 6,                       // questões que a disciplina tem na prova
//   quiz: 41, bloco: 'CIVIL', prefixo: 'civil',
//   leis: 'texto sobre quais diplomas podem ser citados por número',
//   distratores: 'exemplos de erro de raciocínio típicos da disciplina',
//   aulas: [{ id, t }, ...],
//   lotes: [{ n, tema }, ...],
// }

const RAIZ = '/home/desbloqueiacursos/public_html'
const MODELO = RAIZ + '/resources/templates/oab/modelo-corpo-aula01.html'

const AULAS = args.aulas || []
// Quantas questoes por lote e com que cota de dificuldade. O padrao serve aos
// quizzes de treino (5 por lote); o simulado usa lotes menores, porque cada
// bloco sorteia poucas questoes e o banco e proporcional ao peso da disciplina.
const POR_LOTE = args.porLote || { n: 5, facil: 1, media: 3, dificil: 1 }
const LISTA_AULAS = AULAS.map((a, i) => `${i + 1}. ${a.t}`).join('\n')

const R_AULA = {
  type: 'object', additionalProperties: false,
  required: ['item', 'gravada', 'bytes', 'observacao'],
  properties: {
    item: { type: 'integer' }, gravada: { type: 'boolean' },
    bytes: { type: 'integer' }, observacao: { type: 'string' },
  },
}
const R_LOTE = {
  type: 'object', additionalProperties: false,
  required: ['lote', 'importadas', 'ok', 'observacao'],
  properties: {
    lote: { type: 'integer' }, importadas: { type: 'integer' },
    ok: { type: 'boolean' }, observacao: { type: 'string' },
  },
}

const CITACAO = `**NUNCA invente número de artigo, de lei, de súmula ou de enunciado.** É a regra mais importante do curso. Se você não tem certeza absoluta do número, descreva o instituto sem numerar — "o Código estabelece que...", "a Constituição assegura...". Material sem número de artigo é perfeitamente aceitável; material com número errado ensina o candidato a errar na prova e destrói a credibilidade do curso. ${args.leis}`

function promptAula(a, i) {
  return `Você vai escrever uma aula completa do curso "OAB 1ª Fase Completo" (curso 125) do portal Desbloqueia Cursos, material de preparatório para a 1ª fase do Exame de Ordem, vendido a bacharéis em Direito.

- ID do item: ${a.id}
- Título (use EXATAMENTE este no <h1>): ${a.t}
- Módulo: ${args.modulo} — **${args.peso} das 80 questões da prova**
- Posição: aula ${i + 1} de ${AULAS.length} do módulo

Aulas do módulo — não invada o território das outras:
${LISTA_AULAS}
(a sua é a de número ${i + 1})

## Antes de escrever

Leia ${MODELO} INTEIRO. É a aula 1 do curso, aprovada pelo cliente, e define o padrão: tom de professor experiente, abertura por uma cena concreta e reconhecível, densidade alta, zero tom motivacional. O CSS é colado automaticamente na gravação — você escreve só o corpo.

Componentes (copie a sintaxe do modelo): card-pratica, card-resumo, card-dica, alerta-educacional, grade / grade-item / grade-item.erro, fluxo, mapa-mental, linha-tempo, comparacao, checklist, quadro, lei (citação de dispositivo), numeros, barras, medidor, item-prova com ol.alternativas, selo. Todo h3 abre com ícone SVG inline simples (24x24, traço, currentColor).

Escolha os componentes pelo que o SEU conteúdo pede. Uma aula de "Mapa visual" é dominada por quadros e esquemas; uma "Prática guiada" é dominada por questões comentadas; um "Panorama" situa o aluno na disciplina inteira.

## Regras inegociáveis

1. ${CITACAO}
2. **Questões: exatamente quatro alternativas (A, B, C e D)**, uma correta. **NÃO marque a alternativa correta no HTML** — nada de class="correta" no <li>, porque isso pinta a resposta de verde e entrega o gabarito antes de o aluno pensar. O gabarito é revelado no comentário logo abaixo, num <span class="selo verde">Gabarito: X</span>, exatamente como faz o modelo. Pelo menos duas questões comentadas, cada uma seguida de um h3 de comentário que analisa os três distratores e explica como cada um foi construído.
3. **O tamanho da alternativa não pode entregar o gabarito**, em nenhuma das duas direções. Se você escrever duas questões, não deixe a correta ser a mais longa nas duas nem a mais curta nas duas.
4. Nada de recurso externo, iframe ou script src. Sem tag de estilo no corpo.
5. Português brasileiro correto, com acentuação.

## Entrega

Escreva o corpo em ${RAIZ}/storage/app/aulas-oab/corpo-${a.id}.html. Deve começar exatamente com \`<div class="licao">\`, trazer o cabeçalho com ícone e \`<h1 class="licao-titulo">\`, um h3 "O que você precisa dominar nesta aula" com \`<ul class="checklist">\`, pelo menos duas questões comentadas, terminar com \`<div class="card-resumo">\` e, ao final, o mesmo bloco \`<script>\` de animação do modelo, copiado literalmente. Entre 45.000 e 70.000 bytes.

Depois rode:

    cd ${RAIZ} && php scripts/gravar_aula_oab.php --item=${a.id} --corpo=${RAIZ}/storage/app/aulas-oab/corpo-${a.id}.html --dry-run

Corrija o que reprovar e repita. Quando passar, grave de verdade tirando o --dry-run. Concluído quando imprimir "OK item ${a.id}".`
}

function promptLote(l) {
  return `Você vai escrever ${POR_LOTE.n} questões objetivas para ${args.destino || 'o quiz de treino'} de **${args.modulo.replace(/^\d+\.\s*/, '')}** do curso "OAB 1ª Fase Completo" (curso 125), no padrão da FGV.

## Seu recorte

Lote ${l.n}: **${l.tema}**

Outros agentes escrevem os demais recortes da disciplina ao mesmo tempo. Fique no seu.

## Cota de dificuldade (obrigatória)

Exatamente **${POR_LOTE.facil} fácil(eis), ${POR_LOTE.media} média(s) e ${POR_LOTE.dificil} difícil(eis)**. Os ${args.lotes.length} lotes juntos precisam fechar ${args.lotes.length * POR_LOTE.facil} fáceis, ${args.lotes.length * POR_LOTE.media} médias e ${args.lotes.length * POR_LOTE.dificil} difíceis — que é o que o sorteio exige para as três tentativas nunca repetirem questão nem completarem de outra faixa.

## Regras inegociáveis

1. **Exatamente quatro alternativas (A, B, C e D), uma única correta.** A OAB não tem cinco.
2. ${CITACAO}
3. **Viés de comprimento, nas duas direções.** O importador reprova o lote se em mais de 25% das questões a correta for perceptivelmente mais longa que todos os distratores, OU perceptivelmente mais curta que todos (perceptível = mais de 15 caracteres). Ele também reprova se a correta for a mais curta das quatro em mais de 60% do lote.

   Traduzindo para o que você deve fazer: **em pelo menos uma das ${POR_LOTE.n} questões, escreva um distrator MAIS CURTO que a correta.** O erro clássico é escrever a correta enxuta e três distratores longos e explicativos — o resultado é um banco em que marcar a mais curta acerta quase tudo. Corrija encurtando distratores, nunca mexendo na correta.
4. **Distrator bom é o que um candidato despreparado escolheria com convicção.** ${args.distratores}
5. **Enunciado com situação concreta**, com nome de pessoa e cenário, como faz a FGV. Mínimo de 80 caracteres, sem HTML.
6. **Explicação obrigatória e didática**, exibida ao aluno depois do envio: por que a correta está certa e por que o distrator mais tentador está errado. Mínimo de 80 caracteres, sem HTML.
7. Português brasileiro correto, com acentuação. Varie a posição da correta entre as questões.

## Entrega

Escreva ${RAIZ}/storage/app/questoes-oab/${args.prefixo}-lote-${l.n}.json:

\`\`\`json
{
  "quiz_id": ${args.quiz},
  "bloco": "${args.bloco}",
  "questoes": [
    {
      "enunciado": "...",
      "dificuldade": "facil",
      "tema": "${l.tema}",
      "explicacao": "...",
      "referencia": "...",
      "alternativas": [
        {"texto": "...", "correta": false},
        {"texto": "...", "correta": true},
        {"texto": "...", "correta": false},
        {"texto": "...", "correta": false}
      ]
    }
  ]
}
\`\`\`

Depois rode:

    cd ${RAIZ} && php scripts/importar_questoes_oab.php --arquivo=${RAIZ}/storage/app/questoes-oab/${args.prefixo}-lote-${l.n}.json --dry-run

Corrija o que ele apontar e repita até passar. Então importe de verdade, sem o --dry-run. Concluído quando imprimir "OK ${args.bloco}".`
}

const [aulas, lotes] = await Promise.all([
  AULAS.length === 0 ? Promise.resolve([]) : pipeline(AULAS, (a, _o, i) => agent(promptAula(a, i), {
    label: `aula ${a.id}: ${a.t.slice(0, 40)}`, phase: 'Aulas', schema: R_AULA,
  })),
  pipeline(args.lotes, (l) => agent(promptLote(l), {
    label: `${args.prefixo} lote ${l.n}: ${l.tema.slice(0, 32)}`, phase: 'Questões', schema: R_LOTE,
  })),
])

const gravadas = aulas.filter((r) => r && r.gravada).length
const importadas = lotes.filter(Boolean).reduce((s, r) => s + (r.ok ? r.importadas : 0), 0)
log(`${args.modulo}: aulas ${gravadas}/${AULAS.length} · questões ${importadas}/${args.lotes.length * POR_LOTE.n}`)

return {
  modulo: args.modulo,
  aulasGravadas: gravadas,
  questoesImportadas: importadas,
  falhasAulas: aulas.map((r, i) => (r && r.gravada ? null : { item: AULAS[i].id, motivo: r ? r.observacao : 'agente não retornou' })).filter(Boolean),
  falhasLotes: lotes.map((r, i) => (r && r.ok ? null : { lote: args.lotes[i].n, motivo: r ? r.observacao : 'agente não retornou' })).filter(Boolean),
}

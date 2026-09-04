export const meta = {
  name: 'lote-disciplinas-oab',
  description: 'Produz aulas e questões de VÁRIAS disciplinas do curso OAB numa passagem só',
  phases: [
    { title: 'Aulas', detail: 'uma aula por agente, gravada com validação' },
    { title: 'Questões', detail: 'lotes de 5 questões, importados com validação' },
  ],
}

// args = { disciplinas: [ { modulo, peso, quiz, bloco, prefixo, leis, distratores,
//                           aulas: [{id, t}], lotes: [{n, tema}] } ] }
//
// Achata todas as disciplinas em duas filas — uma de aulas, outra de lotes de
// questoes — em vez de rodar uma disciplina por vez. Com apenas 2 agentes em
// paralelo nesta maquina, encadear disciplinas deixaria slot ocioso no fim de
// cada uma. Achatando, a fila so esvazia no fim de tudo.

const RAIZ = '/home/desbloqueiacursos/public_html'
const MODELO = RAIZ + '/resources/templates/oab/modelo-corpo-aula01.html'
const POR_LOTE_PADRAO = { n: 5, facil: 1, media: 3, dificil: 1 }
const porLote = (d) => d.porLote || POR_LOTE_PADRAO

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

function citacao(d) {
  return `**NUNCA invente número de artigo, de lei, de súmula ou de enunciado.** É a regra mais importante do curso. Se você não tem certeza absoluta do número, descreva o instituto sem numerar — "o Código estabelece que...", "a Constituição assegura...". Material sem número de artigo é perfeitamente aceitável; material com número errado ensina o candidato a errar na prova e destrói a credibilidade do curso. ${d.leis}`
}

function promptAula(d, a, i) {
  const lista = d.aulas.map((x, j) => `${j + 1}. ${x.t}`).join('\n')
  return `Você vai escrever uma aula completa do curso "OAB 1ª Fase Completo" (curso 125) do portal Desbloqueia Cursos, material de preparatório para a 1ª fase do Exame de Ordem, vendido a bacharéis em Direito.

- ID do item: ${a.id}
- Título (use EXATAMENTE este no <h1>): ${a.t}
- Módulo: ${d.modulo} — ${d.pesoTexto || `**${d.peso} das 80 questões da prova**`}
- Posição: aula ${i + 1} de ${d.aulas.length} do módulo

Aulas do módulo — não invada o território das outras:
${lista}
(a sua é a de número ${i + 1})

${d.escopo || `Esta disciplina vale ${d.peso} questões: priorize o que mais cai e não tente esgotar a matéria. Densidade alta, escopo enxuto — o aluno tem 20 disciplinas para cobrir.`}

## Antes de escrever

Leia ${MODELO} INTEIRO. É a aula 1 do curso, aprovada pelo cliente, e define o padrão: tom de professor experiente, abertura por uma cena concreta e reconhecível, densidade alta, zero tom motivacional. O CSS é colado automaticamente na gravação — você escreve só o corpo.

Componentes (copie a sintaxe do modelo): card-pratica, card-resumo, card-dica, alerta-educacional, grade / grade-item / grade-item.erro, fluxo, mapa-mental, linha-tempo, comparacao, checklist, quadro, lei (citação de dispositivo), numeros, barras, medidor, item-prova com ol.alternativas, selo. Todo h3 abre com ícone SVG inline simples (24x24, traço, currentColor).

Escolha os componentes pelo que o SEU conteúdo pede. Uma aula de "Mapa visual" é dominada por quadros e esquemas; uma "Prática guiada" é dominada por questões comentadas.

## Regras inegociáveis

1. ${citacao(d)}
2. **Questões: exatamente quatro alternativas (A, B, C e D)**, uma correta. **NÃO marque a alternativa correta no HTML** — nada de class="correta" no <li>, porque isso pinta a resposta de verde e entrega o gabarito antes de o aluno pensar. O gabarito é revelado no comentário logo abaixo, num <span class="selo verde">Gabarito: X</span>, exatamente como faz o modelo. Pelo menos duas questões comentadas, cada uma seguida de um h3 de comentário que analisa os três distratores e explica como cada um foi construído.
3. **O tamanho da alternativa não pode entregar o gabarito**, em nenhuma das duas direções.
4. Nada de recurso externo, iframe ou script src. Sem tag de estilo no corpo.
5. Português brasileiro correto, com acentuação.

## Entrega

Escreva o corpo em ${RAIZ}/storage/app/aulas-oab/corpo-${a.id}.html. Deve começar exatamente com \`<div class="licao">\`, trazer o cabeçalho com ícone e \`<h1 class="licao-titulo">\`, um h3 "O que você precisa dominar nesta aula" com \`<ul class="checklist">\`, pelo menos duas questões comentadas, terminar com \`<div class="card-resumo">\` e, ao final, o mesmo bloco \`<script>\` de animação do modelo, copiado literalmente. Entre 45.000 e 70.000 bytes.

Depois rode:

    cd ${RAIZ} && php scripts/gravar_aula_oab.php --item=${a.id} --corpo=${RAIZ}/storage/app/aulas-oab/corpo-${a.id}.html --dry-run

Corrija o que reprovar e repita. Quando passar, grave de verdade tirando o --dry-run. Concluído quando imprimir "OK item ${a.id}".`
}

function promptLote(d, l) {
  const P = porLote(d)
  const total = d.lotes.length
  const destino = d.simulado
    ? `o **SIMULADO oficial** de **${d.modulo.replace(/^\d+\.\s*/, '')}**`
    : `o quiz de treino de **${d.modulo.replace(/^\d+\.\s*/, '')}**`
  const inedito = `

## Estas questões precisam ser INÉDITAS em relação a tudo o que o curso já pergunta

O curso já tem banco de treino e banco de simulado nesta mesma disciplina, e os três conjuntos precisam ser estanques entre si. **Antes de escrever qualquer coisa, rode:**

    cd ${RAIZ} && php scripts/listar_banco_oab.php --bloco=${d.bloco}

Leia a lista inteira, as duas seções. Nenhuma das suas questões pode repetir uma daquelas proposições. **Trocar o nome do personagem e o cenário NÃO resolve**: se a tese jurídica cobrada é a mesma, é repetição, e o aluno vai reconhecer o item. Quando um assunto já estiver coberto, ataque outro recorte dele — outro requisito, outra exceção, outro efeito, outro sujeito, outra fase.

Esse é o trabalho principal deste lote, e não um detalhe: a lista já é longa, então espere gastar tempo procurando terreno livre. Se um recorte que você imaginou já estiver lá, descarte e procure outro.

Vale o mesmo dentro do seu próprio lote: as suas ${P.n} questões precisam cobrar ${P.n} pontos distintos.`
  return `Você vai escrever ${P.n} questões objetivas para ${destino} do curso "OAB 1ª Fase Completo" (curso 125), no padrão da FGV.${inedito}

## Seu recorte

Lote ${l.n}: **${l.tema}**

Outros agentes escrevem os demais recortes desta disciplina ao mesmo tempo. Fique no seu.

## Cota de dificuldade (obrigatória)

Exatamente **${P.facil} fácil(eis), ${P.media} médias e ${P.dificil} difícil(eis)**. Os ${total} lotes juntos precisam fechar ${total * P.facil} fáceis, ${total * P.media} médias e ${total * P.dificil} difíceis — que é o que o sorteio exige para as três tentativas nunca repetirem questão nem completarem de outra faixa.

## Regras inegociáveis

1. **Exatamente quatro alternativas (A, B, C e D), uma única correta.** A OAB não tem cinco.
2. ${citacao(d)}
3. **Viés de comprimento, nas duas direções.** O importador reprova o lote se em mais de 25% das questões a correta for perceptivelmente mais longa que todos os distratores, OU perceptivelmente mais curta que todos (perceptível = mais de 15 caracteres). Ele também reprova se a correta for a mais curta das quatro em mais de 60% do lote.

   Traduzindo para o que você deve fazer: **em pelo menos duas das ${P.n} questões, escreva um distrator MAIS CURTO que a correta.** O erro clássico é escrever a correta enxuta e três distratores longos e explicativos — o resultado é um banco em que marcar a mais curta acerta quase tudo. Corrija encurtando distratores, nunca mexendo na correta.
4. **Distrator bom é o que um candidato despreparado escolheria com convicção.** ${d.distratores}
5. **Enunciado com situação concreta**, com nome de pessoa e cenário, como faz a FGV. Mínimo de 80 caracteres, sem HTML.
6. **Explicação obrigatória e didática**, exibida ao aluno depois do envio: por que a correta está certa e por que o distrator mais tentador está errado. Mínimo de 80 caracteres, sem HTML.
7. **Distribua a posição do gabarito por igual.** Some ao final: das suas ${P.n} questões, aproximadamente um quarto deve ter a correta em A, um quarto em B, um quarto em C e um quarto em D. Conte antes de entregar e corrija se alguma letra ficar com menos da metade do esperado — um banco em que a resposta quase nunca é D ensina o aluno a descartar D, e isso é pior do que uma questão fraca.
8. Português brasileiro correto, com acentuação.

## Entrega

Escreva ${RAIZ}/storage/app/questoes-oab/${d.prefixo}-lote-${l.n}.json:

\`\`\`json
{
  "quiz_id": ${d.quiz},
  "bloco": "${d.bloco}",
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

    cd ${RAIZ} && php scripts/importar_questoes_oab.php --arquivo=${RAIZ}/storage/app/questoes-oab/${d.prefixo}-lote-${l.n}.json --dry-run

Corrija o que ele apontar e repita até passar. Então importe de verdade, sem o --dry-run. Concluído quando imprimir "OK ${d.bloco}".`
}

// --- achatamento -----------------------------------------------------------
const filaAulas = []
const filaLotes = []
for (const d of args.disciplinas) {
  d.aulas.forEach((a, i) => filaAulas.push({ d, a, i }))
  d.lotes.forEach((l) => filaLotes.push({ d, l }))
}

log(`${args.disciplinas.length} disciplinas · ${filaAulas.length} aulas · ${filaLotes.length} lotes de questões`)

const [aulas, lotes] = await Promise.all([
  pipeline(filaAulas, (x) => agent(promptAula(x.d, x.a, x.i), {
    label: `${x.d.prefixo} · aula ${x.a.id}`, phase: 'Aulas', schema: R_AULA,
  })),
  pipeline(filaLotes, (x) => agent(promptLote(x.d, x.l), {
    label: `${x.d.prefixo} · lote ${x.l.n}`, phase: 'Questões', schema: R_LOTE,
  })),
])

const gravadas = aulas.filter((r) => r && r.gravada).length
const importadas = lotes.filter(Boolean).reduce((s, r) => s + (r.ok ? r.importadas : 0), 0)
log(`aulas ${gravadas}/${filaAulas.length} · questões ${importadas}/${filaLotes.reduce((soma, x) => soma + porLote(x.d).n, 0)}`)

return {
  aulasGravadas: gravadas,
  aulasTotal: filaAulas.length,
  questoesImportadas: importadas,
  questoesTotal: filaLotes.reduce((soma, x) => soma + porLote(x.d).n, 0),
  falhasAulas: aulas.map((r, i) => (r && r.gravada ? null : { item: filaAulas[i].a.id, disciplina: filaAulas[i].d.prefixo, motivo: r ? r.observacao : 'agente não retornou' })).filter(Boolean),
  falhasLotes: lotes.map((r, i) => (r && r.ok ? null : { lote: filaLotes[i].l.n, disciplina: filaLotes[i].d.prefixo, motivo: r ? r.observacao : 'agente não retornou' })).filter(Boolean),
}

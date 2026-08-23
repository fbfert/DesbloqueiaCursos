# Telemetria da Norminha — como ler, e o que decidir

`/admin/tutor-norminha/telemetria` · permissão `conteudo.ver` · somente leitura

Este painel existe para responder **uma** pergunta: *a camada de IA se paga?*
Sem ele, o Checkpoint 0 do plano mestre vira opinião — alguém acha que está indo bem, alguém acha
que não, e a decisão de gastar token por mensagem é tomada no escuro.

## A resposta não está nos números

Está na **lista de perguntas não resolvidas**. Os contadores dizem o tamanho do problema; só a
lista diz a natureza dele — e é a natureza que decide.

Leia cinquenta perguntas e classifique cada uma:

| Tipo | Exemplo | O que fazer |
|---|---|---|
| **Conteúdo** | "não entendi o método comparativo direto" | Isto a IA resolve. Conta a favor da Onda 1 |
| **Navegação / suporte** | "onde emito a segunda via do boleto?" | Isto um **fast-path** resolve — mais barato, mais rápido, sem alucinação |

Se a maioria for navegação, a decisão correta é **voltar à Etapa 4 e escrever mais atalhos**, não
ligar o modelo. Um fast-path custa uma tarde e responde sempre igual; a IA custa por mensagem, para
sempre, e às vezes inventa.

Foi para tornar essa leitura possível que o `resolved_by = 'unresolved'` existe desde a Etapa 4.

## As métricas, uma a uma

### Alunos que conversaram

Quantas pessoas distintas enviaram ao menos uma pergunta no período.

**Como ler:** compare com o total de alunos ativos no portal. Se 4 de 300 abriram o chat, o problema
não é a qualidade da resposta — é que ninguém encontra ou quer a Norminha. Nesse caso nem a Onda 1
nem mais fast-paths mudam nada, e o trabalho é de descoberta: posição, rótulo, momento.

### Perguntas enviadas e perguntas por aluno ativo

Total e média. **A média importa mais que o total.** Dez alunos com uma pergunta cada é adoção; um
aluno com dez é curiosidade de uma pessoa só. Um total alto com média alta e poucos alunos é
sinal de que alguém está testando, não usando.

### Conversas abertas

Cada abertura de chat sem `conversation_id` cria uma. Muitas conversas para poucas mensagens
significa gente que abre, olha e fecha.

### Não resolvidas pelo sistema (%)

A fatia que o PHP não deu conta. **Este número sozinho não decide nada** — 60% de perguntas de
navegação pede fast-path, não IA.

O cartão fica em alerta acima de 40%. É um limiar para chamar atenção, não um gatilho automático.

### Como as respostas foram resolvidas

Na Onda 0 só existem `php` e `unresolved`, porque a IA está desligada. Depois da Onda 1 aparecem
`hybrid` (modelo com evidência ou ferramenta) e `ai` (resposta puramente linguística).

**Sinal de alerta pós-Onda 1:** `ai` alto significa que o modelo está respondendo sem evidência do
conteúdo oficial — ou seja, chutando com boa gramática. O esperado é `hybrid` dominar.

### O que os atalhos resolveram

Ranking dos fast-paths. Diz o que já é resolvido de graça e, por ausência, o que talvez devesse ser:
se muita gente pergunta em texto livre algo que um atalho cobriria, ou o atalho não está visível ou
a classificação de intenção está conservadora demais (e ela é conservadora **de propósito** — ver
Etapa 4).

### Feedback dos alunos

Útil × não útil, mais a **cobertura** — que porcentagem das respostas recebeu voto.

**Cobertura abaixo de 10% torna o percentual ruído.** O painel avisa quando isso acontece. Três
votos de cinquenta respostas não medem satisfação; medem que três pessoas clicaram.

### Bloqueios por limite

Quantos 429 o rate limit devolveu, e para quantos alunos.

**Como ler:** um número consistentemente acima de zero, espalhado por vários alunos, significa
limite apertado demais para uso normal — 20 mensagens em 5 minutos deveria incomodar só quem está
abusando. Concentrado em um aluno só, é o contrário: está funcionando.

### Cursos com mais dúvidas

Onde a dor se concentra. Um curso destacado costuma indicar material confuso num ponto específico —
e às vezes a correção certa é reescrever a aula, não ensinar a IA a explicá-la.

## Critérios sugeridos para o Checkpoint 0

Sugestões, deliberadamente **não** codificadas: números fixos em código envelhecem e ninguém os
revisa.

1. **Volume mínimo.** Pelo menos 10 a 14 dias de chat liberado para todos os alunos autenticados, e
   uso por gente suficiente para a amostra significar algo. Sem volume, tudo abaixo é ruído.
2. **Proporção de não resolvidas** alta o bastante para justificar o custo. Abaixo de ~20%, o PHP já
   está dando conta.
3. **Natureza das perguntas** — o critério que manda. Maioria de conteúdo → Onda 1. Maioria de
   navegação → mais fast-paths, e reavalie depois.
4. **Custo projetado.** Extrapole as mensagens que iriam para IA por mês e multiplique pelo preço
   vigente do modelo. Esse valor cabe no custo por aluno?

Se 1 falhar, os outros não têm sentido. Se 3 apontar navegação, 2 e 4 não importam.

## Notas técnicas

**Fuso.** Toda janela é calculada em SQL (`DATE_SUB(CURDATE(), INTERVAL n DAY)`), no mesmo relógio
que gravou os dados. O PHP desta aplicação roda em UTC e o MySQL em UTC−3: uma data vinda de
`date()` erraria o dia inteiro toda noite, e o painel mostraria zero sem avisar. Ver
`docs/2026-08-15-fuso-horario-php-mysql.md`.

**Índices.** Toda consulta filtra por período e usa os índices da migration `074`. Verificado por
`EXPLAIN` com 10 mil linhas: `type: range`, 375 linhas examinadas em vez de 10.000. Em tabela
pequena o otimizador prefere varredura, o que é escolha correta dele, não índice faltando.

**Bloqueios são contados, não estimados.** A coluna `norminha_uso.bloqueios_dia` (migration 074)
existe porque o log de arquivo não se consulta por período, e `mensagens_dia` não enxerga bloqueio
de janela.

**O texto do aluno é conteúdo não confiável.** A view escapa tudo com `Helpers::e()`; nada é
renderizado como HTML. O painel não mostra nome, e-mail nem CPF — só `usuario_id`, para suporte
pontual. Há teste que falha se algum desses termos aparecer na saída do service.

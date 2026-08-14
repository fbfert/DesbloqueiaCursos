# Curso 117 (IA nos estudos): conteúdos expandidos e novos itens

Data: 2026-08-13
Curso: 117 — "IA na prática, como usar nos estudos!" (8h)
Skill aplicada: `.claude/skills/conteudo-html-expandido`

Mesmo tratamento dado ao curso 118, com duas diferenças: além de reescrever as
aulas existentes, o curso ganhou **7 itens novos**, porque cada módulo tinha
apenas um conteúdo.

## O que mudou

| Métrica | Antes | Depois |
|---|---|---|
| Aulas (`tipo=html`) | 5 | **12** |
| Itens por módulo | 1,0 | **2,4** |
| Tamanho médio | 1.729 caracteres | **24.809** |
| Com design system | 0 | 12 |

O curso 118, como referência, tem 3,8 itens por módulo.

## Itens novos

| Módulo | Item |
|---|---|
| 1. Uso consciente | Mapa visual: onde a IA ajuda e onde ela falha |
| 2. Prompts | **Biblioteca de prompts: 20 modelos prontos** (entregou 29) |
| 2. Prompts | Prática guiada: monte e refine o seu prompt |
| 3. Organização | Modelos de cronograma e revisão espaçada |
| 3. Organização | Prática guiada: monte seu plano da semana |
| 4. Ética | Casos comentados: dilemas de autoria e privacidade |
| 4. Ética | Checklist de verificação: como conferir o que a IA respondeu |

Publicados com `obrigatorio = 0`: são complementares e **não travam o
certificado**. Marcá-los como obrigatórios reabriria a conclusão de quem já
tivesse terminado o curso — no momento da mudança havia 5 inscrições, nenhuma
concluída, mas a regra vale para o futuro.

Foram criados primeiro em `rascunho`, com HTML de placeholder, e só publicados
depois do conteúdo gravado e conferido. Isso evita que qualquer aluno veja
página vazia durante a geração.

## Adaptações em relação ao curso 118

O 118 é preparatório para prova; o 117 é para estudantes aprenderem a usar IA.
Copiar as instruções ao pé da letra teria produzido material errado:

| Aspecto | Curso 118 | Curso 117 |
|---|---|---|
| Público | professores em preparação | estudantes (médio, graduação, concursos) |
| Exercício | questão no estilo PND | fixação preparando para o quiz final obrigatório |
| Componente-chave | mapa mental | comparação prompt fraco × prompt bem feito |
| Aula de encontro ao vivo | — | preparação antes/durante/depois, sem teoria |

Regras editoriais específicas deste tema, dadas a todos os agentes:

- **Sem nome comercial de ferramenta de IA** — o curso não endossa fornecedor;
  o texto fala em "a ferramenta de IA que você usa";
- **Privacidade** — não inserir dados pessoais, de terceiros ou material
  sigiloso de estágio/trabalho;
- **Alucinação** — a IA prevê texto plausível, não consulta base de verdade;
  por isso inventa citações, datas e páginas com confiança. Conferir sempre em
  fonte confiável;
- **Autoria** — entregar texto de IA como próprio é desonestidade acadêmica.

## Execução

Dois workflows, ambos sem erros:

1. 5 agentes para reescrever as aulas existentes (5/5, ~10 min)
2. 7 agentes para os itens novos (7/7, ~10 min)

Os agentes do segundo workflow receberam a lista das 4 aulas já existentes com
instrução de **complementar, não repetir**.

Para gravar apenas os 7 itens novos foi preciso um modo "subconjunto" do script
de aplicação: o original exige que o JSON cubra **todos** os itens do curso e
abortaria ao ver os 5 já gravados fora da lista.

## Validação

Mesmas travas do curso 118 — `card-resumo` obrigatório, mínimo de bytes por
item (3.000 nos novos, para acomodar a aula de palestra, que é menor por
desenho), ícone válido, HTML bem formado, sem `<script>`/`<iframe>`/`on*`, e
gravação em transação única.

Conferência final no banco: zero mojibake, zero placeholder `{{ICON:}}` órfão,
zero script/iframe, 12/12 com `card-resumo`.

Backup anterior à gravação:
`backups/curso117-html-pre-melhoria-20260813-212216.sql`.

## Quiz final: banco triplicado e sorteio ativado

Quiz `id=23`, item 1473 ("Quiz final: validação do curso").

| | Antes | Depois |
|---|---|---|
| Questões no banco | 5 | **15** |
| Aplicadas por tentativa | as 5, sempre as mesmas | **5 sorteadas** |
| `modo_selecao` | `todas` | `blocos` |
| Embaralhamento | desligado | perguntas **e** alternativas |
| Repetição entre tentativas | sempre repetia | evita enquanto houver inéditas |

**Triplicar o banco sozinho não resolveria.** Com `modo_selecao = 'todas'` o
quiz aplica todas as questões cadastradas em toda tentativa — passar de 5 para
15 apenas deixaria a prova três vezes maior, sempre igual. Para "sortear
perguntas diferentes a cada tentativa" foi preciso, além de ampliar o banco:

1. criar o bloco `GERAL` com `quantidade_sortear = 5`;
2. vincular a ele as 5 questões que já existiam;
3. mudar o quiz para `modo_selecao = 'blocos'`;
4. ligar embaralhamento e `evitar_repeticao_tentativas`.

Simulação de 3 tentativas seguidas do mesmo aluno: 15 questões distintas, zero
repetição. Da 4ª tentativa em diante o banco se esgota, o sistema reaproveita
questões já vistas e registra a ocorrência em `sorteio_auditoria_json`.

As 10 questões novas cobrem os temas das 12 aulas (uso consciente, construção
de prompts, verificação e alucinação, privacidade, autoria, revisão espaçada e
organização) e foram escritas com **4 alternativas**, contra 3 das originais:
com 3 opções o chute acerta 33% das vezes, com 4 cai para 25%. As 5 antigas
foram preservadas como estavam.

A tentativa já existente (1 aluno, aprovado com 100%) continua íntegra — o
snapshot preserva a prova exatamente como foi aplicada.

Backup: `backups/quiz23-pre-expansao-20260813-222924.sql`.

### Ponto em aberto

O quiz tem `tentativas_maximas = NULL` (ilimitadas). Com 15 questões e 5 por
tentativa, o banco cobre 3 tentativas inéditas; a partir da 4ª haverá
reaproveitamento. Se a intenção for que ninguém veja questão repetida, basta
limitar a 3 tentativas — ou ampliar o banco.

## Carga horária

Mantida em **8h** por decisão do usuário. Registro a ressalva: o curso passou de
5 para 12 aulas e de ~8,6 mil para ~298 mil caracteres de conteúdo, então 8h é
hoje uma referência conservadora — o material novo funciona como aprofundamento
opcional.

## Pendência

Conteúdo gerado por IA. Recomenda-se revisão por amostragem antes de divulgar,
com atenção especial à **biblioteca de prompts** (item 1539), que é o material
de consulta mais usado, e aos **casos comentados de ética** (item 1543), onde a
recomendação precisa estar alinhada às normas da instituição.

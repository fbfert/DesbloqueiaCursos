# Curso 116 (neurociência infantil): conteúdos expandidos, novos itens e quiz

Data: 2026-08-13
Curso: 116 — "O que prejudica o cérebro infantil em formação? A neurociência explica!" (8h)
Skill aplicada: `.claude/skills/conteudo-html-expandido`

Mesmo tratamento dos cursos 118 e 117: aulas reescritas no design system, itens
novos para equilibrar os módulos e quiz final com banco ampliado e sorteio.

## O que mudou

| Métrica | Antes | Depois |
|---|---|---|
| Aulas (`tipo=html`) | 7 | **13** |
| Tamanho médio | 2.020 caracteres | **22.515** |
| Com design system | 0 | 13 |
| Quiz: banco | 5 questões | **15** |
| Quiz: por tentativa | as 5, sempre iguais | **5 sorteadas** |

## Itens novos

| Módulo | Item |
|---|---|
| 2. Fatores que prejudicam | Mapa visual: como o estresse tóxico afeta o cérebro |
| 2. Fatores que prejudicam | Casos comentados: reconhecer sinais no dia a dia |
| 3. Ambientes e vínculos | Prática guiada: construir vínculo responsivo |
| 4. O que protege | Rotina protetora na prática: sono, brincar e movimento |
| 5. Plano prático | Modelos prontos: plano de ação para casa e escola |
| 5. Plano prático | Checklist do ambiente protetor |

Publicados com `obrigatorio = 0` (complementares, não travam o certificado) e
criados antes em `rascunho`, para nenhum aluno ver página vazia durante a
geração.

## Cuidados específicos do tema

Este é um curso sobre **desenvolvimento infantil** para pais, cuidadores e
educadores — público não técnico. Um material assim erra de dois jeitos: pelo
alarmismo determinista e pela culpabilização de quem cuida. As instruções aos
agentes trataram os dois:

- **Linguagem de possibilidade**, nunca de determinismo: "pode afetar", "tende
  a", "está associado a". Proibido afirmar que uma experiência causa dano
  cerebral específico e irreversível;
- **Proibido inventar** estatísticas, percentuais, idades exatas de janelas,
  pesquisadores, universidades ou estudos;
- **Não ensinar a diagnosticar**: sinais servem para observar e encaminhar,
  jamais para rotular criança. Onde há sinal, há orientação de procurar
  pediatra, psicólogo, fonoaudiólogo ou serviço de saúde;
- **Não culpabilizar famílias**, sobretudo em vulnerabilidade, e reforçar
  plasticidade: vínculo, rotina e ambiente protetor melhoram trajetórias;
- **`alerta-educacional` obrigatório** em cada aula, dizendo que o conteúdo é
  educativo e não substitui profissional de saúde.

O validador ganhou uma checagem extra que avisa se alguma aula vier sem o
`alerta-educacional`. Resultado: **13/13 com o aviso**.

Uma varredura por linguagem determinista encontrou dois usos de "dano
permanente" — ambos **legítimos, negando o determinismo**:

> "Repare na linguagem: pode afetar, e não 'causa dano permanente'."

> "Isso não significa que uma interação ruim cause dano permanente, nem que um
> adulto precise acertar sempre."

## Quiz final (id 22)

Mesma correção aplicada ao quiz do curso 117: o quiz estava em
`modo_selecao = 'todas'`, aplicando sempre as mesmas 5 questões. Ampliar o banco
sem ligar o sorteio apenas deixaria a prova maior e ainda idêntica.

Foi criado o bloco `GERAL` (sorteia 5), as 5 questões existentes foram
vinculadas a ele, o quiz passou a `modo_selecao = 'blocos'` e o embaralhamento
de perguntas e alternativas foi ligado.

As 10 questões novas seguem os mesmos cuidados do tema: nenhuma alternativa
correta afirma determinismo, sugere que o adulto leigo diagnostique ou
culpabiliza a família. A conduta correta é sempre observar, acolher e
encaminhar quando for o caso — inclusive na questão sobre família em
vulnerabilidade.

## Validação

13/13 páginas aprovadas, zero erros e zero avisos. Conferência no banco: zero
mojibake, zero placeholder de ícone órfão, zero `<script>`/`<iframe>`, 13/13
com `card-resumo`, ícones SVG e `alerta-educacional`.

Backup: `backups/curso116-pre-melhoria-20260813-224553.sql` (inclui aulas,
quiz, perguntas e alternativas).

## Pendências

**Revisão profissional recomendada.** O conteúdo foi gerado por IA com as
travas descritas acima, mas trata de desenvolvimento infantil. Antes de
divulgar, vale revisão de alguém da área — com prioridade para os itens 1545
(mapa do estresse tóxico), 1546 (casos comentados) e 1550 (checklist com
encaminhamentos por profissional).

**Quiz com tentativas ilimitadas** (`tentativas_maximas = NULL`): o banco de 15
cobre 3 tentativas inéditas; a partir da 4ª haverá reaproveitamento, registrado
na auditoria do sorteio.

**Carga horária mantida em 8h.** O curso passou de 7 para 13 aulas e de ~14 mil
para ~293 mil caracteres; 8h é hoje uma referência conservadora.

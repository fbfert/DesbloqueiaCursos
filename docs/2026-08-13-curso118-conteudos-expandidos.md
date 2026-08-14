# Curso 118 (PND Pedagogia): conteúdos HTML expandidos

Data: 2026-08-13
Curso: 118 — "PND na prática - PEDAGOGIA: preparação para a Prova Nacional Docente"
Skill aplicada: `.claude/skills/conteudo-html-expandido`

As 61 aulas `tipo=html` do curso foram reescritas: conteúdo expandido, migração
para o design system compartilhado da skill e inclusão de questões comentadas
no formato da prova.

## O que mudou

| Métrica | Antes | Depois |
|---|---|---|
| Tamanho médio por aula | 4.163 caracteres | **24.830** |
| Aulas com `card-resumo` | 0 | 61 |
| Aulas com ícones SVG | 0 | 61 |
| Aulas com mapa mental | 0 | 61 |
| Aulas com questão comentada | 0 | 61 |

Antes, cada página trazia um CSS próprio (paleta carvão/magenta/coral) e nenhum
componente do design system. Agora todas usam o CSS compartilhado de
`design-system.css` e os ícones de `icons.php`.

## Caso fora do padrão da skill

A skill foi escrita para converter `tipo=texto` → `tipo=html`. Aqui os itens
**já eram** `tipo=html`, então o caminho foi `html` → `html`:

- a seleção passou a buscar `tipo='html'`;
- a gravação **não** altera `conteudo_itens.tipo` nem apaga `conteudo_textos`
  (o script original da skill faria as duas coisas, o que aqui seria destrutivo);
- cada agente leu o HTML publicado do próprio item antes de reescrever, para não
  perder informação já revisada.

Se for repetir em outro curso já convertido, parta desse ajuste — não rode o
`apply-conversao.php` original sem adaptar.

## Perfis por formato de aula

O curso tem quatro formatos por módulo, e cada um recebeu instrução própria:

| Formato | Componentes priorizados |
|---|---|
| Aula principal | mapa mental ou fluxo + card prática + quadro/comparação |
| Caderno de aprofundamento | quadro, comparação e grade (texto teórico denso) |
| Mapa visual | mapa mental como protagonista, no início, com 4–5 ramos |
| Prática guiada | fluxo passo a passo, caso real de escola e checklist |

Todas terminam com `card-resumo` e trazem 1–2 questões comentadas com
alternativas em `<ol type="A">` e explicação de por que cada distrator está
errado.

## Execução

Workflow com 61 agentes em paralelo (um por aula), autorizado explicitamente
pelo usuário. Resultado: 61/61 concluídos, 0 erros, 0 retornos vazios,
~2h24 de execução e ~2,4M tokens de subagentes.

O workflow foi interrompido no meio (a sessão encerrou) e retomado com
`resumeFromRunId` — as 11 aulas já prontas voltaram do cache, sem refazer
trabalho. Vale lembrar disso em execuções longas: o journal preserva o
progresso.

## Validação antes de gravar

Gravação em transação única, tudo-ou-nada, só depois de 61/61 passarem:

- `card-resumo` presente (erro se faltar);
- corpo com no mínimo 3.500 bytes (proteção contra resposta truncada);
- ícone dentro da lista permitida;
- HTML bem formado (`DOMDocument`);
- ausência de `<script>`, `<iframe>` e atributos `on*`.

Conferência posterior no banco: zero mojibake, zero placeholder `{{ICON:}}`
órfão, zero `<script>`/`<iframe>`, tipos de item preservados (61 html + 1 quiz).

Backup anterior à gravação:
`backups/curso118-html-pre-melhoria-20260813-163951.sql` (260K).

## Bug corrigido na skill

A validação anti-XSS usava `on[a-z]+\s*=`, que casa com **texto legítimo em
português**: a frase "Atividade c*oncreta =* aprendizagem" era barrada como
atributo de evento. Qualquer palavra com "on" antes de um `=` dispararia o
falso positivo.

Corrigido para `<[^>]*\son[a-z]+\s*=`, exigindo que o `on*=` esteja dentro de
uma tag. Validado contra 9 casos: `onclick`, `onerror`, `onload`,
`onmouseover`, `<script>` e `<iframe>` seguem bloqueados; texto comum passa.

## Conferência visual

Renderizada via Chromium headless em desktop e em iframe de 390px (que é como o
LMS exibe o conteúdo `tipo=html`). Layout se reorganiza corretamente no mobile.

**Atenção ao medir responsividade neste ambiente:** o Chromium headless força
viewport mínimo de **500px** e ignora `--window-size` menor — a captura sai
recortada e parece corte de layout. Para testar mobile de verdade, embuta a
página num `<iframe width="390">`, como o próprio LMS faz.

## Pendência

O conteúdo foi gerado por IA a partir do material existente, com instrução de
não inventar estatísticas, citações legais literais ou nomes de escolas reais
(referências a LDB, ECA, BNCC e Constituição ficaram em termos gerais).

Como é material preparatório para prova oficial, **recomenda-se revisão
pedagógica por amostragem antes de divulgar**, com atenção especial ao gabarito
e à justificativa dos distratores das questões comentadas.

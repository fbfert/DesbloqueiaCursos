# Pendência: formatação rica no texto das questões de quiz

Data de registro: 2026-08-15
Status: **não iniciada** — decisão tomada de fazer como tarefa própria, não junto
da geração de curso.

## Situação atual

O texto das questões (enunciado, alternativa, explicação e rubrica) é **texto
puro**, por decisão de segurança. As views imprimem tudo com `Helpers::e()`, que
escapa; o que dá formatação hoje é apenas o `nl2br`, que transforma `\n` em
`<br>`.

Isso é diferente das aulas, que são HTML completo e rodam dentro de
`<iframe sandbox>`, isoladas da página.

Arquivos que imprimem texto de questão:

- `resources/views/v2/pages/quiz.php` (superfície viva)
- `resources/views/aluno/curso/_quiz.php`
- `resources/views/v4-claude/aluno/curso/_quiz.php`

## O que se ganharia

Praticamente uma coisa só: **tabela renderizada como tabela**. Hoje os dados
tabulares aparecem como linhas com ` | ` separando as células — legível, mas sem
alinhamento de colunas. Afeta cerca de 30 questões no simulado de Geografia
(quiz 27) e algumas no de Matemática (quiz 26).

Negrito e itálico seriam ganho menor: o texto de prova quase não usa.

## Como fazer

Trocar `Helpers::e()` por `App\Support\HtmlSanitizer::clean()` nos pontos que
imprimem texto de questão, reaproveitando o mesmo caminho que já protege o
conteúdo vindo do editor CKEditor:

`Helpers::decodeEditorHtml()` → `HtmlSanitizer::clean()` → `Helpers::renderSafeHtml()`

O sanitizer já remove `script`, `iframe`, `object`, `embed`, `form`, `input`,
atributos `on*` e `javascript:`/`data:` em `href`/`src`.

## Por que NÃO foi feito junto com a geração dos cursos

1. **Toca em todo texto de quiz exibido ao aluno**, nas três superfícies, e não
   só nas questões novas;
2. **É adjacente a XSS.** O campo é editável pelo admin e vai direto do banco
   para a página. Trocar escape por sanitização precisa de teste explícito com
   payload malicioso, não só de conferência visual;
3. **Não há staging.** O diretório de trabalho é o próprio docroot de produção;
4. O banco atual está inteiramente em texto puro, então **nada quebra enquanto
   isso não for feito** — é melhoria, não correção.

## Checklist para quando for executada

- [ ] escrever teste com payload (`<script>`, `onerror=`, `javascript:`) e
      confirmar que o sanitizer barra nas três views;
- [ ] confirmar que texto puro já existente continua renderizando igual —
      especialmente as tabelas em ` | `, que não podem regredir;
- [ ] decidir se as tabelas existentes serão convertidas para `<table>` ou se a
      mudança vale apenas para conteúdo novo;
- [ ] atualizar as instruções de geração de questões, que hoje **proíbem** HTML
      no enunciado (ver `docs/2026-08-15-curso121-pnd-geografia.md` quando
      existir, e o workflow de questões);
- [ ] atualizar a checagem `nenhum texto de questao contem HTML` em
      `scripts/verificar_banco_questoes.php`, que hoje trata HTML como erro.

O último item é importante: hoje existe uma trava que **rejeita** HTML no texto
do quiz, criada porque o curso 120 nasceu com 156 enunciados em HTML que
apareceriam literais na tela. Se a sanitização passar a valer, essa trava muda
de sentido e precisa ser revista junto, senão passa a acusar erro no que virou
comportamento correto.

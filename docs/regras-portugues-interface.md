# Regras de Português na Interface

## Regra permanente
O portal deve usar português brasileiro com acentuação correta em todos os textos visíveis ao usuário.
Todos os arquivos de interface devem ser salvos em UTF-8, e o admin precisa renderizar com charset UTF-8 do início ao fim.
É proibido publicar ou manter texto final com encoding quebrado, mesmo quando a string parecer acentuada no editor.

## Revisão obrigatória
- Mensagens, botões, menus, labels, placeholders, alertas, cards e páginas devem ser revisados antes do commit.
- É proibido inserir textos finais sem acentuação como `nao`, `acao`, `pagina`, `modulo`, `conteudo`, `area`, `usuario`, `inscricao` e `avaliacao` quando forem textos humanos.
- A regra não se aplica a nomes técnicos de variáveis, métodos, rotas, slugs, arquivos, tabelas, colunas, enums ou chaves internas.
- Antes de finalizar uma tarefa, o agente deve procurar ocorrências comuns de palavras sem acento em views, controllers e serviços relevantes.
- Quando houver dúvida, priorizar a correção em texto exibido ao usuário e preservar identificadores técnicos.

## Exemplos de revisão
- `Página` em vez de `Pagina`
- `Não` em vez de `Nao`
- `Ação` em vez de `Acao`
- `Ações` em vez de `Acoes`
- `Módulo` em vez de `Modulo`
- `Conteúdo` em vez de `Conteudo`
- `Área` em vez de `Area`

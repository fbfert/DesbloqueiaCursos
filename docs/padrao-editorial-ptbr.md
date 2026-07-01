# Padrão Editorial PT-BR

## Objetivo
Garantir consistência de linguagem em toda a interface do Portal de Cursos Polo Rainbow.

## Regra obrigatória
Todos os textos exibidos ao usuário devem estar em português brasileiro com acentuação correta.
Todos os arquivos de interface devem ser salvos em UTF-8 e renderizados com charset UTF-8 no admin e nas demais áreas.
Texto final com encoding quebrado é considerado erro de interface, mesmo quando a palavra estiver corretamente escrita na origem.

## Escopo
- Frontend público
- Área do aluno
- Área do professor
- Backoffice/admin
- Mensagens de feedback (erro, sucesso, aviso, status)

## Exemplos
- `Não` (em vez de `Nao`)
- `Você` (em vez de `Voce`)
- `Configurações` (em vez de `Configuracoes`)
- `Inscrições` (em vez de `Inscricoes`)
- `Período` (em vez de `Periodo`)
- `Ações` (em vez de `Acoes`)

## Aplicação prática
- Revisar textos de views antes de concluir alterações.
- Revisar mensagens em controllers/services que são exibidas na interface.
- Em revisão de código, reprovar textos sem acentuação.
- Ver também `docs/regras-portugues-interface.md` como regra operacional permanente.

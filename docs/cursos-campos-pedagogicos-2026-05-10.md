# Cursos/Eventos — Campos pedagógicos e valor promocional (2026-05-10)

## Objetivo
Ampliar o cadastro administrativo de cursos/eventos com campos pedagógicos/comerciais e suportar **valor promocional** (sem quebrar pedidos existentes), refletindo essas informações nas páginas públicas e administrativas.

## Migração
- Arquivo: `sql/026_cursos_campos_pedagogicos.sql`
- Tabela: `cursos_eventos`
- Estratégia: MySQL 5.7 (sem `ADD COLUMN IF NOT EXISTS`) usando `INFORMATION_SCHEMA.COLUMNS` + SQL dinâmico.

## Novos campos (tabela `cursos_eventos`)
- `valor_promocional` (DECIMAL(10,2) NULL)
- `objetivo_geral` (LONGTEXT NULL)
- `objetivos_especificos` (LONGTEXT NULL) — um por linha
- `publico_alvo` (LONGTEXT NULL)
- `pre_requisitos_texto` (LONGTEXT NULL)
- `pre_requisitos_itens` (LONGTEXT NULL) — um por linha
- `ementa` (LONGTEXT NULL)
- `conteudo_programatico_tipo` (VARCHAR(20) NOT NULL DEFAULT 'texto') — `texto|html|modulos`
- `conteudo_programatico_texto` (LONGTEXT NULL)
- `conteudo_programatico_modulos` (LONGTEXT NULL) — JSON
- `metodologia` (LONGTEXT NULL)
- `produto_final` (LONGTEXT NULL) — preferencialmente um por linha
- `avaliacao` (LONGTEXT NULL)

## Regras implementadas
### Descritivo do curso (renomeação)
- A coluna no banco continua `descricao_completa`.
- Na interface pública/administrativa o rótulo exibido passa a ser **“Descritivo do curso”**.

### Valor promocional
- Campo opcional.
- Validações (backend):
  - vazio ⇒ `NULL`
  - não pode ser negativo
  - se preenchido, exige `valor > 0` e `valor_promocional < valor`
- Cálculo de valor efetivo:
  - usa `valor_promocional` **somente** quando `em_promocao = 1` e o valor promocional é válido
  - caso contrário, usa `valor`
- Checkout/pedido:
  - novos pedidos passam a usar o **valor efetivo** ao criar o item do pedido
  - pedidos já criados não são recalculados

### Conteúdo programático (3 formatos)
- `texto`: salva em `conteudo_programatico_texto` e exibe com escape + `nl2br`.
- `html`: salva em `conteudo_programatico_texto` e exibe com sanitização/allowlist básica (sem `<script>`, `on*`, `javascript:`).
- `modulos`: salva JSON em `conteudo_programatico_modulos` no formato:
  ```json
  [
    { "titulo": "Módulo 1", "itens": ["Item 1", "Item 2"] }
  ]
  ```
  - módulos vazios são descartados
  - se o JSON estiver inválido na página pública, a seção não quebra o carregamento (renderiza vazio)

## Onde foi alterado
- Admin (CRUD): formulário, listagem e detalhe do curso/evento.
- Público: listagem e detalhe do curso/evento.
- Checkout: criação do pedido usa `valor_efetivo` do curso.


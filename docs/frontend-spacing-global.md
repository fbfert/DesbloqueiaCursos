# Espaçamento global do frontend

Este projeto usa duas configurações globais para controlar o ritmo visual do frontend público:

- `frontend_card_gap`: espaçamento entre cards dentro do mesmo grid, lista ou linha.
- `frontend_section_gap`: espaçamento vertical entre seções e blocos do frontend público.

## Valores padrão

- `frontend_card_gap` = `clamp(16px, 2vw, 24px)`
- `frontend_section_gap` = `clamp(24px, 3vw, 40px)`

## Escopo

As variáveis CSS correspondentes são injetadas apenas em `app-public.frontend-theme`.

Isso significa que:

- admin não é afetado visualmente;
- professor não é afetado visualmente;
- aluno não é afetado visualmente fora do frontend público;
- o layout público continua recebendo fallback seguro quando a configuração estiver vazia, ausente ou inválida.

## Regras de validação

Os dois campos aceitam somente valores CSS seguros de espaçamento, com whitelist no backend.

Exemplos aceitos:

- `16px`
- `20px`
- `1rem`
- `1.25rem`
- `2vw`
- `clamp(16px, 2vw, 24px)`
- `clamp(24px, 3vw, 40px)`

Exemplos rejeitados:

- `10px; color:red`
- `url(...)`
- `expression(...)`
- `var(--algo)`
- `<script>`
- qualquer HTML
- qualquer JavaScript

## Uso no frontend

- `frontend_card_gap` deve ser usado para separar cards dentro do mesmo grupo.
- `frontend_section_gap` deve ser usado para separar blocos e seções empilhadas.
- Margens manuais entre cards e seções devem ser evitadas quando houver `gap` no container.

## Validação manual

Para testar a resposta visual:

1. Ajuste `frontend_card_gap` para `4px` e `40px`.
2. Ajuste `frontend_section_gap` para `24px` e `40px`.
3. Verifique a home, `/cursos`, detalhe de curso, checkout e páginas institucionais públicas.


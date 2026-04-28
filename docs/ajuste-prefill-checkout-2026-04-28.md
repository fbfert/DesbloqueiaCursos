# Ajuste de prefill de CPF e telefone no checkout

Data: 2026-04-28

## Contexto
- Em alguns cenários, no fluxo de compra própria, o participante 1 recebia apenas nome e e-mail.
- CPF e telefone não estavam sendo reaproveitados de forma consistente entre sessão, cadastro e pedido.

## Ajustes aplicados
- Persistência de dados na sessão de login:
  - `usuario_cpf`
  - `usuario_telefone`
- Atualização da sessão ao salvar `minha-conta`:
  - sincroniza `usuario_cpf` e `usuario_telefone`.
- Fallback no checkout:
  - Em `/inscricao`, se `pagadorPrefill` vier sem CPF/telefone, usa os valores da sessão autenticada.
  - Em `/checkout/participantes`, para compra própria, participante 1 usa fallback de CPF/telefone da sessão, depois do pagador/pedido.

## Arquivos alterados
- `app/Services/AuthService.php`
- `app/Controllers/CheckoutController.php`
- `resources/views/checkout/inscricao.php`
- `resources/views/checkout/participantes.php`
- `resources/views/layout.php`

## Resultado esperado
- Em compra própria, participante 1 deve carregar nome, e-mail, CPF e telefone automaticamente.
- Caso CPF/telefone realmente inexistam no cadastro, o fluxo redireciona para `minha-conta`.

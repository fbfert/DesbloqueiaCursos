# Smoke Tests

Rotinas simples para validar rotas criticas do portal.

## Uso

```bash
php tests/Smoke/smoke.php https://polorainbow.com.br
```

Se nenhum argumento for informado, o script tenta ler `SMOKE_BASE_URL`.

## Escopo

- home
- catalogo publico
- login e cadastro
- recuperacao de senha
- health check da API
- redirecionamentos de areas protegidas
- validacao publica de certificados

## Resultado

- `0`: todas as rotas esperadas responderam como previsto
- `1`: ao menos uma rota falhou

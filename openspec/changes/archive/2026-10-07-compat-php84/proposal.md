# Proposal

## Why

A produção é uma VPS (AlmaLinux + Virtualmin) com PHP 8.2, 8.3 e 8.4 disponíveis por domínio. No PHP 8.4, parâmetro com default `null` sem tipo nullable explícito (`Tipo $x = null`) emite `E_DEPRECATED` — e o `App\Core\ErrorHandler` transforma qualquer aviso em página 500. Hoje há 27 ocorrências em 12 arquivos de `app/` (+1 em `tests/Smoke/smoke.php`): trocar o PHP do domínio para 8.4 derrubaria as páginas que carregam essas classes (Norminha, pagamento AbacatePay, migração de conteúdo legado, OpenAI).

## What Changes

- Tornar explícito o nullable (`?Tipo $x = null`) nas 28 declarações listadas, sem mudar nenhuma assinatura além do `?`, nenhum comportamento e nenhum chamador.
- Registrar a regra em `CLAUDE.md` (já feito) e garantir por verificação que nenhum arquivo PHP do projeto emite `E_DEPRECATED` de compilação no 8.4.

**Fora de escopo:** depreciações de tempo de execução que só aparecem ao executar caminhos específicos; mudanças no `ErrorHandler`; troca efetiva da versão de PHP na VPS.

## Capabilities

### New Capabilities
(nenhuma)

### Modified Capabilities
(nenhuma — refatoração sem mudança de comportamento; `skip_specs: true`)

## Impact

- Arquivos: `app/Services/ConteudoMigracaoLegadoService.php`, `NorminhaIaService.php`, `NorminhaService.php`, `NorminhaRateLimitService.php`, `NorminhaMemoriaService.php`, `NorminhaToolsService.php`, `NorminhaPublicoService.php`, `NorminhaPublicoLimiteService.php`, `NorminhaCustoService.php`, `OpenAIService.php`, `Payments/AbacatePayService.php`, `app/Models/PagamentoGatewayConfiguracao.php`, `tests/Smoke/smoke.php`.
- Compatível com PHP ≥ 7.1 (nullable types), portanto com 8.2, 8.3 e 8.4.
- Sem migration; deploy comum; rollback reenviando os arquivos.

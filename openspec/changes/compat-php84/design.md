# Design

## Context
Ver proposal.md. Lista exata das ocorrências gerada com `php -d error_reporting=E_ALL -l` (PHP 8.4.25) em todos os arquivos PHP versionados (exceto a biblioteca TCPDF): 28 linhas, todas "Implicitly marking parameter $x as nullable is deprecated".

## Decisions
- **Só o `?`.** Para cada parâmetro `Tipo $x = null`, escrever `?Tipo $x = null`. Não remover o default, não mudar o tipo, não reordenar parâmetros. Parâmetros sem declaração de tipo não são afetados.
- **TCPDF fora.** Biblioteca de terceiros em `app/Support/Tcpdf` não é tocada; o lint não acusou nada nela.
- **Verificação de regressão:** os testes unitários que exercitam esses serviços (Norminha, checkout) devem dar o mesmo resultado antes e depois; o lint no 8.4 deve sair sem nenhum `Deprecated`.

## Risks / Trade-offs
- [Herança: uma subclasse ou interface com a mesma assinatura sem `?`] → o lint acusaria incompatibilidade de assinatura; verificar com `php -l` em 8.3 e 8.4 e grep de `extends`/`implements` dos arquivos tocados.

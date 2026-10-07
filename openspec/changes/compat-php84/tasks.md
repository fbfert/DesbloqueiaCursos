# Tarefas

## 1. Nullable explícito

- [ ] 1.1 Registrar a linha de base: rodar no container os testes `norminha_*` (via `tests/Unit/norminha_todos.php`), `checkout_rapido_fase0.php`, `checkout_rapido_fase1.php` e o smoke, anotando os resultados (falhas pré-existentes conhecidas: norminha_knowledge 1, norminha_tools 1, quiz_system 2, checkout_rapido_fase1 1).
- [ ] 1.2 Em cada uma das 28 declarações listadas por `php -d error_reporting=E_ALL -l` (PHP 8.4 do host), trocar `Tipo $x = null` por `?Tipo $x = null`, sem outra alteração.
- [ ] 1.3 Verificar: o mesmo lint em todos os arquivos PHP versionados (exceto TCPDF) sai sem nenhum `Deprecated`; `php -l` no container (8.3) sem erros nos arquivos tocados; grep de `extends`/`implements` nos arquivos tocados sem assinatura incompatível.
- [ ] 1.4 Repetir os testes de 1.1 e o smoke — resultados idênticos à linha de base.
- [ ] 1.5 Commit "Nullable explicito para PHP 8.4".

# Tarefas

## 1. Otimizador

- [x] 1.1 Escrever `tests/Unit/otimizador_imagem.php` (gera imagens de teste com GD num diretório temporário; sem banco): PNG 1672×941 opaco → largura 1280, altura 720 (±1), formato jpg (ou webp se suportado), tamanho menor que o original; PNG 800×450 → largura 800; PNG com transparência e sem WebP → png com alpha preservado; GIF animado (2 frames) → ok=false com motivo; arquivo corrompido → ok=false sem warning nem exceção; imagem acima do limite de megapixels (opção `max_megapixels` pequena no teste) → ok=false.
- [x] 1.2 Rodar no container — falha (classe inexistente).
- [x] 1.3 Implementar `App\Support\OtimizadorImagem` conforme design.md §1 (compatível com PHP 8.2–8.4; parâmetros nullable explícitos).
- [x] 1.4 Rodar o teste — passa, saída limpa.

## 2. Upload

- [x] 2.1 `CursoService::salvarThumbnailUpload()` e `CategoriaService::salvarThumbnailUpload()` chamam o otimizador conforme design.md §2; validações e mensagens idênticas.
- [x] 2.2 Verificar por HTTP no ambiente local (admin.homologacao@polorainbow.com.br / Local@12345): enviar uma capa PNG grande num curso → arquivo gravado otimizado e curso apontando para ele; GIF animado → original gravado; 6 MB → mesma mensagem de erro de antes. Repetir uma vez para categoria. Limpar os dados de teste.

## 3. Script de manutenção

- [x] 3.1 Implementar `scripts/otimizar_capas.php` conforme design.md §3 (simulação padrão, `--aplicar`, `--reverter=`), com a mesma inicialização dos scripts existentes em `scripts/`.
- [x] 3.2 Verificar no ambiente local com a fixture (capas em assets/uploads/thumbnails): simulação lista e não altera; aplicar troca as referências e grava manifesto; reverter restaura; capa referenciada ausente é relatada e ignorada.
- [x] 3.3 Documentar em `docs/deploy.md` (seção curta "Capas otimizadas") como rodar na VPS e como reverter; atualizar o item das capas nas Pendências de `docs/2026-10-06-tema-caderno.md`.

## 4. Validação

- [x] 4.1 `php -l` (container) e lint sem `Deprecated` no PHP 8.4 do host em todos os arquivos tocados; `tests/Unit/otimizador_imagem.php` e smoke passam.
- [x] 4.2 Revisar textos de interface e de log em PT-BR com acentuação.

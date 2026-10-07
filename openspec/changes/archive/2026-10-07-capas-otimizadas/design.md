# Design

## Context
`CursoService::salvarThumbnailUpload()` (~linhas 428-480) e `CategoriaService::salvarThumbnailUpload()` (~linhas 253-300) validam tamanho (5 MB), extensão e MIME, geram um nome seguro (`thumb-…`/`categoria-…`) e fazem `move_uploaded_file` para `assets/uploads/thumbnails` ou `assets/uploads/categorias`, devolvendo o caminho público gravado no banco. O GD está presente (JPEG e PNG); WebP depende de como o PHP foi compilado (no Docker local não há; na VPS AlmaLinux o pacote `php-gd` costuma ter).

## Decisions

### 1. `App\Support\OtimizadorImagem` (sem estado, testável)
```php
OtimizadorImagem::otimizar(string $origem, string $destinoSemExtensao, array $opcoes = array()): array
// retorno: array('ok' => bool, 'caminho' => string, 'extensao' => 'webp'|'jpg'|'png', 'motivo' => string)
OtimizadorImagem::suportaWebp(): bool
```
- Opções: `largura_max` (1280), `qualidade_webp` (80), `qualidade_jpeg` (82), `max_megapixels` (40).
- Decodifica com `getimagesize` + `imagecreatefrom{jpeg,png,webp,gif}`; recusa (ok=false, motivo) GIF animado (mais de um frame), imagem acima do limite de megapixels, tipos sem decodificador, arquivo corrompido — sem deixar escapar warning (o `ErrorHandler` do projeto transformaria em 500).
- Redimensiona com `imagecopyresampled` mantendo proporção; preserva alpha (`imagealphablending(false)` + `imagesavealpha(true)`) quando o destino suporta.
- Formato: WebP se `suportaWebp()`; senão PNG nível 9 se a imagem tem transparência real; senão JPEG.
- Escreve em arquivo temporário e renomeia (escrita atômica); libera memória (`imagedestroy`).

### 2. Uso no upload
Depois de todas as validações atuais e do `move_uploaded_file` para o nome seguro, os dois Services chamam o otimizador sobre o arquivo gravado, com destino `<mesmo nome sem extensão>`. Se `ok`, o caminho público passa a ser o do arquivo otimizado e o arquivo recém-enviado é removido (ainda não é referenciado por ninguém). Se não, mantém o original e registra `Logger::warning('midia.capa.otimizacao_ignorada', motivo)`. Mensagens e validações não mudam.

### 3. Script `scripts/otimizar_capas.php`
- CLI: `php scripts/otimizar_capas.php [--aplicar] [--reverter=<manifesto>] [--largura=1280]`, carregando Env e Database no padrão dos scripts existentes em `scripts/`.
- Candidatas: valores distintos de `cursos_eventos.thumbnail` e `categorias.thumbnail` (registros não excluídos) que apontem para `/assets/uploads/thumbnails/` ou `/assets/uploads/categorias/`, com arquivo existente e largura > 1280 ou tamanho > 300 KB. URLs externas são ignoradas.
- `--aplicar`: para cada arquivo, gera `<nome>-otm.<ext>` ao lado; em uma transação atualiza todas as linhas que apontam para o caminho antigo; grava o manifesto JSON em `storage/app/otimizar_capas/<data-hora>.json` (`[{tabela, id, de, para}]`). Os originais ficam — conteúdo de aula ou e-mails antigos podem apontar para eles.
- `--reverter`: lê o manifesto e restaura `de` em cada linha, em transação.
- Saída em texto: tabela com caminho, tamanho atual, tamanho otimizado ou estimado e total economizado.

## Risks / Trade-offs
- [VPS sem WebP] → JPEG q82 ainda reduz ~1,9 MB → ~200–300 KB.
- [Memória no decode] → limite de 40 MP; 1672×941 decodifica com folga no limite padrão de 128 MB.
- [Perda de qualidade perceptível] → qualidade 80/82 em 1280 px é visualmente equivalente em tela de celular; originais preservados no disco.
- [Lista de "thumbnails existentes" no admin] → continua mostrando originais e otimizadas; ambas funcionam.

## Migration Plan
Deploy do código; na VPS: `php scripts/otimizar_capas.php` (simulação), conferir, `php scripts/otimizar_capas.php --aplicar`, guardar o caminho do manifesto. Rollback: `--reverter=<manifesto>`.

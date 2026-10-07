# Proposal

## Why

As capas de cursos e categorias são gravadas exatamente como chegam no upload. As capas reais têm 1,7–2,0 MB cada (PNG de ~1672 px), e a home e o catálogo mostram várias por página: no celular de entrada com dados pré-pagos — o público principal —, isso domina o tempo de carregamento e o consumo de dados, e é pré-requisito para virar a chave do tema caderno. Pedir que a equipe reexporte cada capa manualmente não escala: toda capa nova volta pesada.

## What Changes

- Toda imagem enviada como capa de curso ou de categoria passa a ser **redimensionada e comprimida no servidor** no momento do upload: largura máxima de 1280 px (proporção preservada, nunca amplia) e recodificação em WebP (qualidade 80) quando o PHP tem suporte, senão JPEG (qualidade 82); imagens com transparência sem suporte a WebP viram PNG comprimido. O visual não muda — só o peso.
- Se a otimização falhar por qualquer motivo (formato estranho, memória, GIF animado, imagem gigantesca), o upload **continua funcionando** com o arquivo original, e o motivo é registrado no log.
- Um **script de manutenção** (`scripts/otimizar_capas.php`) otimiza as capas já existentes: simulação por padrão, aplicação só com `--aplicar`; cria a versão otimizada ao lado da original, atualiza as referências no banco (`cursos_eventos.thumbnail`, `categorias.thumbnail`) em transação, **não apaga nem move** os originais e grava um manifesto que permite desfazer com `--reverter`.

**Fora de escopo:** imagens dentro do conteúdo das aulas (editor), fotos de usuário, avatar da Norminha, certificados; geração de várias resoluções (`srcset`); CDN; apagar os originais.

## Capabilities

### New Capabilities
- `midia-cursos`: tratamento das imagens de capa de cursos e categorias — otimização no upload e manutenção das capas existentes.

### Modified Capabilities
(nenhuma)

## Impact

- Código novo: `app/Support/OtimizadorImagem.php`, `scripts/otimizar_capas.php`, `tests/Unit/otimizador_imagem.php`.
- Código alterado: `CursoService::salvarThumbnailUpload()` e `CategoriaService::salvarThumbnailUpload()` (chamam o otimizador depois das validações atuais, que continuam iguais).
- Dependência: extensão GD do PHP (já usada pelo projeto). WebP depende do GD compilado com libwebp — o código detecta e cai para JPEG.
- Sem migration. Na VPS, depois do deploy: rodar o script em simulação, conferir, rodar com `--aplicar`.

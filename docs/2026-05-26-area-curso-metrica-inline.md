# Area Curso - Metricas Inline

Data: 2026-05-26

Resumo:
- Refatoracao da tela do aluno em `resources/views/area-curso/index.php`.
- Compactacao visual dos metadados de progresso, modulo e item.
- Substituicao da estrutura antiga por pills inline com SVG.
- Ajuste de CSS em `public_html/assets/css/frontend.css` para impedir quebra interna nas pílulas.
- Nenhuma regra de negocio foi alterada.

Arquivos tocados:
- `app/Controllers/AreaCursoController.php`
- `resources/views/area-curso/index.php`
- `resources/views/area-curso/conteudo_item.php`
- `public_html/assets/css/frontend.css`

Observacoes:
- A interface do aluno passou a usar metadados inline para evitar empilhamento vertical no mobile.
- Os icones estao em SVG inline com acessibilidade preservada por `title`, `aria-label` e `sr-only`.
- A publicacao foi feita por FTP e validada no remoto.

# Certificados: fonte de `{aluno_data_inicio}`

Correção pontual no placeholder `{aluno_data_inicio}` para usar a data de criação do pedido real do aluno.

Regra final:

- `{aluno_data_inicio}` = `pedidos.created_at`
- vínculo usado: `certificados.inscricao_id -> inscricoes.id -> inscricoes.pedido_id -> pedidos.id`
- `{aluno_data_fim}` permanece vinculado a `certificados.emitido_em`

Validação no banco ao vivo:

- certificado: `DESRTKQ5NH`
- `pedidos.created_at`: `2026-06-05 20:00:15`
- `{aluno_data_inicio}` renderizado: `05/06/2026`
- `certificados.emitido_em`: `2026-06-18 22:17:37`
- `{aluno_data_fim}` renderizado: `18/06/2026`

Nenhum outro placeholder, HTML, layout ou fluxo de certificado foi alterado nesta correção.

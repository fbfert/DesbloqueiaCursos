# Hardening Checklist

## Problemas encontrados

- Formularios POST sem protecao CSRF centralizada.
- Middleware de autenticacao e permissao sem log de negacao.
- Uploads sem validacao de extensao, MIME e tamanho.
- Checkout aceitava ids de pedido sem reforco suficiente de ownership em pontos de escrita.
- Progresso da area do curso permitia concluir aula/modulo sem validar posse da inscricao.
- Upload de comprovante PIX nao validava posse do pedido.
- PDF de certificado podia ser exposto sem checagem de posse.

## Correcoes aplicadas

- Adicionado `CsrfMiddleware` e validacao automatica em todas as rotas `POST`.
- O renderer de views passou a injetar hidden field CSRF em formularios `POST`.
- `AuthenticateMiddleware` e `PermissionMiddleware` agora registram log e auditoria de bloqueio.
- `PedidoService` ganhou checagem de ownership/permissao antes de mutacoes de checkout e backoffice.
- `ComprovantePixService` passou a validar ownership/permissao antes de aceitar upload e antes de aprovar/reprovar.
- `InscricaoService` ganhou checagem de ownership/permissao para gerar inscricoes e alterar status.
- `ProgressoService` passou a validar posse da inscricao antes de marcar aula/modulo concluido.
- `FileStorageService` agora valida tamanho, extensao e MIME.
- Material, comprovante PIX e documentos/pagamentos financeiros passaram a usar allowlists de upload.
- O PDF do certificado passou a respeitar ownership/permissao; a validacao publica continua disponivel.

## Pendencias restantes

- Rate limiting em login e endpoints sensiveis.
- Cabecalhos HTTP de seguranca mais agressivos.
- Assinatura de links temporarios para downloads privados.
- 2FA/MFA para perfis administrativos.
- Revisao fina do fluxo de distribuicao de PDF de certificado por e-mail.
- Monitoramento automatizado de tentativas repetidas de abuso e alertas.

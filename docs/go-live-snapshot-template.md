# Go-live Snapshot Template

Registro do fechamento do deploy.

## Snapshot

- Commit publicado: `136a6fe` - `chore: preflight final aprovado para go-live controlado`
- Horario de inicio: `2026-04-23 16:18:47 (aproximado)`
- Horario de fim: `2026-04-23 16:34:25`
- Migrations executadas: `001_auth_module.sql`, `002_rbac_access.sql`, `003_catalogo.sql`, `004_catalogo_refino.sql`, `005_pedidos_inscricoes.sql`, `006_pedidos_refino.sql`, `007_status_and_coupon_prep.sql`, `008_cupons.sql`, `009_email_transacional.sql`, `010_certificados.sql`, `011_area_curso.sql`, `012_presenca_avaliacao.sql`, `013_configuracoes_globais.sql`, `015_financeiro.sql`
- Responsavel: operador local do deploy
- Smoke test: pulado
- Homologacao rapida: validacao parcial concluida, sem smoke
- Observacoes: manutencao ativada antes do deploy; backup realizado; publicacao concluida; migrations aplicadas; validacao pos-deploy executada sem smoke; checkpoint final nao possui confirmacao automatizada completa
- Decisao final: rollback

## Detalhe operacional

- Ambiente: Linux/cPanel
- Versao do PHP: nao registrada nesta instancia
- Banco: MySQL 5.7
- SMTP: nao validado nesta instancia
- Storage privado: estrutura prevista fora de `public_html`
- Manutencao ativada em: antes do backup
- Manutencao removida em: pendente

## Evidencias minimas

- Rotas criticas validadas: validacao manual parcial, smoke nao executado
- Checkout validado: nao confirmado
- Certificado validado: nao confirmado
- Dashboard admin validado: nao confirmado
- Dashboard professor validado: nao confirmado
- Upload privado validado: nao confirmado
- Logs e auditoria verificados: nao confirmados nesta instancia

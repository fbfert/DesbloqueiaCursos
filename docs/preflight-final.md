# Preflight Final

Revisao final de pre-go-live do Polo Rainbow.

## Consistencia operacional

Os documentos estao consistentes entre si:

- `docs/deploy.md`
- `docs/go-live-checklist.md`
- `docs/rollback.md`
- `docs/go-live-30min.md`
- `docs/post-deploy-validation.md`
- `docs/go-live-snapshot-template.md`
- `docs/release-readiness.md`

A ordem operacional esta coerente:

1. manutencao
2. backup
3. publicacao do codigo
4. migrations pendentes
5. smoke
6. validacao pos-deploy
7. snapshot
8. saida da manutencao

## Pendencias bloqueantes

Nenhuma pendencia documental bloqueante foi identificada.

## Pendencias nao bloqueantes

- confirmar no ambiente final a versao do PHP e extensoes obrigatorias
- confirmar os limites reais de upload e memoria do host
- confirmar SMTP em conta de producao
- confirmar escrita em `storage/private_uploads/certificados`
- registrar o snapshot de go-live apos a publicacao

## Recomendacao final

**Publicar com ressalvas**

O material operacional esta pronto para uso, mas a liberacao real deve depender das confirmacoes finais no ambiente de producao:

- backup confirmado
- manutencao ativa
- migrations pendentes identificadas e aplicadas
- smoke test concluido
- validacao pos-deploy concluida
- snapshot final preenchido

Se qualquer validação critica falhar, acionar rollback A ou B conforme o tipo de problema.


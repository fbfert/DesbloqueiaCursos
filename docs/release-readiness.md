# Release Readiness

Status operacional consolidado para a publicacao do Polo Rainbow.

## Status geral

**Pronto com ressalvas**

O conjunto documental esta coerente para publicacao em Linux/cPanel, mas a operacao depende de confirmacoes executivas no ambiente real:

- backup valido do banco e dos arquivos
- SMTP realmente funcionando
- storage privado gravavel
- migrations pendentes corretamente mapeadas
- smoke test e homologacao rapida executados no ambiente final

## Pendencias bloqueantes

Nenhuma pendencia documental bloqueante identificada.

## Pendencias nao bloqueantes

- confirmar no ambiente final a versao de PHP e extensoes
- confirmar os limites reais de upload e memoria do host
- confirmar o envio real de SMTP com conta de producao
- confirmar escrita em `storage/private_uploads/certificados`
- registrar o snapshot de go-live apos a publicacao

## Recomendacao final de publicacao

Publicar somente com:

1. modo manutencao ativo
2. backup do banco concluido
3. backup dos arquivos concluido
4. migrations pendentes aplicadas e registradas
5. storage privado validado
6. SMTP validado
7. rotas criticas respondendo
8. checkout validado
9. certificado validado
10. dashboards admin e professor validados
11. snapshot final preenchido

Se qualquer um dos itens acima falhar em ambiente real, acionar rollback A ou B conforme o tipo de falha.


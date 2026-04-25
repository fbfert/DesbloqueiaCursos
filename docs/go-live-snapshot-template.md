# Go-live Snapshot

## Informações gerais

**Projeto:** Polo Rainbow  
**Ambiente:** Produção  
**Data:** 23/04/2026  
**Responsável pelo deploy:** Seu nome aqui

---

## Publicação

**Commit publicado:** 96b4e0c  
**Tag:** v1.0.1-rc  
**Horário de início:** 14:05  
**Horário de término:** 14:42

---

## Banco de dados

**Migrations executadas:**
- sql/016_rateios_crud.sql

**Backup realizado antes do deploy:** SIM

**Backup pós deploy realizado:** SIM

---

## Smoke Test

**Resultado geral:** OK

**Rotas validadas:**
- /
- /login
- /admin/dashboard
- /admin/financeiro
- /admin/rateios
- /professor/dashboard
- /certificados/validar

**Observações:**
Nenhum erro 500 encontrado.

---

## Validação manual

### Rateios

- criação rateio PF: OK
- criação rateio PJ: OK
- edição percentual: OK
- bloqueio acima de 75%: OK
- bloqueio de duplicidade: OK
- exclusão com justificativa: OK
- auditoria gerada: OK
- reflexo no financeiro: OK

### Certificados

- emissão manual: OK
- QR Code funcionando: OK
- validação pública: OK

### Checkout

- comprovante PIX enviado: OK
- aprovação administrativa: OK

---

## Pendências encontradas

- nenhuma

ou

- pequeno ajuste visual no formulário de rateios
- revisar label do campo X

---

## Decisão final

☑ MANTER EM PRODUÇÃO  
☐ ROLLBACK A (código)  
☐ ROLLBACK B (código + banco)

---

## Observações finais

Deploy concluído com sucesso.
Sistema mantido em produção e monitoramento ativo nas próximas 24h.

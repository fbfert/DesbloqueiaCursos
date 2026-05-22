# Checklist de Homologação — LMS Conteúdo Unificado

- [ ] Backup completo do banco de dados.
- [ ] Backup de arquivos (`storage/private_uploads` e anexos legados).
- [ ] Confirmar aplicação dos SQLs (`038`, `039`, `040`) no ambiente alvo.
- [ ] Executar diagnóstico da migração legada.
- [ ] Executar `dry-run` da migração por curso.
- [ ] Executar migração real por curso.
- [ ] Reexecutar migração para validar idempotência.
- [ ] Validar aba `Conteúdo` no admin.
- [ ] Validar aba `Conteúdo` no professor (escopo autorizado).
- [ ] Validar área do aluno (`/area-curso` e `/aluno/cursos`).
- [ ] Validar avaliação textual (envio, correção, feedback).
- [ ] Validar regras de certificado com conteúdo obrigatório.
- [ ] Validar exportações CSV relacionadas ao conteúdo.
- [ ] Validar permissões do professor (bloqueio fora de escopo).
- [ ] Validar upload/download de arquivos.
- [ ] Validar envio de e-mail dos fluxos de avaliação textual.
- [ ] Validar logs e auditoria (acesso, progresso, correção, certificado).
- [ ] Validar plano de rollback (dados + aplicação).


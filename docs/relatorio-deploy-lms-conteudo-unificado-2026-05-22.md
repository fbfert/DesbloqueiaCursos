# Relatório de deploy controlado — LMS Conteúdo Unificado

Data: 2026-05-22

## Escopo
- Deploy controlado do LMS Conteúdo Unificado.
- Sem alteração de certificados, elegibilidade, regras financeiras ou legado físico.
- Sem aplicar migração global em produção.

## Backup
- Banco de produção salvo em `storage/backups/backup_pre_lms_conteudo_20260522_182829.sql`.
- Backup local do pacote preparado em `deploy/_backup_local/pacote_lms_conteudo_unificado_20260522_164733.zip`.

## Arquivos enviados
- SQL: `sql/038_conteudo_unificado_lms.sql`, `sql/039_conteudo_unificado_lms_hardening.sql`, `sql/040_conteudo_migracao_legado.sql`.
- Models, Services, Controllers, Views, Rotas, Suporte, Script de migração e documentação do LMS Conteúdo Unificado.

## Arquivos não enviados
- `.env`
- `storage/private_uploads/**`
- `storage/7a_*.json`
- `storage/tmp/*`
- `storage/test_uploads/*`
- `storage/sessions/*`
- `storage/cookies_*.txt`
- `deploy/_backup_local/*`
- `.codex/*`
- artefatos de QA/homologação e qualquer arquivo com segredo

## Migração
- Estrutura já existia no servidor; `038`, `039` e `040` não precisaram ser reaplicadas.
- Diagnóstico do legado:
  - `5` módulos
  - `5` aulas
  - `9` materiais
  - `1` link externo
  - `0` atividades
  - `0` entregas antigas
- Cursos afetados: `1, 4, 6, 9`.
- Curso piloto migrado: `1`.
- Dry-run do curso 1 sem inconsistências.
- Reexecução do curso 1 idempotente: `0` itens migrados, `2/2/4/1/0` ignorados.

## Validações
- Redirecionamentos legados para `aba=conteudo` confirmados.
- Conteúdo exibido em admin, professor e aluno.
- Abas antigas removidas da navegação principal.
- Runner temporário remoto removido após o uso.

## Pendências conhecidas
- `/admin/academico` legado.
- PDF público `/certificados/pdf?codigo=...` com `403` em ambiente local.
- Entregas antigas não migradas automaticamente.

## Conclusão
- Deploy controlado concluído com sucesso.
- Migração por curso validada no piloto.
- Sistema pronto para seguir com migração dos demais cursos sob o mesmo procedimento.

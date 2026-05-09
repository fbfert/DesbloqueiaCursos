# Validação pós-deploy (parcial) — Área interna do curso (2026-05-06)

## Escopo validado

Deploy já realizado anteriormente com escopo estrito:

- `resources/views/admin/area-curso/index.php`
- `public_html/assets/css/admin.css`

Backup remoto informado:

- `storage/backups/deploy-20260506-220532`

Nesta rodada foi feita apenas validação pós-deploy (sem alteração de código e sem novo envio FTP).

## Contas de teste utilizadas

- Admin homologação: `admin.homologacao@polorainbow.com.br`
- Professor homologação: `professor.homologacao@polorainbow.com.br`
- Aluno homologação: `aluno.homologacao@polorainbow.com.br` (não autenticou com a credencial de seed usada na automação)

## Rotas testadas e resultados

### Admin

- `GET /admin/area-curso?curso_id=6` → **200 OK**
  - sem erro 500;
  - sem exigência de turma no topo;
  - bloco **Refinar turma** presente;
  - abas detectadas: **Visão geral**, **Módulos e aulas**, **Materiais**, **Atividades**, **Relatórios**.

- `GET /admin/area-curso?curso_id=6&turma_id=6` → **200 OK**
  - contexto de turma abriu corretamente;
  - sem erro 500.

### Professor vinculado

- `GET /professor/area-curso?curso_id=6&turma_id=6` → **200 OK**

### Professor não vinculado / contexto inválido

- `GET /professor/area-curso?curso_id=6&turma_id=999` → **403 Forbidden** (bloqueio seguro)
- `GET /professor/area-curso?curso_id=6` → **403 Forbidden** (bloqueio seguro)

### Aluno

- `GET /aluno/meus-cursos` com a conta de seed testada: retorno para tela de login (pendente credencial de aluno válida para concluir esse cenário funcional).

### Regressão checkout

- `GET /inscricao?curso_id=6&turma_id=6` (sessão autenticada de admin) → **200 OK**
- `GET /checkout/sucesso?pedido_id=25` (sessão autenticada de admin) → **200 OK**, com “Pedido recebido”.
- Validação anônima prévia já havia confirmado redirecionamento para `/login` em `/checkout/sucesso`.

## Logs

Leitura de `storage/logs` via FTP:

- não foi identificado novo `app.error`/500 diretamente associado ao deploy parcial desta tela;
- entradas encontradas durante a automação:
  - `seguranca.autenticacao.ausente` em acessos sem sessão;
  - `seguranca.csrf.rejeitado` nas tentativas iniciais sem token CSRF.

Não houve indício novo de erro fatal na view alterada.

## Limpeza

- sem criação de usuários em produção;
- arquivos/cookies temporários de teste locais removidos ao final.

## Conclusão

- O deploy parcial está consistente para os fluxos de **admin** e **professor** testados.
- Permanece pendente apenas a validação autenticada final com **conta de aluno válida em produção** para encerramento 100% formal.

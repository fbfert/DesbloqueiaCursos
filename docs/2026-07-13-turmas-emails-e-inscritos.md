# Turmas: comunicados por e-mail e listagem de inscritos

Duas funcionalidades novas na coluna "Ações" de `/admin/turmas`: o botão **Ver Inscritos** e o botão **E-mail**.

## Arquivos criados

- `sql/064_turma_emails.sql`
- `app/Models/TurmaEmail.php`
- `app/Services/TurmaEmailService.php`
- `app/Services/TurmaInscritosExportService.php`
- `app/Controllers/Admin/TurmaEmailsController.php`
- `app/Controllers/Admin/TurmaInscritosController.php`
- `resources/views/admin/turmas/emails/index.php`
- `resources/views/admin/turmas/emails/form.php`
- `resources/views/admin/turmas/emails/show.php`
- `resources/views/admin/turmas/inscritos/index.php`
- `assets/js/turma-email-envio.js`

## Arquivos alterados

- `app/Models/EmailEnvio.php` — consultas por entidade (`listByEntidade`, `pendentesByEntidade`, `resumoByEntidade`).
- `app/Models/Inscricao.php` — listagem de inscritos escopada à turma, com filtros e whitelist de ordenação.
- `app/Services/EmailService.php` — `queueCustomHtml()` (enfileira sem enviar) e `sendQueuedCustomHtml()` (envia um pendente).
- `app/Services/InscricaoService.php` — `listarInscritosDaTurma()`.
- `assets/css/admin.css` — barra de progresso do envio e caixa de pré-visualização do e-mail.
- `routes/web.php`, `resources/views/admin/turmas/index.php`.

## Tabela criada

`turma_emails` — um registro por comunicado (lote): `turma_id`, `assunto`, `corpo_html`, `total_destinatarios`, `criado_por_usuario_id`. Chaves estrangeiras para `turmas` (ON DELETE CASCADE) e `usuarios` (ON DELETE SET NULL).

Os destinatários **não** ganharam tabela nova: cada um vira uma linha em `emails_envios` com `entidade_tipo = 'turma_email'` e `entidade_id = turma_emails.id`. Isso reaproveita a fila, o reenvio e a auditoria de e-mail que já existiam no admin.

## Rotas

| Rota | Permissão |
|---|---|
| `GET /admin/turmas/inscritos` | `conteudo.ver` |
| `GET /admin/turmas/inscritos/exportar` (CSV) | `conteudo.ver` |
| `GET /admin/turmas/inscritos/exportar-pdf` | `conteudo.ver` |
| `GET /admin/turmas/emails` (histórico) | `conteudo.ver` |
| `GET /admin/turmas/emails/novo` | `conteudo.gerenciar` |
| `POST /admin/turmas/emails/enviar` | `conteudo.gerenciar` |
| `POST /admin/turmas/emails/processar` (AJAX) | `conteudo.gerenciar` |
| `GET /admin/turmas/emails/detalhe` | `conteudo.ver` |

As telas usam `conteudo.*` e não `emails.*` de propósito: as permissões `emails.ver`/`emails.gerenciar` não estão vinculadas a nenhum perfil no banco (só o Superadmin as tem, por bypass), então o perfil **Conteudo** — que é quem administra turmas — tomaria 403.

## Envio de e-mail em lotes

O envio **não** é síncrono. `criarLote()` grava o comunicado e enfileira um `emails_envios` por destinatário com status `pendente`; a tela de detalhe então chama `POST /admin/turmas/emails/processar` repetidamente via AJAX (10 por vez, `assets/js/turma-email-envio.js`), mostrando barra de progresso.

O motivo é concreto: **não há worker de fila rodando no servidor**, e a maior turma tem 52 alunos. Enviar dezenas de e-mails SMTP dentro de um único POST estouraria o tempo limite do PHP. Como cada destinatário é uma linha independente em `emails_envios`, uma falha parcial é recuperável pela fila de e-mails já existente (`/admin/emails/fila`).

Se o processamento parar sem enviar nada (SMTP fora do ar, por exemplo), o JS interrompe em vez de girar em falso e orienta a usar a fila.

### Conteúdo e placeholders

Assunto e corpo são escritos na hora do envio (CKEditor 5, via a classe `js-email-html-editor` já usada nos modelos de e-mail). O HTML passa por `Helpers::decodeEditorHtml()` → `HtmlSanitizer::clean(..., 'full')` antes de ser gravado.

Placeholders trocados por destinatário: `{usuario.nome}`, `{usuario.email}`, `{turma.nome}`, `{turma.codigo}`, `{turma.data_inicio}`, `{turma.data_fim}`, `{curso.nome}`, `{sistema.nome}`.

O assunto é renderizado no momento de enfileirar; o corpo, no momento do envio (o HTML fica guardado uma única vez em `turma_emails.corpo_html`, não replicado por destinatário).

### Destinatários

`Inscricao::forTurmaMatriculados()` — inscrições com status `ativa`, `em_andamento`, `concluida`, `concluida_sem_certificado` ou `certificado_emitido`, sem `deleted_at`. E-mails inválidos ou repetidos são descartados.

**Atenção:** a tela de inscritos mostra *todos* os inscritos da turma, enquanto o e-mail vai só para os matriculados acima. Na turma 8, por exemplo, são 70 inscritos mas 43 destinatários — a diferença são 26 `pendente` e 1 `cancelada`. Não é bug; são regras diferentes.

## Listagem de inscritos

`/admin/turmas/inscritos?turma_id=X` — escopo acadêmico, sem dados financeiros (não mostra pedido nem pagador, por isso vive sob `conteudo.ver` e não sob `pedidos.ver` como `/admin/inscricoes`).

- Busca por nome, e-mail ou telefone.
- Filtro por status da inscrição, itens por página (20/50/100).
- Colunas ordenáveis: aluno, e-mail, status, progresso, nota, data de inscrição.
- Colunas exibidas: aluno, e-mail, **telefone**, status, progresso, presença, nota, apto ao certificado, inscrito em.
- Resumo por status no topo; paginação.

O CPF **não** é exibido nem exportado (decisão de produto); a busca por CPF também foi removida junto.

## Exportação

Botões **Exportar CSV** e **Exportar PDF**. Ambos respeitam a busca, o filtro de status e a ordenação da tela, mas **ignoram a paginação** — levam todas as linhas do resultado filtrado, não só a página visível.

- CSV: BOM UTF-8 + separador `;` (mesma convenção de `FinanceiroService`/`RelatorioLmsService`; abre no Excel pt-BR com acentos e colunas corretos).
- PDF: TCPDF em paisagem A4, com cabeçalho da turma e os filtros aplicados impressos.
- Teto de 5.000 linhas por arquivo; truncamento vai para o log (`turmas.inscritos.exportar_truncado`).
- Toda exportação é registrada em auditoria (`turmas.inscritos.exportado`: quem, turma, formato, nº de linhas, filtros) — os arquivos carregam dados pessoais (nome, e-mail, telefone).

## Validação real

- `php -l` e `node --check` sem erros em todos os arquivos criados/alterados.
- Migration validada primeiro em banco descartável (com o schema de `turmas`/`usuarios` replicado para as FKs) e só então aplicada em produção.
- Listagem exercitada contra o banco real na turma 8 (70 inscritos): paginação não repete registros entre páginas, filtro de status não vaza, busca por nome encontra, ordenação inverte de fato, turma inexistente retorna vazio.
- Injeção tentada e descartada pelas whitelists, sem erro: `status = "' OR 1=1 --"` e `sort_by = "u.senha_hash; DROP TABLE x"`.
- Sanitizador exercitado com XSS proposital: `<script>`, `onclick` e `href="javascript:"` removidos; placeholders trocados corretamente.
- CSV e PDF gerados de verdade: com `status=ativa` o arquivo sai com 43 linhas (não as 20 da página); PDF válido (`%PDF`, 115 KB).

**Não exercitado:** o disparo SMTP de ponta a ponta, porque qualquer teste real enviaria e-mail para 43 alunos de verdade. Para validar, crie uma turma de teste com uma inscrição no próprio e-mail.

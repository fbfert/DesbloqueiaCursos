# Exclusão de e-mails com falha (Admin)

Tela: `/admin/emails/fila`

## Objetivo

Permitir que o administrador exclua registros de envio de e-mail **somente** quando o status do envio estiver com falha, para limpar a fila/histórico operacional sem risco de remover pendentes ou enviados.

## Regras

- Permitido excluir apenas quando `status = falhou`.
- Não permite excluir:
  - `status = enviado`
  - `status = pendente`
  - qualquer outro status que não seja `falhou` (caso seja adicionado no futuro).
- A exclusão é feita via **POST** e exige **CSRF**.
- A justificativa é **obrigatória**.
- Antes de excluir, o sistema registra um snapshot do registro na tabela **`lixeira`** (via `TrashService`), para auditoria e reversibilidade administrativa.

## Rotas

- Exclusão individual:
  - `POST /admin/emails/excluir-falha`
  - Payload: `id`, `justificativa`, `_token`

- Exclusão em lote:
  - `POST /admin/emails/excluir-falhas-selecionadas`
  - Payload: `ids[]`, `justificativa`, `_token`

## Interface (UI)

- Na listagem, o botão **“Excluir”** aparece apenas quando `status = falhou`.
- Na área de ações em lote, existe o botão **“Excluir falhas selecionadas”**.
- Ambos pedem uma justificativa via `prompt()` no navegador.

## Implementação (resumo)

### Controller

Arquivo: `app/Controllers/Admin/EmailsController.php`

- `excluirFalha()`:
  - valida CSRF
  - lê `id` e `justificativa`
  - chama `EmailAdminService::excluirFalha(...)`
  - retorna com flash de sucesso/erro e redirect para `/admin/emails/fila`

- `excluirFalhasSelecionadas()`:
  - valida CSRF
  - lê `ids[]` e `justificativa`
  - chama `EmailAdminService::excluirFalhasSelecionadas(...)`
  - flash com total de excluídos/ignorados e redirect para `/admin/emails/fila`

### Service

Arquivo: `app/Services/EmailAdminService.php`

- `excluirFalha($emailId, $justificativa, $actorUserId, $ip, $ua)`:
  - exige justificativa
  - busca o registro por ID
  - valida `status === 'falhou'`
  - registra na `lixeira` (`TrashService::record('email_envio', ...)`)
  - exclui via model (`EmailEnvio::deleteFailedById`)

- `excluirFalhasSelecionadas(array $ids, ...)`:
  - exige justificativa
  - tenta excluir cada ID
  - contabiliza `excluidos` e `ignorados`

### Model

Arquivo: `app/Models/EmailEnvio.php`

- `deleteFailedById($id)`:
  - `DELETE FROM emails_envios WHERE id = :id AND status = 'falhou' LIMIT 1`
  - retorna `true` quando removeu (rowCount > 0)

## Tipo de exclusão

- Exclusão **física** em `emails_envios` (DELETE), com registro prévio na `lixeira`.
- Não foi criada migration.

## Testes manuais (checklist)

1. Acessar `/admin/emails/fila`.
2. Confirmar que `falhou` exibe “Excluir” e `enviado/pendente` não exibe.
3. Excluir um registro `falhou`:
   - informar justificativa
   - confirmar mensagem de sucesso
4. Forçar POST para excluir um `enviado`:
   - deve retornar erro “Este e-mail não pode ser excluído.”
5. Selecionar múltiplos registros e usar “Excluir falhas selecionadas”:
   - exclui apenas os `falhou`, ignora os demais
6. Confirmar que o registro aparece na tabela `lixeira` com snapshot.

## Arquivos alterados

- `routes/web.php`
- `app/Controllers/Admin/EmailsController.php`
- `app/Services/EmailAdminService.php`
- `app/Models/EmailEnvio.php`
- `resources/views/admin/emails/fila.php`


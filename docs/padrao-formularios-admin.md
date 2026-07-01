# Padrão de formulários administrativos

Este documento define o contrato mínimo para formulários da área administrativa.

## Regras obrigatórias

1. Todo formulário administrativo `POST` deve incluir CSRF.
2. Toda edição deve enviar o campo oculto `id`.
3. Toda `action` deve ser explícita e apontar para a rota correta.
4. Em caso de erro, o controller deve retornar para a própria tela.
5. Nenhum erro de formulário deve redirecionar diretamente para `/admin/dashboard`.
6. O estado do formulário deve ser preservado com `old_input`.
7. Mensagens de erro e sucesso devem ser exibidas na própria tela.
8. Exclusões devem ser feitas por `POST` e, quando aplicável, exigir justificativa.
9. Controllers devem capturar falhas esperadas e retornar resposta amigável.
10. Exceções inesperadas devem ser logadas com `path`, `method`, `file` e `line`.

## Botões padrão

- Salvar
- Salvar e permanecer
- Salvar e novo
- Salvar como cópia
- Cancelar

## Recomendações de implementação

- Use `form_action` ou `submit_action` para distinguir o comportamento de envio.
- Preserve `old_input` ao redirecionar após falha de validação ou falha de negócio.
- Não dependa de `GET` para ações destrutivas.
- Em edição, o `id` deve vir no `POST`, não apenas na query string.

## Comportamento esperado do Router

- Registrar a exceção.
- Manter o usuário na tela administrativa quando houver `HTTP_REFERER` seguro.
- Redirecionar para o dashboard apenas quando não houver destino seguro.
- Não mascarar falhas de formulário com navegação silenciosa.

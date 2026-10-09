# Spec Delta

## Purpose

Expor ao app do aluno, sob `/api/app/v1`, a autenticação, o perfil e o conteúdo da conta com as mesmas regras do site, por um contrato JSON estável.

## ADDED Requirements

### Requirement: Login pelo Google no app
`POST /auth/google` SHALL receber `id_token`, `device_id`, `device_name` e, opcionalmente, `nonce`, validar o token conforme a capability `autenticacao` com os identificadores de cliente do app, resolver a conta pelas mesmas regras do site e responder `200` no mesmo formato de `POST /auth/login`, acrescido de `usuario_novo` (booleano).

#### Scenario: Conta existente
- **WHEN** o app envia um `id_token` válido de um usuário já vinculado
- **THEN** a resposta é `200` com `access_token`, `refresh_token`, `usuario` e `usuario_novo: false`

#### Scenario: Conta criada
- **WHEN** o app envia um `id_token` válido de pessoa sem cadastro, com e-mail verificado
- **THEN** a conta de aluno é criada e a resposta é `200` com `usuario_novo: true` e `usuario.pendencias` contendo `cpf`

### Requirement: Erros do login pelo Google no app
`POST /auth/google` MUST responder `401 google_token_invalido` para token recusado, `409 email_nao_verificado` para e-mail não verificado sem vínculo, `403 conta_inativa` para conta não ativa, `422 validacao` para campos ausentes e `404 nao_encontrado` quando o recurso estiver desligado, e MUST ter o mesmo limite de taxa por IP do login.

#### Scenario: Token de outro cliente
- **WHEN** o app envia um `id_token` emitido para um cliente não configurado
- **THEN** a resposta é `401 google_token_invalido` e nenhum token do portal é emitido

#### Scenario: Excesso de tentativas
- **WHEN** o mesmo IP excede o limite de tentativas da janela
- **THEN** a resposta é `429 muitas_tentativas` com `Retry-After`

### Requirement: Pendências do usuário
O objeto `usuario` devolvido pelo login, pelo login com Google e por `GET /me` SHALL incluir `pendencias`, uma lista de códigos de dados que o usuário ainda precisa informar; `cpf` MUST constar enquanto a conta não tiver CPF, e a lista MUST ser vazia quando não houver pendência.

#### Scenario: Usuário sem CPF
- **WHEN** um usuário sem CPF consulta `GET /me`
- **THEN** a resposta traz `cpf: null` e `pendencias: ["cpf"]`

#### Scenario: Usuário completo
- **WHEN** um usuário com CPF consulta `GET /me`
- **THEN** a resposta traz `pendencias: []`

### Requirement: Informar o CPF pelo app
`POST /me/cpf` com `{"cpf": "..."}` SHALL gravar o CPF da conta seguindo as regras da capability `conta-aluno` e responder `200` com o `usuario` atualizado; MUST responder `422 validacao` para CPF inválido, `409 cpf_em_uso` para CPF de outra conta e `409 cpf_ja_informado` quando a conta já tiver CPF.

#### Scenario: CPF gravado
- **WHEN** um usuário sem CPF envia um CPF válido e livre
- **THEN** a resposta é `200` com `pendencias: []` e os certificados retidos dele são emitidos

#### Scenario: CPF já informado
- **WHEN** um usuário que já tem CPF envia `POST /me/cpf`
- **THEN** a resposta é `409 cpf_ja_informado` e o CPF não muda

### Requirement: Atualização de perfil sem CPF
`POST /me` MUST atualizar telefone, cidade e estado de usuário sem CPF sem exigir o CPF.

#### Scenario: Aluno vindo do Google atualiza o telefone
- **WHEN** um usuário sem CPF envia `POST /me` com um telefone novo
- **THEN** a resposta é `200` com o telefone atualizado e `pendencias: ["cpf"]`

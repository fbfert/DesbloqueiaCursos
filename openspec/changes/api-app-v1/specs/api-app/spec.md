# Spec Delta

## ADDED Requirements

### Requirement: Contrato da API do app
A API do app SHALL ficar sob `/api/app/v1`, responder JSON UTF-8 e seguir exatamente o formato de
`openspec/changes/api-app-v1/contrato.md`: sucesso em `{"data": ..., "meta": {...}}` e erro em
`{"erro": {"codigo", "mensagem", "campos"}}`, com `campos` só em `422`. Datas MUST sair em ISO-8601
com fuso e dinheiro em centavos inteiros. Mensagens de erro MUST estar em PT-BR com acentuação
correta.

#### Scenario: Caminho inexistente
- **WHEN** o app chama um caminho desconhecido sob `/api/app/`
- **THEN** a resposta é `404` em JSON com `erro.codigo = "nao_encontrado"`, nunca a página HTML

#### Scenario: Falha inesperada
- **WHEN** um erro não tratado acontece numa rota `/api/app/*`
- **THEN** a resposta é `500` com `erro.codigo = "erro_interno"`, sem mensagem, arquivo ou pilha do PHP, e o erro é registrado no log

### Requirement: Autenticação por token sem sessão
As rotas autenticadas do app SHALL aceitar apenas `Authorization: Bearer <access_token>`. Tokens
MUST ser opacos (32 bytes aleatórios) e só o SHA-256 MUST ser gravado. O access token MUST valer 1 h
e o refresh token 60 dias, ambos vinculados ao `device_id`. Requisições a `/api/app/*` MUST NOT
iniciar nem persistir sessão PHP, e os POST do app MUST NOT exigir CSRF.

#### Scenario: Token expirado
- **WHEN** o app envia um access token vencido
- **THEN** a resposta é `401 token_expirado`

#### Scenario: Token ausente ou inválido
- **WHEN** o app chama rota autenticada sem token ou com token desconhecido/revogado
- **THEN** a resposta é `401 nao_autenticado` e o evento é auditado

#### Scenario: Sem cookie de sessão
- **WHEN** qualquer rota `/api/app/*` é chamada
- **THEN** a resposta não contém `Set-Cookie` de sessão

### Requirement: Refresh rotativo com detecção de reuso
`POST /auth/refresh` SHALL invalidar o refresh usado e emitir um novo par. Se um refresh já consumido
for apresentado de novo, todos os tokens daquele `device_id` MUST ser revogados e a resposta MUST
ser `401 sessao_revogada`.

#### Scenario: Rotação normal
- **WHEN** o app troca um refresh válido
- **THEN** recebe novo access e novo refresh, e o refresh antigo deixa de valer

#### Scenario: Reuso
- **WHEN** um refresh já trocado é reapresentado
- **THEN** a resposta é `401 sessao_revogada` e o access token mais recente do dispositivo também para de funcionar

### Requirement: Mesma política de login do site
O login do app SHALL reaproveitar a regra do site: `politica_login`, bloqueio após o número máximo de
tentativas e exigência de `status = ativo`. Login, cadastro e recuperação de senha MUST ter limite de
taxa por IP e por login, respondendo `429 muitas_tentativas` com `Retry-After`.

#### Scenario: Conta bloqueada
- **WHEN** o usuário com bloqueio temporário ativo tenta entrar
- **THEN** a resposta é `423 conta_bloqueada` com os minutos restantes na mensagem

#### Scenario: Excesso de tentativas
- **WHEN** o mesmo IP ou o mesmo login excede o limite da janela
- **THEN** a resposta é `429 muitas_tentativas` com cabeçalho `Retry-After`

#### Scenario: Recuperação não revela conta
- **WHEN** o app pede recuperação para um login inexistente
- **THEN** a resposta é `200 {"data": {"ok": true}}`, igual à de um login existente

### Requirement: Versão mínima do app
Toda rota autenticada SHALL responder `426 atualizacao_obrigatoria` quando o build informado em
`X-App-Version` for menor que a versão mínima configurada.

#### Scenario: App desatualizado
- **WHEN** o app envia `X-App-Version: 0.9.0 (0)` e a mínima é 1
- **THEN** a resposta é `426 atualizacao_obrigatoria`

### Requirement: Acesso a cursos com a regra do site
Inscrições, conteúdo, quiz, avaliação e arquivos SHALL usar as mesmas regras de acesso da área do
aluno (`AreaCursoService::carregarAluno`), e a inscrição pedida MUST ser exatamente a do caminho:
inscrição de outro usuário ou sem acesso responde `403 sem_acesso`. Abrir item MUST aplicar as mesmas
regras do site (registro de acesso, conclusão automática de texto e HTML).

#### Scenario: Inscrição de outro aluno
- **WHEN** o aluno A pede `/inscricoes/{id}` de uma inscrição do aluno B
- **THEN** a resposta é `403 sem_acesso`

#### Scenario: Concluir quiz manualmente
- **WHEN** o aluno chama `/concluir` num item de quiz ou avaliação textual
- **THEN** a resposta é `422 nao_concluivel`

### Requirement: Arquivos privados com dono
Download de arquivo de item, imagem de avaliação e PDF de certificado SHALL exigir Bearer e conferir
que o recurso pertence ao usuário do token. O PDF de certificado na API MUST exigir o dono, sem
alterar a rota pública do site.

#### Scenario: Certificado de outro aluno
- **WHEN** o aluno pede o PDF de um certificado emitido para outra pessoa
- **THEN** a resposta é `403 sem_acesso` ou `404 nao_encontrado`, sem o arquivo

### Requirement: Extrações sem mudança no site
As regras usadas pela API e antes presas em controllers HTML (abertura de item e navegação, regras
de cancelamento do pedido pelo aluno, início do pagamento AbacatePay e núcleo do login) SHALL morar
em Services usados tanto pelo site quanto pela API, e o comportamento do site MUST permanecer o mesmo.

#### Scenario: Site continua igual
- **WHEN** o smoke anônimo e os testes unitários existentes rodam depois da extração
- **THEN** todos passam como antes

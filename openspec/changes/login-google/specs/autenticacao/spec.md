# Spec Delta

## Purpose

Permitir que qualquer usuário do portal entre no site e no app por um provedor de identidade externo (Google), com vínculo seguro a contas existentes e criação de conta quando necessário, sem enfraquecer o login por senha.

## ADDED Requirements

### Requirement: Entrada pelo Google no site
O sistema SHALL oferecer "Entrar com Google" nas telas de login e cadastro dos temas públicos em uso, iniciando um fluxo OpenID Connect por redirecionamento que solicita somente identificação básica (e-mail e nome). Ao concluir com sucesso, o sistema MUST abrir a sessão exatamente como o login por senha e redirecionar para o destino pós-login vigente.

#### Scenario: Aluno já vinculado entra pelo Google
- **WHEN** um usuário cuja conta Google já está vinculada conclui o fluxo do Google
- **THEN** a sessão é aberta para essa conta e ele é levado ao destino pós-login definido pelas suas permissões

#### Scenario: Destino preservado
- **WHEN** um visitante clica em "Entrar com Google" a partir do checkout com destino de retorno válido
- **THEN** após o login ele volta ao checkout

#### Scenario: Destino externo recusado
- **WHEN** o destino de retorno informado aponta para outro domínio
- **THEN** o destino é descartado e o usuário vai para o destino padrão

### Requirement: Recurso desligado sem configuração
Quando as credenciais do Google não estiverem configuradas, o sistema MUST ocultar o botão "Entrar com Google" e responder 404 às rotas do fluxo do Google no site e no app.

#### Scenario: Sem credenciais
- **WHEN** o ambiente não tem as credenciais do Google configuradas
- **THEN** a tela de login não mostra o botão e a rota de início do fluxo responde 404

### Requirement: Proteção do fluxo de redirecionamento
O fluxo do site MUST usar `state` de uso único com validade de 10 minutos, `nonce` e PKCE. Um retorno com `state` ausente, divergente, expirado ou já usado MUST ser recusado sem abrir sessão.

#### Scenario: Retorno repetido
- **WHEN** o mesmo retorno do Google é recebido pela segunda vez
- **THEN** o login é recusado com a mensagem "Sua tentativa de login expirou. Tente novamente."

#### Scenario: Usuário cancela no Google
- **WHEN** o usuário nega o acesso na tela do Google
- **THEN** ele volta ao login com a mensagem "Login com o Google cancelado."

### Requirement: Validação do token de identidade
O sistema MUST aceitar um token de identidade do Google somente se a assinatura for válida pelas chaves públicas vigentes do Google, o emissor for o Google, o público for um dos identificadores de cliente configurados para aquele canal (site ou app), o token não estiver expirado (tolerância de 60 segundos) e, quando houver `nonce` esperado, ele coincidir.

#### Scenario: Token de outro aplicativo
- **WHEN** chega um token válido do Google emitido para um identificador de cliente que não é do portal
- **THEN** o token é recusado e nenhuma sessão ou token do portal é emitido

#### Scenario: Token expirado
- **WHEN** chega um token cuja expiração passou há mais de 60 segundos
- **THEN** o token é recusado

#### Scenario: Rotação de chaves do Google
- **WHEN** chega um token assinado por uma chave que ainda não está no cache local
- **THEN** o sistema busca novamente as chaves públicas uma vez e valida o token com elas

### Requirement: Identificação da conta pelo provedor
O sistema SHALL identificar a conta pelo identificador permanente da pessoa no Google, e não pelo e-mail, uma vez que o vínculo exista. Cada conta Google MUST estar vinculada a no máximo um usuário e cada usuário a no máximo uma conta Google.

#### Scenario: E-mail alterado no Google
- **WHEN** um usuário vinculado troca o e-mail da conta Google e entra novamente
- **THEN** ele entra na mesma conta do portal

### Requirement: Vínculo automático por e-mail verificado
Quando a conta Google ainda não estiver vinculada e o Google declarar o e-mail verificado, o sistema SHALL vincular a conta Google ao usuário existente com o mesmo e-mail, de qualquer perfil, registrar o vínculo na auditoria e enviar à pessoa um e-mail de aviso com data e hora do vínculo. Se o e-mail não for verificado, MUST recusar o login.

#### Scenario: Conta existente com o mesmo e-mail
- **WHEN** um usuário cadastrado com senha entra pela primeira vez pelo Google com o mesmo e-mail verificado
- **THEN** a conta Google é vinculada, a sessão é aberta, a auditoria registra o vínculo e ele recebe o e-mail de aviso

#### Scenario: E-mail não verificado
- **WHEN** o Google informa que o e-mail da conta não está verificado e não há vínculo prévio
- **THEN** o login é recusado com a mensagem "Seu e-mail no Google não está verificado."

### Requirement: Criação de conta pelo Google
Quando não houver vínculo nem usuário com o e-mail verificado, o sistema SHALL criar uma conta de aluno com o nome e o e-mail do Google, sem senha e sem CPF, marcada como cadastro pendente de origem Google, com o perfil aluno, registrar o consentimento de termos e privacidade com origem Google, IP e navegador, e enviar o e-mail de boas-vindas. Contas de outros perfis MUST NOT ser criadas pelo Google.

#### Scenario: Primeiro acesso de pessoa nova
- **WHEN** uma pessoa sem cadastro entra pelo Google com e-mail verificado
- **THEN** a conta de aluno é criada, a sessão é aberta e ela é levada à tela "Complete seu cadastro"

#### Scenario: Aviso de consentimento
- **WHEN** a tela de login ou cadastro mostra o botão "Entrar com Google"
- **THEN** abaixo dele aparece o texto "Ao continuar com o Google, você concorda com os Termos de Uso e a Política de Privacidade", com links para ambos

### Requirement: Conta inativa não entra pelo Google
O sistema MUST recusar a entrada pelo Google de usuário com status diferente de ativo. O bloqueio temporário por tentativas de senha MUST NOT impedir a entrada pelo Google.

#### Scenario: Conta inativa
- **WHEN** um usuário inativo conclui o fluxo do Google
- **THEN** nenhuma sessão é aberta e ele vê a mesma mensagem do login por senha para conta inativa

#### Scenario: Bloqueio por senha errada
- **WHEN** um usuário ativo está temporariamente bloqueado por excesso de senhas erradas e entra pelo Google
- **THEN** a sessão é aberta

### Requirement: Falha do Google
Se o Google estiver indisponível ou devolver resposta inválida, o sistema MUST recusar o login com a mensagem "Não foi possível entrar com o Google agora. Tente novamente ou use e-mail e senha." e registrar o motivo no log, sem registrar tokens, códigos ou segredos.

#### Scenario: Google fora do ar
- **WHEN** a troca do código pelo token falha por erro de rede
- **THEN** o usuário volta ao login com a mensagem de indisponibilidade e o log registra a falha sem o código recebido

### Requirement: Registro de acesso
Toda entrada pelo Google, no site ou no app, SHALL ser registrada no histórico de acessos com um evento distinto do login por senha.

#### Scenario: Acesso registrado
- **WHEN** um usuário entra pelo Google no site
- **THEN** o histórico de acessos registra o evento de login pelo Google com IP e navegador

### Requirement: Gestão do vínculo na conta
Em "Minha conta", o sistema SHALL mostrar se há conta Google vinculada e o e-mail informado por ela. O usuário com senha definida SHALL poder desvincular a conta Google; sem senha definida, a opção MUST NOT ser oferecida.

#### Scenario: Desvincular com senha
- **WHEN** um usuário com senha desvincula a conta Google
- **THEN** o vínculo é removido, a ação é auditada e a próxima entrada pelo Google segue a regra de vínculo por e-mail

#### Scenario: Conta sem senha
- **WHEN** um usuário criado pelo Google, sem senha, abre "Minha conta"
- **THEN** a conta Google aparece vinculada sem a opção de desvincular

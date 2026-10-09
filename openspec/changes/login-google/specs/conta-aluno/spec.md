# Spec Delta

## Purpose

Manter os dados cadastrais que o próprio usuário informa, em especial o CPF, que pode faltar em contas criadas por provedor externo e é necessário para emitir certificados.

## ADDED Requirements

### Requirement: Conta sem CPF
O sistema SHALL permitir contas sem CPF somente quando criadas por provedor de identidade externo. O cadastro por formulário MUST continuar exigindo CPF válido, e o CPF, quando presente, MUST continuar único entre os usuários.

#### Scenario: Cadastro por formulário
- **WHEN** alguém envia o formulário de cadastro sem CPF
- **THEN** o cadastro é recusado como hoje

#### Scenario: Várias contas sem CPF
- **WHEN** duas pessoas diferentes criam conta pelo Google
- **THEN** as duas contas existem sem CPF, sem conflito entre si

### Requirement: Lembrete de CPF pendente
Enquanto o usuário não tiver CPF, a área do aluno SHALL exibir em todas as suas páginas um aviso fixo "Informe seu CPF para podermos emitir seus certificados." com um botão que leva ao campo de CPF em "Minha conta". O aviso MUST desaparecer assim que o CPF for salvo.

#### Scenario: Aluno sem CPF
- **WHEN** um aluno sem CPF abre "Meus cursos"
- **THEN** o aviso de CPF pendente aparece com o botão para informar o CPF

#### Scenario: CPF informado
- **WHEN** o aluno salva um CPF válido
- **THEN** o aviso deixa de aparecer em todas as páginas

### Requirement: Completar cadastro após o primeiro acesso
Logo após a criação da conta pelo Google, o sistema SHALL apresentar a tela "Complete seu cadastro" pedindo o CPF, com a opção "Fazer isso depois", que leva ao destino pós-login sem bloquear o acesso.

#### Scenario: Fazer depois
- **WHEN** o aluno recém-criado escolhe "Fazer isso depois"
- **THEN** ele segue ao destino pós-login e o lembrete de CPF pendente continua ativo

#### Scenario: Preenche na hora
- **WHEN** o aluno informa um CPF válido e não usado na tela "Complete seu cadastro"
- **THEN** o CPF é salvo e ele segue ao destino pós-login

### Requirement: Aluno informa o próprio CPF uma única vez
O usuário SHALL poder informar o próprio CPF, no site ou no app, somente enquanto ele estiver vazio. O CPF MUST ter dígitos verificadores válidos e não pertencer a outra conta. Depois de gravado, só a administração pode alterá-lo.

#### Scenario: CPF de outra conta
- **WHEN** o aluno informa um CPF que já pertence a outro usuário
- **THEN** a gravação é recusada com a mensagem "Este CPF já está cadastrado em outra conta. Fale com o atendimento."

#### Scenario: CPF inválido
- **WHEN** o aluno informa um CPF com dígitos verificadores errados
- **THEN** a gravação é recusada com a mensagem "Informe um CPF válido."

#### Scenario: CPF já preenchido
- **WHEN** um usuário que já tem CPF abre "Minha conta"
- **THEN** o CPF aparece somente para leitura

### Requirement: Checkout com conta sem CPF
No checkout, o sistema SHALL pedir o CPF do pagador quando a conta logada não tiver CPF e, ao concluir o pedido, gravar esse CPF na conta se ela ainda estiver sem CPF e o CPF não pertencer a outra conta.

#### Scenario: Compra por aluno vindo do Google
- **WHEN** um aluno sem CPF finaliza uma compra informando um CPF válido e livre
- **THEN** o pedido é criado e o CPF passa a constar na conta, encerrando o lembrete

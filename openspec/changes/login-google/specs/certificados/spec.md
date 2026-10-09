# Spec Delta

## Purpose

Garantir que certificados de participantes com conta no portal só sejam emitidos com CPF, retendo a emissão até o próprio aluno informar o CPF, sem travar a operação da secretaria.

## ADDED Requirements

### Requirement: Retenção de certificado sem CPF
Quando a administração solicitar a emissão de certificado (individual, rápida, em lote ou com exceção administrativa) para um participante sem CPF que esteja ligado a uma conta de usuário também sem CPF, o sistema MUST NOT emitir o certificado e SHALL registrá-lo como retido "aguardando CPF", guardando os parâmetros escolhidos na solicitação e quem a fez.

#### Scenario: Emissão individual retida
- **WHEN** o admin emite o certificado de um aluno que entrou pelo Google e não informou CPF
- **THEN** nenhum certificado é emitido, a solicitação fica retida "aguardando CPF" e o admin vê a mensagem "Certificado retido: o aluno ainda não informou o CPF. Ele será emitido automaticamente quando o CPF for informado."

#### Scenario: Lote com participantes sem CPF
- **WHEN** o admin emite um lote em que parte dos participantes com conta não tem CPF
- **THEN** os demais certificados são emitidos e o resumo do lote informa quantos ficaram retidos aguardando CPF

#### Scenario: Conta com CPF e participante sem CPF
- **WHEN** o participante não tem CPF, mas a conta ligada a ele tem
- **THEN** o certificado é emitido usando o CPF da conta e o CPF é gravado no participante

### Requirement: Aviso ao aluno com certificado retido
Ao reter um certificado, o sistema SHALL enviar ao aluno um e-mail informando que o certificado está pronto para emissão e que ele precisa informar o CPF, com link para "Minha conta". Uma nova retenção para o mesmo aluno em menos de 24 horas MUST NOT gerar outro e-mail.

#### Scenario: Lote retém vários certificados do mesmo aluno
- **WHEN** um lote retém dois certificados do mesmo aluno
- **THEN** o aluno recebe um único e-mail de aviso

### Requirement: Emissão automática ao informar o CPF
Quando o aluno informar o CPF (no site, no app ou no checkout), o sistema SHALL gravar esse CPF nos registros de participante ligados à conta que estejam sem CPF e emitir automaticamente, com os parâmetros guardados, os certificados retidos dele, enviando os e-mails de emissão de certificado de costume. Uma falha na emissão MUST NOT impedir a gravação do CPF.

#### Scenario: Aluno informa o CPF
- **WHEN** um aluno com um certificado retido salva o CPF
- **THEN** o certificado é emitido com esse CPF, deixa de constar como retido e aparece em "Meus certificados"

#### Scenario: Falha na emissão automática
- **WHEN** a emissão automática de um certificado retido falha
- **THEN** o CPF continua salvo, o certificado continua retido, o erro é registrado no log e a retenção aparece com a falha na lista do admin

### Requirement: Acompanhamento das retenções pelo admin
A administração SHALL ver a lista de certificados retidos aguardando CPF (aluno, curso, turma, data e autor da solicitação, última falha) e SHALL poder cancelar uma retenção mediante justificativa, registrada na lixeira/auditoria.

#### Scenario: Cancelar retenção
- **WHEN** o admin cancela uma retenção informando a justificativa
- **THEN** a retenção deixa de existir, o certificado não será emitido automaticamente e a justificativa fica registrada

### Requirement: Participante sem conta mantém o comportamento atual
Para participante sem CPF e sem conta de usuário ligada, a emissão SHALL continuar como hoje, e o sistema SHALL avisar o admin, no resultado da emissão, que o certificado foi emitido sem CPF.

#### Scenario: Participante de compra para terceiro
- **WHEN** o admin emite o certificado de um participante sem conta e sem CPF
- **THEN** o certificado é emitido e o admin vê o aviso "Certificado emitido sem CPF do participante."

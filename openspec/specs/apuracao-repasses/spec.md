# apuracao-repasses Specification

## Purpose

Apurar mensalmente o que cada professor tem a receber pelas vendas dos seus cursos e turmas,
controlar documentos fiscais e registrar o pagamento dos repasses com segurança e rastreabilidade.

## Requirements

### Requirement: Geração de repasses idempotente
O sistema MUST gerar no máximo um repasse por professor em cada apuração. Uma nova tentativa de
gerar repasses para uma apuração que já os possui MUST ser recusada sem criar repasses nem
espelhos de RPA, inclusive quando duas solicitações chegam ao mesmo tempo.

#### Scenario: Segunda geração recusada
- **GIVEN** uma apuração com repasses já gerados
- **WHEN** o admin solicita "Gerar repasses" de novo
- **THEN** o sistema informa que os repasses dessa apuração já foram gerados
- **AND** nenhum repasse ou espelho de RPA é criado

#### Scenario: Cliques simultâneos
- **WHEN** duas solicitações de geração da mesma apuração chegam ao mesmo tempo
- **THEN** apenas uma cria os repasses e a outra é recusada

### Requirement: Exigência de documento fiscal
O sistema SHALL marcar o repasse como dependente de documento quando o professor é PJ ou quando
o perfil fiscal dele tem "exige nota fiscal" marcado. Esse repasse MUST começar como "aguardando
documento". Os demais começam como "pendente".

#### Scenario: Pessoa física que exige nota
- **GIVEN** um professor PF com "exige nota fiscal" marcado
- **WHEN** os repasses da apuração são gerados
- **THEN** o repasse dele começa como "aguardando documento"

#### Scenario: Pessoa física sem exigência
- **GIVEN** um professor PF sem "exige nota fiscal"
- **WHEN** os repasses são gerados
- **THEN** o repasse começa como "pendente"

### Requirement: Validação de documento
O admin com `financeiro.gerenciar` SHALL poder abrir, validar ou rejeitar o documento enviado.
Validar MUST levar o repasse a "aprovado" e registrar quem validou e quando. Rejeitar MUST exigir
motivo e devolver o repasse a "aguardando documento". As duas ações MUST gerar auditoria.

#### Scenario: Documento validado
- **GIVEN** um repasse em "documento recebido"
- **WHEN** o admin valida o documento
- **THEN** o documento fica "validado", com usuário e data
- **AND** o repasse fica "aprovado"

#### Scenario: Rejeição sem motivo
- **WHEN** o admin tenta rejeitar um documento sem informar o motivo
- **THEN** o sistema recusa e pede o motivo

#### Scenario: Documento rejeitado
- **WHEN** o admin rejeita um documento informando o motivo
- **THEN** o documento fica "rejeitado" com o motivo
- **AND** o repasse volta para "aguardando documento"

### Requirement: Envio de documento não aprova sozinho
Anexar um documento MUST levar o repasse a "documento recebido" quando ele exige documento. O
envio sozinho MUST NOT aprovar um repasse que exige documento.

#### Scenario: Nota anexada por PJ
- **GIVEN** um repasse PJ em "aguardando documento"
- **WHEN** o admin anexa a nota fiscal
- **THEN** o repasse fica "documento recebido", não "aprovado"

### Requirement: Pagamento só de repasse liberado
O sistema MUST registrar pagamento apenas de repasse "aprovado", ou "pendente" quando não exige
documento. O valor pago MUST ser igual ao valor líquido. A data do pagamento MUST ficar em campo
próprio, sem alterar a data de validação do documento.

#### Scenario: PJ sem documento validado
- **GIVEN** um repasse PJ em "aguardando documento"
- **WHEN** o admin tenta registrar o pagamento
- **THEN** o sistema recusa, informando que falta validar o documento

#### Scenario: Valor diferente do líquido
- **GIVEN** um repasse aprovado com valor líquido de R$ 750,00
- **WHEN** o admin registra pagamento de R$ 700,00
- **THEN** o sistema recusa e informa o valor esperado

#### Scenario: Pagamento válido
- **WHEN** o admin paga um repasse aprovado pelo valor líquido
- **THEN** o repasse fica "pago" com a data de pagamento registrada
- **AND** a data de validação do documento continua a mesma

#### Scenario: Repasse já pago
- **GIVEN** um repasse "pago"
- **WHEN** o admin tenta registrar outro pagamento
- **THEN** o sistema recusa

### Requirement: Acesso aos arquivos do repasse
Documentos, comprovantes de pagamento e espelhos de RPA MUST ficar fora da área pública e só ser
entregues por rota protegida. O admin com `financeiro.ver` SHALL abrir qualquer um. O professor
SHALL baixar apenas os espelhos de RPA dos próprios repasses.

#### Scenario: Professor baixa o próprio RPA
- **WHEN** o professor abre o espelho de RPA de um repasse seu em `/professor/financeiro`
- **THEN** o arquivo é entregue

#### Scenario: Professor tenta RPA de outro
- **WHEN** o professor pede o espelho de RPA de um repasse de outro professor
- **THEN** o sistema responde com acesso negado e registra a tentativa

### Requirement: Rateio editável só antes dos repasses
O rateio de uma apuração MUST poder ser criado, editado ou excluído apenas enquanto a apuração
não tem repasses gerados. Após cada alteração, os totais da apuração MUST ser recalculados. A
soma dos percentuais continua limitada ao teto configurado, nunca acima de 75%.

#### Scenario: Edição bloqueada
- **GIVEN** uma apuração com repasses gerados
- **WHEN** o admin tenta salvar ou excluir um rateio dela
- **THEN** o sistema recusa, informando que os repasses já foram gerados

#### Scenario: Totais atualizados
- **GIVEN** uma apuração sem repasses
- **WHEN** o admin altera o percentual de um rateio
- **THEN** o valor total de rateio e o total retido da apuração refletem a alteração

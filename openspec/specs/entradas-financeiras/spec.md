# entradas-financeiras Specification

## Purpose

Reconhecer como entrada financeira apenas as vendas efetivamente recebidas, refletir estornos e
contestações dos meios de pagamento e oferecer painel e exportação confiáveis para o admin.

## Requirements

### Requirement: Entradas confirmadas
O sistema SHALL contar como entrada confirmada apenas o pedido "aprovado" ou "pago", que não é
presente e tem total maior que zero. Pedido "reembolsado", "cancelado" ou "expirado" MUST NOT
contar como entrada.

#### Scenario: Pedido reembolsado sai das entradas
- **GIVEN** um pedido pago de R$ 300,00 em outubro
- **WHEN** o pedido passa a "reembolsado"
- **THEN** o total de entradas de outubro diminui R$ 300,00

### Requirement: Estorno pelo gateway
Ao receber um reembolso válido e autenticado do gateway, o sistema MUST mudar o pedido para
"reembolsado" e registrar o histórico. Um reembolso repetido MUST ser ignorado sem efeito. O
acesso do aluno MUST NOT ser revogado automaticamente.

#### Scenario: Reembolso recebido
- **GIVEN** um pedido "pago" via AbacatePay
- **WHEN** chega o evento de reembolso desse pagamento
- **THEN** o pedido fica "reembolsado", com registro no histórico de status
- **AND** as inscrições do pedido continuam como estavam

#### Scenario: Evento repetido
- **WHEN** o mesmo evento de reembolso chega duas vezes
- **THEN** a segunda chegada não altera nada

### Requirement: Contestação pelo gateway
Ao receber uma contestação válida do gateway, o sistema MUST manter o pedido no status atual e
registrar a contestação no histórico.

#### Scenario: Contestação recebida
- **WHEN** chega o evento de contestação de um pedido pago
- **THEN** o pedido continua "pago"
- **AND** o histórico registra a contestação

### Requirement: Pendências de estorno e contestação
O painel financeiro SHALL listar pedidos reembolsados ou contestados que ainda pedem ação: pedido
reembolsado com inscrição ativa, pedido contestado, ou pedido incluído em apuração já fechada,
sinalizado como "ajuste de repasse pendente".

#### Scenario: Reembolso após apuração
- **GIVEN** um pedido de setembro incluído na apuração de setembro, já fechada
- **WHEN** o pedido é reembolsado em outubro
- **THEN** o painel mostra o pedido com o aviso "ajuste de repasse pendente"

### Requirement: Exportação completa de entradas
A exportação CSV de entradas MUST conter todos os pedidos confirmados que atendem aos filtros
aplicados, sem limite fixo de linhas, além dos resumos mensais e por curso/turma.

#### Scenario: Mais de 20 pedidos no período
- **GIVEN** 150 pedidos confirmados no mês filtrado
- **WHEN** o admin exporta as entradas
- **THEN** o arquivo traz as 150 linhas de pedidos

### Requirement: Permissões de gestão financeira
Ações que alteram dados financeiros MUST exigir `financeiro.gerenciar`: apurar, gerar repasses,
anexar e validar documento, registrar pagamento e editar perfil fiscal. Aprovar ou reprovar
comprovante PIX MUST exigir `pedidos.gerenciar` ou `financeiro.gerenciar`. Permissões só de
leitura MUST NOT autorizar essas ações.

#### Scenario: Usuário só com leitura
- **GIVEN** um usuário com `financeiro.ver` e `pedidos.ver`, sem permissão de gestão
- **WHEN** ele tenta aprovar um comprovante PIX ou registrar um pagamento de repasse
- **THEN** o sistema nega a ação e registra a tentativa na auditoria

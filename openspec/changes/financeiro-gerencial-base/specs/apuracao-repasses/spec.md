# Spec Delta

## ADDED Requirements

### Requirement: Conta de saída do pagamento de repasse
O registro de pagamento de um repasse MUST indicar a conta financeira ativa de onde o dinheiro
saiu. A conta configurada como padrão para repasses SHALL vir pré-selecionada.

#### Scenario: Pagamento sem conta
- **WHEN** o admin registra o pagamento de um repasse sem escolher a conta
- **THEN** o sistema recusa e pede a conta de saída

### Requirement: Base do rateio líquida de taxas, por configuração
Quando a configuração "descontar taxas dos meios de pagamento no rateio" está ligada, a apuração
de uma competência igual ou posterior à competência inicial configurada MUST usar como base de
cada item a receita confirmada menos a taxa do meio de pagamento lançada para aquele item. Com a
configuração desligada, ou em competências anteriores, a base MUST continuar sendo o valor
confirmado do pedido. O teto do rateio continua limitado a 75%.

#### Scenario: Configuração desligada
- **GIVEN** a opção desligada
- **WHEN** a competência 2026-12 é apurada
- **THEN** a base de cada item é o valor confirmado, sem descontar taxas

#### Scenario: Configuração ligada
- **GIVEN** a opção ligada a partir de 2026-12
- **AND** um item de R$ 300,00 com taxa lançada de R$ 2,97
- **WHEN** a competência 2026-12 é apurada
- **THEN** a base desse item é R$ 297,03

#### Scenario: Competência anterior
- **GIVEN** a opção ligada a partir de 2026-12
- **WHEN** a competência 2026-11 é apurada
- **THEN** a base continua sendo o valor confirmado

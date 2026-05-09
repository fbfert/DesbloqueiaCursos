# Fase 6A.2 — Painel de aptos para certificado

## Escopo

- Painel consultivo de conferência pedagógica na Área interna do curso.
- Sem emissão automática.
- Sem alteração da regra atual de emissão manual.

## Integração

- Reaproveita `LmsElegibilidadeService` para situação e motivos.
- Reaproveita `RelatorioLmsService` para filtros, tabela e exportação.
- Não cria migration.

## Decisão de produto (CSV)

- O CSV de **progresso** continua com elegibilidade e motivos.
- O CSV de **atividades** permanece focado em atividade (não recebe elegibilidade nesta versão).
- Novo CSV do painel: `relatorio=aptos_certificado`, com visão por aluno/inscrição.

## Aviso funcional obrigatório

- O painel exibe explicitamente:
  - “Este painel apenas informa elegibilidade. A emissão de certificado continua pelo fluxo atual.”

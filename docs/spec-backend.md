# Spec Driven Backend — Portal de Cursos e Eventos

## Propósito

Este documento consolida o spec do backend do Portal de Cursos e Eventos como fonte de verdade para evolução do sistema.
Ele orienta implementação, revisão e teste de mudanças sem depender de decisões pontuais em controllers ou views.

## Objetivos do backend

- Manter o domínio de cursos, eventos, turmas, pedidos, cupons, PIX, inscrições, certificados, financeiro e backoffice sob regras explícitas.
- Garantir compatibilidade com MySQL 5.7 e deploy em Linux/cPanel.
- Concentrar regra de negócio em `Services`, mantendo controllers finos.
- Preservar logs, auditoria, lixeira e histórico em alterações relevantes.
- Impedir vazamento de dados financeiros ou sensíveis no frontend público.

## Premissas arquiteturais

- PHP MVC com acesso a banco via PDO.
- Rotas web organizadas por módulo, com possibilidade de evolução para API.
- Migrations em SQL simples dentro de `sql/`.
- Frontend mobile-first, com classes reutilizáveis no admin e no portal.
- Arquivos privados fora de `public_html`.

## Regras globais obrigatórias

- CPF e e-mail devem permanecer únicos por usuário.
- Toda exclusão relevante deve ir para lixeira, com justificativa quando aplicável.
- Toda alteração relevante deve gerar log e histórico.
- O backend deve bloquear ações críticas por permissão.
- Mensagens, títulos e rótulos para usuário devem usar português brasileiro com acentuação correta.

## Domínios funcionais

### 1. Autenticação e perfis

- Login por e-mail ou CPF.
- Recuperação de senha com token de 60 minutos.
- Perfis controlados por RBAC.
- Admin, professor e aluno com permissões distintas.

### 2. Catálogo

- Cursos e eventos são entidades principais.
- Turmas/edições pertencem ao curso ou evento.
- Professores visualizam apenas itens atribuídos.
- Cursos em promoção podem alterar elegibilidade de cupom conforme regra de negócio.

### 3. Pedidos e checkout

- Checkout deve suportar cupom, PIX manual e compra para terceiros.
- Pedido deve guardar origem/tipo quando for presente, cortesia, administrativo ou equivalente.
- Pedido aprovado/pago não pode ser reclassificado sem regra explícita.
- PIX e comprovantes devem respeitar estados de envio, análise, aprovação e recusa.

### 4. Inscrições e área do aluno

- Pedido aprovado deve gerar inscrição ativa quando aplicável.
- O aluno deve enxergar o curso em “Meus Cursos”.
- Regras de expiração de acesso devem ser respeitadas quando configuradas.

### 5. Financeiro e rateio

- Rateio deve usar valor líquido recebido.
- Teto máximo de rateio: 75%.
- Pedidos especiais sem receita não entram em faturamento, comissão, repasse ou fechamento.
- Alterações financeiras devem ser rastreáveis por log e histórico.

### 6. Certificados

- Certificado precisa de código alfanumérico, QR Code e validação pública.
- A emissão deve depender das regras do curso e da inscrição.

### 7. Backoffice

- Operações administrativas devem ser auditadas.
- Filtros, ações em lote e exclusões devem ter confirmação explícita.
- Dados críticos devem ser exibidos com badges e estados claros.

## Contratos de implementação

### Controllers

- Recebem request, validam permissão e delegam regra para services.
- Não devem conter regra de negócio extensa.
- Devem responder com redirect, flash message ou view, conforme o fluxo.

### Services

- Concentrar validação de domínio, cálculo, transição de status, auditoria e efeitos colaterais.
- Ser reutilizáveis em admin, aluno, professor e operações internas.
- Proteger integridade de pedido, inscrição, cupom, comprovante e financeiro.

### Models

- Executar consultas e persistência.
- Não conter regra complexa de negócio.
- Expor métodos claros para leitura, contagem, atualização e histórico.

### Views

- Exibir apenas o que já foi validado pelo backend.
- Não confiar em valores do frontend para cálculo ou autorização.
- Usar componentes visuais consistentes com o admin e o portal.

## Regras de integridade de dados

- Não permitir duplicidade de inscrição em curso/turma quando houver vínculo válido.
- Não permitir comprovante aprovado para pedido já finalizado sem regra explícita.
- Não gerar financeiro para pedidos marcados como cortesia, presente ou equivalente.
- Não expor números comerciais sensíveis no frontend público.
- Não destruir histórico quando a intenção for cancelamento lógico.

## Logs, auditoria e lixeira

- Registrar identidade do ator, contexto, entidade afetada e justificativa.
- Manter histórico de transições de status quando o domínio exigir.
- Lixeira deve preservar rastreabilidade e permitir eventual recuperação controlada.

## Contratos esperados por módulo

### Pedidos

- Criar, visualizar, cancelar, excluir logicamente e auditar.
- Suportar cupom aplicado manualmente quando houver permissão.
- Exibir origem do pedido quando for diferente do fluxo padrão.

### Cupons

- Validar escopo, validade, elegibilidade e limites.
- Não permitir aplicação onde a regra de negócio bloqueia.

### PIX / comprovantes

- Separar estados de envio, análise, aprovação e recusa.
- Não aprovar automaticamente por simples existência do arquivo.

### Presentes e cortesia

- Criar pedido aprovado de valor zero com marca explícita de origem.
- Criar inscrição ativa sem impacto em financeiro, rateio ou comissão.
- Manter histórico e permitir cancelamento controlado.

## Critérios de aceite para novas mudanças

Uma alteração de backend só é considerada concluída quando:

- a regra de negócio estiver em `Service` ou `Model` apropriado;
- permissões tiverem sido verificadas;
- logs/histórico tiverem sido preservados;
- o frontend não expuser dados sensíveis;
- a compatibilidade com MySQL 5.7 for mantida;
- a alteração tiver rota, view ou migration apenas quando necessário;
- houver validação manual ou teste de fluxo coberto.

## Checklist de revisão

- O controller está fino?
- A regra está no service correto?
- O banco continua compatível com MySQL 5.7?
- Há log e histórico?
- Há bloqueio por permissão?
- Há risco de exposição pública de dado sensível?
- A mudança afeta financeiro, rateio ou certificados?
- A mudança mantém o comportamento anterior fora do escopo?

## Observação operacional

Este spec deve ser atualizado sempre que o backend ganhar novos fluxos relevantes, novas regras financeiras, novos estados de pedido, mudanças de expiração de acesso ou integrações críticas.

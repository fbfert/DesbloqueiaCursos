# AGENTS.md

## Projeto
Portal de cursos e eventos Polo Rainbow.

## Stack obrigatória
- PHP MVC
- MySQL 5.7
- Frontend mobile-first
- Arquitetura preparada para API
- Armazenamento de arquivos fora da public_html
- Logs e auditoria em tudo
- Lixeira com justificativa obrigatória

## Regras de negócio principais
- Cursos e eventos separados de turmas/edições
- Compra para terceiros e compra em lote
- PIX manual no MVP
- Campo de cupom no pedido
- Cupom não funciona em curso em promoção
- Rateio sobre valor líquido recebido
- Teto máximo de rateio: 75%
- Certificado com código alfanumérico, QR Code e validação pública
- Login por e-mail ou CPF
- CPF e e-mail únicos
- Recuperação de senha com token de 60 minutos
- Professor só vê cursos atribuídos a ele
- Professores não veem comprovantes PIX
- Todo delete vai para lixeira
- Toda mudança relevante gera log e histórico

## Padrões de implementação
- Não quebrar compatibilidade com MySQL 5.7
- Usar migrations SQL simples na pasta /sql
- Separar regras de negócio em Services
- Controllers finos
- Validar inputs no backend
- Preparar tudo para deploy em Linux/cPanel

## Regra editorial obrigatória
- Todos os textos exibidos ao usuário devem usar português brasileiro com acentuação correta.
- Não publicar telas, mensagens de erro/sucesso, rótulos, botões, títulos ou descrições sem acentuação.
- Essa regra vale para frontend público, área do aluno, área do professor e backoffice/admin.
- Consulte também `docs/regras-portugues-interface.md` antes de concluir qualquer ajuste de interface.

## Ordem de construção
1. Estrutura base do projeto
2. Banco de dados inicial
3. Autenticação e perfis
4. Catálogo de cursos/eventos/turmas
5. Pedidos, cupons e PIX
6. Área do aluno
7. Backoffice
8. Certificados
9. Financeiro e repasses
10. Logs, auditoria e lixeira

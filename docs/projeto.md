# Projeto Portal de Cursos e Eventos — Polo Rainbow

## Visão geral

O Portal de Cursos e Eventos do Polo Rainbow é uma aplicação web em PHP MVC para gestão completa de cursos, eventos, turmas, vendas, área do aluno, backoffice e operação financeira.

O projeto foi desenhado para:

- operar com **MySQL 5.7**;
- rodar em **Linux/cPanel**;
- manter frontend **mobile-first**;
- separar arquivos privados fora da `public_html`;
- registrar logs, auditoria e lixeira em operações relevantes.

## Objetivos de negócio

- Separar cursos/eventos de suas turmas/edições.
- Permitir compra para terceiros e compra em lote.
- Suportar pagamento por PIX manual no MVP.
- Aplicar cupons com regras de elegibilidade.
- Calcular repasses com base no valor líquido recebido.
- Emitir certificados com código alfanumérico, QR Code e validação pública.
- Garantir rastreabilidade com logs, histórico e lixeira com justificativa.

## Stack e diretrizes técnicas

- **Backend:** PHP MVC (controllers finos e regras em services).
- **Banco:** MySQL 5.7 (compatibilidade obrigatória).
- **Persistência e acesso:** PDO.
- **Frontend:** arquitetura mobile-first.
- **Estrutura pronta para API:** rotas e organização já preparadas para evolução.
- **Migrations:** SQL simples em `sql/`.
- **Deploy:** compatível com Linux/cPanel.

## Regras de negócio principais

- Login por e-mail ou CPF.
- CPF e e-mail únicos por usuário.
- Recuperação de senha com token de 60 minutos.
- Cupom não pode ser aplicado em curso em promoção.
- Rateio deve usar valor líquido recebido.
- Teto máximo de rateio limitado a 75%.
- Professor visualiza apenas cursos atribuídos.
- Professor não visualiza comprovantes PIX.
- Toda exclusão deve ir para lixeira com justificativa.
- Toda mudança relevante deve gerar log e histórico.

## Estrutura de diretórios (resumo)

- `app/`: núcleo da aplicação (controllers, services, models, core).
- `config/`: configurações de ambiente e serviços.
- `routes/`: definição de rotas web e base para API.
- `public_html/`: raiz pública do servidor web.
- `resources/`: arquivos de interface e apoio.
- `storage/`: logs, cache, temporários, uploads e lixeira.
- `sql/`: migrations e scripts SQL compatíveis com MySQL 5.7.
- `docs/`: documentação técnica e operacional.
- `tests/`: smoke tests e artefatos de homologação.

## Módulos funcionais

- **Autenticação e perfis:** aluno, professor e admin.
- **Catálogo:** cursos, eventos, turmas e edições.
- **Pedidos e checkout:** cupom, PIX manual e regras de compra.
- **Área do aluno:** progresso, acompanhamento e certificados.
- **Área do professor:** visão acadêmica e financeira restrita ao seu escopo.
- **Backoffice:** operação administrativa, catálogo, financeiro e configurações globais.
- **Certificados:** emissão, código de validação, QR Code e consulta pública.
- **Financeiro e repasses:** fechamento por competência e limite de rateio.
- **Logs, auditoria e lixeira:** rastreabilidade ponta a ponta.

## Segurança e conformidade operacional

- Arquivos sensíveis fora de `public_html`.
- Diretórios com escrita controlada em `storage/`.
- Registro de eventos críticos para auditoria.
- Política editorial obrigatória em português brasileiro com acentuação correta.

## Ambientes e execução local

1. Copiar ambiente:
   - `cp .env.example .env`
2. Ajustar variáveis de banco, URL e e-mail no `.env`.
3. Subir servidor local:
   - `php -S 127.0.0.1:8000 -t public_html`
4. Validar rotas iniciais:
   - `GET /`
   - `GET /api/health`

## Ordem de construção oficial

1. Estrutura base do projeto.
2. Banco de dados inicial.
3. Autenticação e perfis.
4. Catálogo de cursos/eventos/turmas.
5. Pedidos, cupons e PIX.
6. Área do aluno.
7. Backoffice.
8. Certificados.
9. Financeiro e repasses.
10. Logs, auditoria e lixeira.

## Documentos relacionados

- `README.md`
- `docs/deploy.md`
- `docs/go-live-checklist.md`
- `docs/rollback.md`
- `docs/qa-checklist.md`
- `docs/homologacao.md`
- `docs/padrao-editorial-ptbr.md`

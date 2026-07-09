# Avaliação do projeto

**Data:** 2026-07-09

## Escopo

Avaliação estrutural da base em `public_html/`, com leitura de bootstrap, rotas,
camadas de aplicação, documentação operacional, alterações recentes e validação
pontual dos arquivos alterados.

## Resumo executivo

O projeto está em um estágio funcional e relativamente bem organizado para uma
base PHP sem framework pesado. Há separação clara entre controllers, services e
models, uso de PDO, RBAC, auditoria, lixeira com justificativa e uma disciplina
razoável de documentação.

O principal problema não é a arquitetura central, e sim a higiene operacional:
há tree misto, arquivos temporários/backup soltos, documentação com trechos que
não refletem o estado atual do repositório e um teste unitário que não roda no
ambiente presente por causa de `.env` inválido e ausência de banco acessível.

## O que existe hoje

- Bootstrap único em `index.php`, com carregamento de `.env`, sessão, error
  handler e roteamento.
- Rotas web e API separadas em `routes/web.php` e `routes/api.php`.
- Controllers relativamente finos, com regra de negócio concentrada em
  services.
- Models com PDO puro e foco em compatibilidade com MySQL 5.7.
- Views PHP puras em `resources/views/`, com layout compartilhado.
- Camada V2 separada para a nova experiência pública e acadêmica.
- Mecanismos explícitos de segurança e controle:
  - CSRF automático em POST.
  - Middleware de autenticação e permissão.
  - Lixeira com justificativa.
  - Auditoria e logging.

## Pontos fortes

- A organização segue uma linha consistente de MVC simples, que é adequada para
  hospedagem em cPanel e manutenção manual.
- A documentação de intenção é boa: `README.md`, `CLAUDE.md` e os relatórios em
  `docs/` explicam bastante do domínio e das regras de negócio.
- Há preocupação real com segurança de conteúdo:
  - HTML sanitizado antes de renderização em várias áreas.
  - Rotas protegidas por autenticação/permissão.
  - Uploads e arquivos privados fora da área pública.
- O sistema já cobre domínios importantes do produto:
  - checkout,
  - área do aluno,
  - área do professor,
  - certificados,
  - financeiro,
  - recuperação de pedidos,
  - páginas institucionais editáveis.

## Riscos e inconsistências

### 1. Ambiente de teste quebrado

O teste em `tests/Unit/quiz_system.php` falha porque o `.env` atual não é
parseável como INI e o script tenta conectar ao MySQL sem um socket acessível no
ambiente disponível.

Efeito prático:

- validação local fica pouco confiável;
- a base perde um ponto importante de regressão automatizada;
- qualquer verificação manual precisa contornar o ambiente real.

### 2. Sanitização de e-mail incompleta

O helper novo `app/Support/EmailHtmlDocument.php` documenta remoção de URLs
`data:` e `javascript:`, mas a implementação atual neutraliza apenas
`javascript:` em `href`/`src`.

Isso é um risco porque o editor de modelos de e-mail passa a aceitar HTML mais
rico e precisa de um contrato de sanitização consistente com o restante do
projeto.

### 3. Documentação com desvio do estado real

O `README.md` cita arquivos de QA e smoke test que não existem no diretório de
teste atual. Hoje o repositório só mostra `tests/Unit/quiz_system.php`.

Isso não é só detalhe editorial: afeta a confiabilidade do onboarding e da
operação.

### 4. Tree de trabalho poluído

Existem modificações locais e vários arquivos soltos fora do código principal,
incluindo backups, diagnósticos e artefatos temporários.

Risco:

- revisão confusa;
- chance de commit acidental;
- dificuldade para distinguir código real de resíduo operacional.

## Mudanças recentes observadas

As alterações mais recentes concentram trabalho em:

- modelos de e-mail;
- certificados;
- layout global;
- home V2;
- CSS da V2.

Isso indica evolução funcional, mas também aponta um ponto de atenção:
`EmailModeloService` está acumulando bastante comportamento novo em uma única
classe. Ele continua dentro do padrão do projeto, mas já merece cuidado para
evitar virar uma camada monolítica dentro do próprio service.

## Verificações executadas

- Inspeção de estrutura de diretórios e arquivos principais.
- Leitura de `README.md`, `AGENTS.md` e `CLAUDE.md`.
- Leitura de `index.php`, `routes/web.php` e `routes/api.php`.
- Revisão das mudanças em:
  - `app/Controllers/Admin/CertificadosController.php`
  - `app/Controllers/Admin/EmailModelosController.php`
  - `app/Models/Inscricao.php`
  - `app/Services/CertificadoService.php`
  - `app/Services/EmailModeloService.php`
  - `app/Support/EmailHtmlDocument.php`
  - `app/Support/EmailPlaceholders.php`
- `php -l` nos arquivos PHP modificados e novos.
- Execução de `tests/Unit/quiz_system.php`, que falhou por ambiente.

## Conclusão

Minha leitura final é:

- a base tem uma arquitetura viável;
- o domínio principal já está bem coberto;
- a maior fragilidade atual é operacional, não conceitual;
- a documentação precisa acompanhar melhor o que realmente existe no repositório;
- antes de crescer mais, o projeto se beneficia de limpeza de tree, correção do
  ambiente de teste e fechamento da superfície de sanitização do editor de
  e-mails.

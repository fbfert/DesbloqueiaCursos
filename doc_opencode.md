# Polo Rainbow - Documentação do Projeto

Portal de cursos e eventos com PHP MVC puro, MySQL 5.7, checkout em PIX manual, área do aluno (LMS), backoffice administrativo, financeiro com rateios, certificados com validação pública, logs e auditoria.

---

## Índice

1. [Visão Geral](#visao-geral)
2. [Arquitetura](#arquitetura)
3. [Estrutura de Diretórios](#estrutura-de-diretorios)
4. [Banco de Dados](#banco-de-dados)
5. [Autenticação e Perfis](#autenticacao-e-perfis)
6. [RBAC (Controle de Acesso)](#rbac-controle-de-acesso)
7. [Rotas](#rotas)
8. [Módulos do Sistema](#modulos-do-sistema)
9. [Serviços (Services)](#servicos-services)
10. [Modelos (Models)](#modelos-models)
11. [Frontend](#frontend)
12. [Arquivos e Uploads](#arquivos-e-uploads)
13. [Logs e Auditoria](#logs-e-auditoria)
14. [Execução Local](#execucao-local)
15. [Deploy](#deploy)
16. [Convenções de Código](#convencoes-de-codigo)
17. [Regras de Negócio](#regras-de-negocio)
18. [Próximas Etapas](#proximas-etapas)

---

## Visão Geral

| Item | Detalhe |
|---|---|
| **Nome** | Polo Rainbow |
| **Stack** | PHP MVC puro, MySQL 5.7, Frontend mobile-first |
| **Checkout** | PIX manual, compra para terceiros, compra em lote |
| **Perfis** | Admin, Professor, Aluno |
| **Certificados** | Código alfanumérico, QR Code, validação pública |
| **Arquivos privados** | Fora da `public_html` |
| **Lixeira** | Com justificativa obrigatória |
| **Compatibilidade** | MySQL 5.7, Linux/cPanel |

---

## Arquitetura

### Padrão MVC próprio

O projeto utiliza um MVC leve sem dependência de frameworks externos:

```
Request → Router → Middleware → Controller → Service → Model → Database
                                              ↓
                                        View (PHP)
                                              ↓
                                        Response
```

### Core (`app/Core/`)

| Arquivo | Responsabilidade |
|---|---|
| `App.php` | Instância principal da aplicação, singleton |
| `Autoloader.php` | PSR-0-like, mapeia namespaces para caminhos |
| `Controller.php` | Classe base com `view()`, `redirect()`, `json()` |
| `Csrf.php` | Geração e validação de tokens CSRF |
| `Database.php` | Singleton PDO, configuração em `config/database.php` |
| `Env.php` | Parser de `.env` |
| `ErrorHandler.php` | Captura de erros e exceções |
| `Helpers.php` | Funções utilitárias (`Helpers::e()` para escaping) |
| `Logger.php` | Logs em arquivo (JSON) |
| `Request.php` | Wrapper de `$_GET`, `$_POST`, `$_FILES`, headers |
| `Response.php` | HTML, JSON, redirects |
| `Router.php` | Registro de rotas, dispatch, stack de middleware |
| `Session.php` | Wrapper seguro para `$_SESSION` com flash messages |
| `Validator.php` | Validação de inputs |
| `View.php` | Renderização de templates PHP |

### Middleware (`app/Middleware/`)

| Middleware | Função |
|---|---|
| `AuthenticateMiddleware` | Verifica sessão ativa do usuário |
| `CsrfMiddleware` | Valida token CSRF em requisições POST |
| `PermissionMiddleware` | Verifica permissão RBAC (`permission:x.y.z`) |

### Fluxo de requisição

1. `public_html/index.php` define `BASE_PATH` e `PUBLIC_PATH`
2. Carrega autoloader, variáveis de ambiente, sessão e errorHandler
3. Cria `App`, carrega rotas (`web.php`, `api.php`)
4. `App::run()` captura a requisição e delega ao `Router`
5. `Router` encontra a rota, monta o stack de middleware e executa
6. Controller chama Services que acessam Models
7. View é renderizada e retorna `Response`

---

## Estrutura de Diretórios

```
Portal de Cursos/
├── app/
│   ├── Controllers/          # Controladores (Admin, Professor, Api, públicos)
│   ├── Core/                 # Framework MVC próprio
│   ├── Middleware/           # Middleware stack
│   ├── Models/               # Acesso a dados (PDO)
│   └── Services/             # Regras de negócio
├── config/
│   ├── app.php               # Configurações gerais (debug, URL, timezone)
│   ├── database.php          # Conexão PDO
│   ├── mail.php              # SMTP
│   └── storage.php           # Caminhos de storage
├── public_html/              # Document root (cPanel)
│   ├── .htaccess             # Rewrite para index.php
│   ├── assets/               # CSS, JS, imagens públicas
│   └── index.php             # Front controller
├── resources/
│   ├── assets/               # Assets fonte (se aplicável)
│   ├── emails/               # Templates de e-mail transacional
│   └── views/                # Templates PHP (layout.php, home.php, etc.)
├── routes/
│   ├── api.php               # Rotas de API
│   └── web.php               # Rotas web (~270 rotas)
├── sql/                      # Migrations em SQL (001 a 024)
├── storage/                  # Fora da public_html
│   ├── cache/
│   ├── logs/
│   ├── private_uploads/      # Arquivos privados (comprovantes, materiais)
│   ├── tmp/
│   ├── trash/                # Lixeira (justificativa obrigatória)
│   └── uploads/
└── tests/
    ├── Smoke/smoke.php       # Smoke test de rotas críticas
    └── Fixtures/
```

---

## Banco de Dados

### Engine e compatibilidade

- MySQL 5.7 (sem recursos incompatíveis como JSON functions avançadas)
- Charset: UTF-8
- Migrations em SQL simples na pasta `/sql/`

### Migrations (ordem numérica)

| # | Arquivo | Conteúdo |
|---|---|---|
| 001 | `001_auth_module.sql` | Usuários, sessões, autenticação |
| 002 | `002_rbac_access.sql` | RBAC: perfis, permissões, vínculos |
| 003 | `003_catalogo.sql` | Cursos, eventos, categorias |
| 004 | `004_catalogo_refino.sql` | Refinamentos do catálogo |
| 005 | `005_pedidos_inscricoes.sql` | Pedidos, inscrições, participantes |
| 006 | `006_pedidos_refino.sql` | Refinamentos de pedidos |
| 007 | `007_status_and_coupon_prep.sql` | Preparação para cupons |
| 008 | `008_cupons.sql` | Cupons de desconto, usos |
| 009 | `009_email_transacional.sql` | E-mails transacionais, filas |
| 010 | `010_certificados.sql` | Certificados, validação, assinantes |
| 011 | `011_area_curso.sql` | Área do curso: módulos, aulas |
| 012 | `012_presenca_avaliacao.sql` | Presença, avaliações |
| 013 | `013_configuracoes_globais.sql` | Configurações globais do portal |
| 015 | `015_financeiro.sql` | Financeiro: rateios, repasses |
| 016 | `016_rateios_crud.sql` | CRUD de rateios |
| 017 | `017_frontend_modulos_menus.sql` | Frontend: módulos, menus |
| 017b | `017_homologacao_seed.sql` | Seeds para homologação |
| 018 | `018_menus_topo.sql` | Menus de topo |
| 018b | `018_usuario_cidade_estado.sql` | Cidade/estado do usuário |
| 019 | `019_paginas_publicas.sql` | Páginas públicas CMS |
| 020 | `020_perfil_aluno.sql` | Perfil do aluno |
| 021 | `021_frontend_home_destaques_limite.sql` | Destaques da home |
| 022 | `022_area_curso_lms.sql` | LMS: progresso, atividades |
| 023 | `023_area_curso_materiais.sql` | Materiais do curso |
| 024 | `024_area_curso_atividades.sql` | Atividades e entregas |

### Tabelas principais (62 models)

#### Autenticação e Usuários
`usuarios`, `perfis`, `permissoes`, `perfil_permissoes`, `usuario_perfis`, `consentimento_usuario`

#### Catálogo
`cursos_eventos`, `categorias`, `turmas`, `curso_pessoas_vinculadas`, `curso_destaques`

#### Pedidos e Checkout
`pedidos`, `pedido_itens`, `pedido_cupons`, `participante_pedidos`, `inscricoes`, `usuario_cursos`, `usuario_turmas`, `comprovantes_pix`

#### Cupons
`cupons`, `cupons_usos`, `cupons_historico`, `cupons_relacoes`

#### LMS / Área do Curso
`modulos`, `aulas`, `materiais`, `atividades`, `atividade_entregas`, `links_externos`, `instrucoes_curso`, `progresso_usuario_aula`, `progresso_usuario_modulo`

#### Acadêmico
`presencas`, `avaliacoes`, `avaliacoes_perguntas`, `avaliacoes_respostas_usuario`, `notas_avaliacoes`

#### Certificados
`certificados`, `certificados_assinantes`, `certificados_historico`, `certificados_template`, `certificados_validacao_log`

#### Financeiro
`curso_rateios`, `curso_rateio_participantes`, `repasse_professores`, `repasse_documentos`, `pagamento_professores`, `rpa_espelhos`, `professor_fiscal`, `apuracoes_mensais`

#### Frontend e CMS
`frontend_modulos`, `frontend_menus`, `frontend_menu_itens`, `paginas`, `curso_detalhes_destaque`

#### Configurações e Infra
`configuracoes_globais`, `email_configuracoes`, `email_envios`, `logs_acesso`, `logs_auditoria`, `lixeira`

---

## Autenticação e Perfis

### Login
- Por **e-mail** ou **CPF**
- **CPF e e-mail únicos** no sistema
- **Recuperação de senha** com token de 60 minutos

### Perfis

| Perfil | Acesso |
|---|---|
| **Admin** | Backoffice completo: catálogo, pedidos, financeiro, certificados, configurações, RBAC |
| **Professor** | Cursos atribuídos: gestão acadêmica (presença, avaliações, notas), área do curso, financeiro pessoal. **Não vê comprovantes PIX** |
| **Aluno** | Catálogo público, checkout, meus cursos, área de aprendizagem, certificados, perfil |

### Sessão
- Session wrapper próprio (`app/Core/Session.php`)
- Flash messages para erros e sucessos
- Middleware `auth` em rotas protegidas

---

## RBAC (Controle de Acesso)

### Estrutura

```
Perfil (role) → Permissões (permissions) → Usuários
```

### Entidades RBAC

| Tabela | Descrição |
|---|---|
| `perfis` | Perfis/grupos de acesso |
| `permissoes` | Itens de permissão (`modulo.acao.recurso`) |
| `perfil_permissoes` | Vínculo N:N entre perfis e permissões |
| `usuario_perfis` | Vínculo N:N entre usuários e perfis |

### Exemplos de permissões

```
conteudo.ver
conteudo.gerenciar
usuarios.ver
usuarios.gerenciar
pedidos.ver
financeiro.ver
financeiro.gerenciar
financeiro.professor.ver
certificados.ver
certificados.gerenciar
configuracoes_globais.ver
configuracoes_globais.gerenciar
area_curso.gerenciar
area_curso.professor.ver
area_curso.professor.gerenciar
academico.ver
academico.gerenciar
cupons.ver
cupons.gerenciar
frontend.modulos.ver
frontend.modulos.gerenciar
rbac.perfis.gerenciar
rbac.permissoes.ver
rbac.permissoes.gerenciar
rbac.dashboard.ver
catalogo.professor.ver
```

---

## Rotas

### Rotas públicas

| Rota | Descrição |
|---|---|
| `GET /` | Home / capa do portal |
| `GET /cursos` | Catálogo público de cursos |
| `GET /cursos/detalhe` | Detalhe de um curso |
| `GET /como-funciona` | Página explicativa |
| `GET /sobre` | Sobre o portal |
| `GET /contato` | Contato |
| `GET /login` / `POST /login` | Autenticação |
| `GET /cadastro` / `POST /cadastro` | Registro |
| `POST /logout` | Logout |
| `GET /recuperar-senha` | Recuperação de senha |
| `GET /certificados/validar` | Validação pública de certificado |

### Checkout (autenticado ou não)

| Rota | Descrição |
|---|---|
| `GET /inscricao` | Formulário de inscrição |
| `GET /checkout/participantes` | Dados dos participantes |
| `GET /checkout/resumo` | Resumo do pedido |
| `GET /checkout/cupom` | Aplicação de cupom |
| `GET /checkout/comprovante` | Upload de comprovante PIX |
| `GET /checkout/sucesso` | Página de sucesso (autenticado) |

### Área do aluno

| Rota | Descrição |
|---|---|
| `GET /area-curso` | Painel do curso |
| `GET /area-curso/modulo` | Módulo específico |
| `POST /area-curso/modulo/concluir-aula` | Marcar aula como concluída |
| `POST /aluno/cursos/atividade/enviar` | Enviar atividade |
| `GET /meus-cursos` | Meus cursos / pedidos |

### Admin (backoffice)

| Rota | Descrição |
|---|---|
| `GET /admin` | Dashboard executivo |
| `GET /admin/catalogo` | Visão geral do catálogo |
| `GET /admin/cursos` | CRUD de cursos |
| `GET /admin/turmas` | CRUD de turmas |
| `GET /admin/categorias` | CRUD de categorias |
| `GET /admin/pedidos` | Gestão de pedidos |
| `GET /admin/comprovantes-pix` | Aprovação/reprovação de comprovantes |
| `GET /admin/cupons` | CRUD de cupons |
| `GET /admin/usuarios` | CRUD de usuários |
| `GET /admin/rbac` | Dashboard de perfis e permissões |
| `GET /admin/academico` | Gestão acadêmica global |
| `GET /admin/area-curso` | Área interna do curso (LMS admin) |
| `GET /admin/financeiro` | Apuração e repasses |
| `GET /admin/financeiro/repasses` | Gestão de repasses |
| `GET /admin/professores-fiscais` | Professores PF/PJ |
| `GET /admin/rateios` | Rateios por curso |
| `GET /admin/certificados` | Emissão e gestão de certificados |
| `GET /admin/configuracoes-globais` | Configurações do portal |
| `GET /admin/frontend/modulos` | Módulos do frontend |
| `GET /admin/frontend/menus` | Menus do frontend |
| `GET /admin/paginas` | Páginas públicas CMS |
| `GET /admin/emails` | Configuração de e-mails |

### Professor

| Rota | Descrição |
|---|---|
| `GET /professor` | Dashboard do professor |
| `GET /professor/catalogo` | Cursos atribuídos |
| `GET /professor/area-curso` | Gestão da área do curso |
| `GET /professor/academico` | Gestão acadêmica (presença, notas) |
| `GET /professor/financeiro` | Financeiro pessoal |

### API

| Rota | Descrição |
|---|---|
| `GET /api/health` | Health check |

---

## Módulos do Sistema

### 1. Catálogo
- Cursos e eventos separados de turmas/edições
- Categorias, modalidades (presencial, online, híbrido)
- Destaque, ordem de exibição, status (rascunho, ativo, inativo)
- Professor responsável vinculado

### 2. Checkout e Pedidos
- Compra para terceiros (participantes diferentes do comprador)
- Compra em lote (múltiplos participantes)
- PIX manual (upload de comprovante)
- Cupom de desconto (não funciona em curso em promoção)
- Campo de cupom no fluxo de checkout

### 3. Área do Aluno (LMS)
- Módulos, aulas (texto, vídeo), materiais, links externos
- Atividades com entrega de arquivo
- Progresso por aulas e módulos
- Presença (presencial)
- Avaliações com perguntas e notas
- Aptidão para certificado

### 4. Certificados
- Código alfanumérico único
- QR Code para validação
- Validação pública (`/certificados/validar`)
- Template personalizável
- Histórico de emissões
- Reemissão, cancelamento, revogação

### 5. Financeiro e Repasses
- Apuração mensal por competência
- Rateio sobre receita líquida
- Desconto de cupom reduz a base de apuração
- **Teto máximo de rateio: 75%** (configurável)
- Professor PF ou PJ com controle fiscal
- Exigência de nota fiscal para PJ
- Espelho de RPA para PF
- Registro de pagamentos e documentos

### 6. Configurações Globais
- Nome fantasia, razão social, CNPJ, cidade, UF
- Prefixo do certificado
- Template visual do portal
- Política de login e validade do reset de senha
- Data de corte financeiro e teto de rateio
- E-mails institucionais

### 7. Frontend CMS
- Módulos configuráveis na home (com limites)
- Menus de navegação com itens reordenáveis
- Páginas públicas (sobre, como funciona, etc.)

### 8. E-mail Transacional
- Configurações SMTP
- Fila de e-mails a enviar
- Templates personalizáveis

### 9. Logs, Auditoria e Lixeira
- Toda mudança relevante gera log
- Toda exclusão vai para lixeira com justificativa obrigatória
- Rastreio completo de ações por usuário, IP e user agent

---

## Serviços (Services)

39 services que encapsulam regras de negócio:

| Service | Responsabilidade |
|---|---|
| `AccessLogService` | Registro de acessos |
| `AptidaoCertificadoService` | Verifica aptidão para certificado |
| `AreaCursoService` | Contexto da área do curso (admin/aluno/professor) |
| `AtividadeService` | CRUD de atividades, entregas, correções |
| `AuditService` | Registro de auditoria |
| `AulaService` | CRUD de aulas |
| `AuthService` | Autenticação, registro, reset de senha |
| `AvaliacaoService` | Avaliações, perguntas, respostas |
| `CatalogoService` | Consulta ao catálogo |
| `CategoriaService` | CRUD de categorias |
| `CertificadoService` | Emissão, validação, gestão de certificados |
| `CertificadoTemplateService` | Templates de certificado |
| `ComprovantePixService` | Upload, aprovação, reprovação |
| `ConfiguracaoGlobalService` | CRUD das configurações do portal |
| `CupomService` | Validação, aplicação, uso de cupons |
| `CursoService` | CRUD de cursos |
| `DashboardService` | Indicadores do dashboard admin |
| `DashboardProfessorService` | Indicadores do dashboard professor |
| `EmailService` | Envio de e-mails transacionais |
| `FileStorageService` | Armazenamento de arquivos |
| `FinanceiroService` | Apuração, cálculos financeiros |
| `FrontendMenuService` | Menus de navegação |
| `FrontendModuloService` | Módulos do frontend |
| `GestaoAcessoService` | Gestão de acesso a conteúdos |
| `InscricaoService` | Inscrições |
| `MaterialService` | CRUD de materiais |
| `ModuloService` | CRUD de módulos |
| `PaginaService` | Páginas públicas CMS |
| `PedidoService` | Criação e gestão de pedidos |
| `PlaceholderService` | Conteúdo placeholder |
| `PresencaService` | Registro de presença |
| `ProfessorAcademicScopeService` | Escopo acadêmico do professor |
| `ProgressoService` | Progresso do aluno |
| `RateioService` | Cálculo de rateios |
| `RbacService` | Controle de acesso por perfis |
| `RelatorioLmsService` | Relatórios LMS (exportação CSV) |
| `RepasseProfessorService` | Repasses a professores |
| `TrashService` | Lixeira com justificativa |
| `TurmaService` | CRUD de turmas |

---

## Modelos (Models)

62 models com acesso direto via PDO:

**Autenticação:** `Usuario`, `Perfil`, `Permissao`, `PerfilPermissao`, `UsuarioPerfil`, `ConsentimentoUsuario`

**Catálogo:** `CursoEvento`, `Categoria`, `Turma`, `CursoPessoaVinculada`, `CursoDestaque`

**Pedidos:** `Pedido`, `PedidoItem`, `PedidoCupom`, `ParticipantePedido`, `Inscricao`, `UsuarioCurso`, `UsuarioTurma`, `ComprovantePix`

**Cupons:** `Cupom`, `CupomUso`, `CupomHistorico`, `CupomRelacao`

**LMS:** `Modulo`, `Aula`, `Material`, `Atividade`, `AtividadeEntrega`, `LinkExterno`, `InstrucoesCurso`, `ProgressoUsuarioAula`, `ProgressoUsuarioModulo`

**Acadêmico:** `Presenca`, `Avaliacao`, `AvaliacaoPergunta`, `AvaliacaoRespostaUsuario`, `NotaAvaliacao`

**Certificados:** `Certificado`, `CertificadoAssinante`, `CertificadoHistorico`, `CertificadoTemplate`, `CertificadoValidacaoLog`

**Financeiro:** `CursoRateio`, `CursoRateioParticipante`, `RepasseProfessor`, `RepasseDocumento`, `PagamentoProfessor`, `RpaEspelho`, `ProfessorFiscal`, `ApuracaoMensal`

**Infra:** `ConfiguracaoGlobal`, `ConfiguracaoCertificado`, `ConfiguracaoFinanceira`, `ConfiguracaoFrontend`, `ConfiguracaoSeguranca`, `EmailConfiguracao`, `EmailEnvio`, `FrontendModulo`, `FrontendMenu`, `FrontendMenuItem`, `Pagina`

---

## Frontend

### Estrutura de views (`resources/views/`)

```
views/
├── layout.php                    # Layout principal
├── home.php                      # Capa do portal
├── account/                      # Minha conta
├── admin/                        # Backoffice (23 subdiretórios)
│   ├── area-curso/
│   ├── catalogos/
│   ├── certificados/
│   ├── configuracoes-globais/
│   ├── cupons/
│   ├── financeiro/
│   ├── frontend/
│   ├── paginas/
│   ├── pedidos/
│   ├── professor/
│   ├── rbac/
│   ├── turmas/
│   └── ...
├── area-curso/                   # Área do aluno (LMS)
├── auth/                         # Login, registro, recuperação
├── certificados/                 # Validação pública
├── checkout/                     # Fluxo de checkout
├── cursos/                       # Catálogo público
├── errors/                       # 404, 500
├── meus-cursos/                  # Meus cursos do aluno
├── pages/                        # Páginas estáticas
├── paginas/                      # Páginas CMS
├── partials/                     # Partials reutilizáveis
└── professor/                    # Área do professor
```

### CSS

| Arquivo | Uso |
|---|---|
| `public_html/assets/css/app.css` | Estilos do frontend público |
| `public_html/assets/css/admin.css` | Estilos do backoffice admin |

### Princípios
- **Mobile-first** (frontend público)
- **Desktop-first** (backoffice admin)
- Sem framework CSS externo
- Variáveis CSS (`--admin-primary`, `--admin-border`, etc.)

---

## Arquivos e Uploads

### Estrutura de storage (fora da `public_html`)

```
storage/
├── cache/              # Cache
├── logs/               # Logs da aplicação
├── private_uploads/    # Comprovantes PIX, entregas de atividades
├── tmp/                # Temporários
├── trash/              # Lixeira (arquivos deletados)
└── uploads/            # Thumbnails de cursos, materiais públicos
```

### Regras
- Comprovantes PIX: **armazenados em `private_uploads/`** (professores não acessam)
- Materiais de curso: caminho físico ou URL externa
- Entregas de atividades: `private_uploads/`
- Acesso autorizado por contexto (aluno/professor/admin) via `FileStorageService`

---

## Logs e Auditoria

### Tipos de log

| Tipo | Localização |
|---|---|
| **Acesso** | `storage/logs/` - cada requisição relevante |
| **Auditoria** | Tabela `logs_auditoria` - cada mudança em dados |
| **Lixeira** | Tabela `lixeira` - justificativa obrigatória |
| **Erros** | `storage/logs/` - exceções e erros de rota |

### Registro de auditoria

Toda ação administrativa gera um registro com:
- Ação (`area_curso.modulo.criado`, `pedidos.aprovado`, etc.)
- Entidade afetada e ID
- Dados anteriores e novos
- Usuário responsável
- IP e User Agent

---

## Execução Local

```bash
# Copiar ambiente
cp .env.example .env

# Iniciar servidor embutido
php -S 127.0.0.1:8000 -t public_html
```

Rotas iniciais:
- `GET /` - Home
- `GET /api/health` - Health check

---

## Deploy

### Pré-requisitos
- Linux/cPanel
- PHP 7.4+ (compatível com MySQL 5.7)
- MySQL 5.7
- `document_root` apontando para `public_html/`

### Checklist de deploy

1. Backup do banco de dados
2. Backup dos arquivos
3. Aplicar migrations SQL em ordem numérica (`sql/`)
4. Upload do código via FTP
5. Validar `APP_URL`, banco e SMTP
6. Validar rotas críticas e fluxos principais
7. Confirmar logs e upload privado

### Permissões de storage

```bash
chmod 755 storage/
chmod 775 storage/logs storage/cache storage/tmp storage/uploads storage/private_uploads storage/trash
```

### Smoke test

```bash
php tests/Smoke/smoke.php https://polorainbow.com.br
```

### Documentação operacional
- `docs/deploy.md`
- `docs/go-live-checklist.md`
- `docs/rollback.md`
- `docs/qa-checklist.md`
- `docs/homologacao.md`

---

## Convenções de Código

### PHP
- **Sem namespace de framework** — MVC próprio
- **Arrays com `array()`** (compatibilidade com PHP antigo)
- **Sem tipos de retorno obrigatórios** (compatibilidade PHP 7.4)
- Controllers finos, lógica em Services
- Validação de inputs no backend
- Padrão de nomenclatura: `PascalCase` para classes, `camelCase` para métodos, `snake_case` para banco

### Banco de dados
- Migrations SQL simples em `/sql/`
- Sem recursos incompatíveis com MySQL 5.7
- `deleted_at` para soft deletes (lixeira)
- `created_at` e `updated_at` em todas as tabelas

### Frontend
- **Todos os textos em PT-BR com acentuação correta**
- Mobile-first no frontend público
- Desktop-first no backoffice
- Sem emojis em textos de sistema
- Labels, mensagens de erro/sucesso, botões e descrições devem usar acentuação

---

## Regras de Negócio

| Regra | Detalhe |
|---|---|
| **Cursos e eventos separados de turmas** | O curso é o produto; a turma é a edição |
| **Compra para terceiros** | Um usuário pode inscrever outras pessoas |
| **Compra em lote** | Múltiplos participantes no mesmo pedido |
| **PIX manual** | Upload de comprovante, aprovação manual |
| **Cupom** | Não funciona se curso está em promoção |
| **Rateio sobre valor líquido** | Base de cálculo é o valor após descontos |
| **Teto de rateio** | Máximo de 75% configurável |
| **CPF e e-mail únicos** | Não pode haver duplicata no sistema |
| **Login por e-mail ou CPF** | Ambos válidos para autenticação |
| **Reset de senha** | Token válido por 60 minutos |
| **Professor vê apenas cursos atribuídos** | Scope restrito por `usuario_cursos` |
| **Professores não veem comprovantes PIX** | Dados sensíveis restritos ao admin |
| **Delete vai para lixeira** | Justificativa obrigatória |
| **Mudança relevante gera log** | Auditoria completa |
| **Certificado com código alfanumérico** | QR Code + validação pública |
| **Rateio reduzido por cupom** | Cupom desconta da base de apuração |

---

## Próximas Etapas

Conforme `README.md`:

1. Banco de dados inicial (parcialmente completo)
2. Autenticação e perfis (completo)
3. Catálogo de cursos, eventos e turmas (completo)
4. Pedidos, cupons e PIX manual (completo)
5. Certificados, financeiro, logs, auditoria e lixeira (em andamento)

---

*Documento gerado em 06/05/2026 — Polo Rainbow v1.0*

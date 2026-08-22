# Correção de produção — conteúdo institucional e catálogo (2026-07-27)

Auditoria e correção dos 10 problemas reportados na versão publicada do
Desbloqueia Cursos (branch `frontend-v4`). Cada item abaixo traz causa raiz,
arquivos alterados, SQL (quando aplicável) e evidência de teste.

Ambiente desta sessão: o diretório de trabalho é a própria conta de hospedagem
(`/home/desbloqueiacursos`), com o repositório git em `public_html/` (branch
`frontend-v4`, remoto `git@github.com:fbfert/DesbloqueiaCursos.git`). **Nenhum
commit, push ou deploy foi realizado** — apenas edição de arquivos no working
tree, conforme solicitado. Já havia alterações não commitadas de sessões
anteriores no repositório antes desta auditoria; elas não foram tocadas nem
revertidas.

Não houve acesso de escrita ao banco de produção. Toda validação funcional foi
feita contra uma instância MariaDB temporária e isolada (criada e destruída
nesta sessão, `/tmp/...`), populada a partir do backup mais recente disponível
localmente (`backups/db_desbloqueiacursos_2026-07-13.sql.gz`, 13/07/2026). O
banco de produção real não foi acessado (as credenciais em `.env` retornaram
"Access denied" a partir deste ambiente — ver seção "Pendências").

---

## 1) Página `/contato`

**Causa raiz:** `PagesController::contato()` renderiza
`resources/views/pages/contato.php`, uma view **estática**, sem ligação com o
backend, contendo o texto provisório "Canal institucional inicial do portal...".
Não é conteúdo de banco — é HTML fixo no repositório.

Achado secundário (já apontado em `docs-v2-interno/23-fase-2-15-...md`):
`public_html/v2/contato/` tinha apenas `index.html` (protótipo estático antigo),
sem o `index.php`-ponte que todas as outras pastas `v2/*` já têm. Como
`v2/.htaccess` define `DirectoryIndex index.php index.html`, qualquer outra
pasta prioriza o PHP; `v2/contato/` caía no HTML estático por não ter o PHP.
Isso afeta a URL `/v2/contato/` (com barra), que hoje redireciona (301) para
`/onde-estamos` via `V2\InstitucionalController::contato()` — não é a mesma
página que `/contato` (rota canônica, atendida por `PagesController`).

**Correção:**
- `resources/views/pages/contato.php` reescrito com dados institucionais reais
  (nome, WhatsApp clicável, e-mail clicável, endereço de atendimento, botão de
  WhatsApp), sem inventar horário de atendimento (não há esse campo cadastrado
  em `configuracoes_globais`).
- `v2/contato/index.php` criado, seguindo exatamente o padrão das pastas irmãs
  (`require dirname(__DIR__, 2) . '/index.php';`), para que `/v2/contato/`
  passe a rodar pelo front controller em vez do HTML estático.
- Não foi criado formulário de contato: não existe rota `POST` nem
  infraestrutura de recebimento validada para isso hoje; criar um POST público
  sem essa base contrariaria a diretriz de segurança do próprio pedido.

**Arquivos alterados:**
- `resources/views/pages/contato.php`
- `v2/contato/index.php` (novo)

**Teste:** `GET /contato` servido localmente (servidor de teste, ver seção
final) retorna HTTP 200, com exatamente 1 `<h1>`, link `tel:+5549991581411`,
`mailto:desbloqueiacursos@gmail.com` e `wa.me/5549991581411` presentes no HTML
renderizado. Sem texto "Canal institucional inicial do portal" nem "estrutura
está pronta para evoluir".

**Não validado nesta sessão:** o comportamento do `DirectoryIndex` do Apache
(prioridade do `index.php` sobre `index.html` em `/v2/contato/`) depende do
Apache real — o servidor de desenvolvimento do PHP usado para teste não lê
`.htaccess`. A correção segue byte a byte o padrão das demais pastas `v2/*`
(comparado diretamente com `v2/login/index.php`), que já funcionam em
produção; validar `/v2/contato/` em produção/staging Apache após o deploy.

---

## 2) Referências a "Polo Rainbow"

**Causa raiz:** nome antigo do produto, remanescente em:
- `paginas.resumo` da página "Termos de Uso" (`rota = '/termos-de-uso'`):
  "Regras de uso da plataforma Polo Rainbow." — **dado de banco**, exibido
  publicamente logo abaixo do título em `/termos-de-uso` e `/v2/termos-de-uso`.
- `app/Services/EmailService.php` e `config/mail.php`: endereço de fallback
  `no-reply@polorainbow.com.br`, usado apenas quando nenhum e-mail de
  remetente está configurado (última linha de defesa, improvável em produção,
  mas incorreto).
- `resources/views/emails/teste_smtp.php`: texto do e-mail de teste de SMTP.
- `v2/data/cursos.js`: dado fictício de um protótipo estático antigo,
  **órfão** (não é referenciado por nenhuma rota ou view ativa — confirmado por
  busca em todo o `v2/`), mas ainda fisicamente presente e acessível por URL
  direta.

**Não alterado (fora do escopo "texto de apresentação ao usuário"):**
`README.md`, `CLAUDE.md`, `AGENTS.md`, `docs/padrao-editorial-ptbr.md`,
`.env.example` e os arquivos em `sql/*.sql` — documentação interna e
migrations já aplicadas, não exibidas a usuários finais.

**Correção:**
- SQL (`sql/069_...sql`, item 1): corrige o `resumo` da página de Termos de Uso.
- `app/Services/EmailService.php` e `config/mail.php`: fallback trocado para
  `no-reply@desbloqueiacursos.com.br`.
- `resources/views/emails/teste_smtp.php`: texto trocado para "Desbloqueia
  Cursos".
- `v2/data/cursos.js`: as 3 ocorrências de "Polo Rainbow" trocadas para
  "Desbloqueia Cursos" (dado fictício, mas texto visível caso o arquivo seja
  aberto diretamente).

**Arquivos alterados:**
- `app/Services/EmailService.php`, `config/mail.php`,
  `resources/views/emails/teste_smtp.php`, `v2/data/cursos.js`
- `sql/069_correcao_conteudo_institucional_catalogo.sql` (item 1)

**Teste:** busca por `Polo Rainbow`/`polorainbow` em toda a árvore do projeto
não retorna mais nenhuma ocorrência fora de documentação interna e migrations
já aplicadas. HTML renderizado de `/termos-de-uso`, `/v2/termos-de-uso`, `/`
(home) e `/cursos` inspecionado manualmente: nenhuma ocorrência.

---

## 3) Títulos duplicados (`<h1>`) nas páginas institucionais

**Causa raiz confirmada** (reproduzida com o backup de 13/07 e validada ao
vivo no servidor de teste): as páginas institucionais em `/quem-somos`,
`/termos-de-uso`, `/onde-estamos` e `/politica-de-privacidade` **sem** prefixo
`/v2/` são resolvidas pelo fallback do roteador
(`App\Core\Router` → `PaginasController::showByRoute` →
`resources/views/paginas/show.php`), que imprime
`<h1><?= $pagina['titulo'] ?></h1>` **e depois** ecoa `conteudo_html` **sem
sanitização e sem tratamento de título duplicado**. O conteúdo cadastrado no
banco, para essas 4 páginas, também começava com o seu próprio `<h1>` — dois
`<h1>` na mesma página.

As mesmas 4 páginas **com** prefixo `/v2/` (via `V2\InstitucionalController`)
já tinham proteção parcial: `App\Support\V2InstitucionalContent::build()`
sanitiza o HTML e **removia** o primeiro `<h1>` do conteúdo (função
`stripFirstH1`). Isso evitava a duplicação, mas **descartava texto legítimo**
quando o `<h1>` do conteúdo não era apenas uma repetição do título (caso de
"Quem Somos", cujo `<h1 class="quem-somos__titulo">` trazia o texto
"Desbloqueia Cursos", diferente do título da página) — violando a exigência de
não remover subtítulos legítimos.

**Correção estrutural (código, vale para conteúdo atual e futuro):**
1. `App\Support\V2InstitucionalContent::stripFirstH1()` → renomeado para
   `demoteFirstH1()`: em vez de remover o primeiro `<h1>` do conteúdo, ele é
   **rebaixado para `<h2>`** (preserva o texto, elimina a duplicação de nível).
2. `App\Controllers\PaginasController::showByRoute()` passou a processar o
   `conteudo_html` pelo **mesmo pipeline** (`V2InstitucionalContent::build()`)
   usado pelas rotas `/v2/*` — ganho duplo: (a) resolve a duplicação de `<h1>`
   nas 4 rotas canônicas sem prefixo; (b) fecha uma lacuna de segurança: esse
   caminho **não sanitizava** o HTML cadastrado antes de exibi-lo (diferente do
   padrão exigido pelo projeto para conteúdo de editor rico).

**Correção de dados (SQL, limpeza do conteúdo já cadastrado):**
- Termos de Uso e Política de Privacidade: `<h1>` inicial (repetição exata do
  título) removido do `conteudo_html`.
- Onde Estamos: `<h1>` inicial removido (conteúdo reescrito, ver item 4).
- Quem Somos: `<h1 class="quem-somos__titulo">Desbloqueia Cursos</h1>`
  rebaixado para `<h2>` — preserva o subtítulo.

**Arquivos alterados:**
- `app/Support/V2InstitucionalContent.php`
- `app/Controllers/PaginasController.php`
- `sql/069_correcao_conteudo_institucional_catalogo.sql` (itens 1 a 4)

**Teste (servidor local + banco de teste, antes/depois):**

| Rota | `<h1>` antes | `<h1>` depois |
|---|---|---|
| `/quem-somos` | 2 | 1 |
| `/termos-de-uso` | 2 | 1 |
| `/onde-estamos` | 2 | 1 |
| `/politica-de-privacidade` | 2 | 1 |
| `/v2/quem-somos` | 1 (mas descartava "Desbloqueia Cursos") | 1, com "Desbloqueia Cursos" preservado como `<h2>` |
| `/v2/termos-de-uso` | 1 | 1 |
| `/v2/onde-estamos` | 1 | 1 |
| `/v2/politica-de-privacidade` | 1 | 1 |

Todas as 8 combinações retornaram HTTP 200 e sem erros/avisos no log do PHP.

---

## 4) Endereços institucionais

**Situação encontrada:** `configuracoes_globais` (tabela única, sem campo de
logradouro) tem apenas `cidade = "Lages"` / `uf = "SC"`. O endereço completo de
cada unidade só existe como **texto livre** dentro de `paginas.conteudo_html`.
A página `/onde-estamos` continha **somente** o endereço de Lages, rotulado
"Endereço oficial" — o que colide com o endereço jurídico real da CP Educa
Cursos LTDA (Av. Vitória Régia, 1782, Correia Pinto/SC), que já aparecia
corretamente na Política de Privacidade como sede.

**Decisão adotada** (menor ajuste possível, sem mexer em
`configuracoes_globais`, que não tem estrutura para dois endereços): o
conteúdo de `/onde-estamos` agora apresenta as duas informações lado a lado,
nomeadas explicitamente:
- **"Unidade de atendimento — Lages/SC"**: Rua Correia Pinto, nº 534, Centro —
  onde o aluno é atendido e onde ocorrem os cursos presenciais (mantido o mapa
  embutido, que já apontava para esse endereço).
- **"Sede jurídica — Correia Pinto/SC"**: Av. Vitória Régia, nº 1782, Bairro
  Pro Flor, CEP 88.535-000 — endereço de registro da CP Educa Cursos LTDA,
  CNPJ 65.513.089/0001-35.

**Arquivos alterados:** `sql/069_correcao_conteudo_institucional_catalogo.sql`
(item 3, conteúdo de `/onde-estamos`).

**Teste:** `GET /onde-estamos` e `/v2/onde-estamos` renderizam ambos os
blocos, HTTP 200, 1 `<h1>` cada.

---

## 5) Modalidades com valor técnico exposto (`online_ao_vivo`, `sob_demanda`...)

**Causa raiz:** não havia um formatter único. Encontradas **6 implementações
duplicadas e divergentes** do mesmo mapa de rótulos, todas incompletas para os
valores reais do banco (`presencial`, `online_ao_vivo`, `sob_demanda`,
`hibrido` — confirmado por `SELECT DISTINCT modalidade FROM cursos_eventos`):

- `resources/views/v4-claude/catalogo.php` e `.../curso.php` (closures locais
  `$dcModalidade`, mapa não reconhecia `online_ao_vivo`/`sob_demanda` →
  caíam no fallback `ucfirst()`, exibindo `Online_ao_vivo`/`Sob_demanda`);
- `resources/views/components/destaques_home_v3.php` (mapa `$dbcModalidadeLabels`,
  mapeava `online_ao_vivo` incorretamente para "Online", sem "ao vivo");
- `App\Services\CursoService::modalidadeLabel()` (sem acento em "Hibrido");
- `App\Controllers\V2\CatalogoController::modalidadeLabel()` e
  `::montarModalidades()` (duas cópias no mesmo arquivo);
- `App\Controllers\V2\HomeController::modalidadeLabel()`;
- `App\Controllers\V2\AlunoController::modalidadeLabel()`.

**Correção:** criado `App\Core\Helpers::modalidadeCurso()` — mesmo padrão já
usado por `Helpers::statusLms()`/`Helpers::tipoConteudoLms()` (helper único,
já importado em praticamente todas as views). Mapa:
`presencial → Presencial`, `online_ao_vivo → On-line ao vivo`,
`sob_demanda → Sob demanda`, `hibrido/híbrido → Híbrido`, `ead → EaD`, com
fallback seguro (`ucwords(str_replace('_',' ', $valor))`) para qualquer valor
não mapeado. As 6 implementações duplicadas foram trocadas para delegar a esse
helper único (nenhuma view ficou com mapa próprio).

Cobertura confirmada: catálogo (`/cursos`, `/v2/catalogo`), ficha do curso
(`/cursos/detalhe`, `/v2/curso`), home (`components/destaques_home_v3.php`),
área do aluno (`/v2/aluno`, `meus-cursos/index.php`), pedidos/certificados
(`admin/certificados/emissao_rapida_individual.php`, `admin/turmas/*`) e telas
administrativas (`admin/cursos/*`, `admin/area-curso/index.php`,
`professor/catalogo/index.php`).

**Cuidado tomado:** os controllers V2 (`CatalogoController`, `HomeController`,
`AlunoController`, e `CursoController` via `CursoService`) **já formatam** a
modalidade antes de entregá-la à view. As views correspondentes
(`v2/partials/course-card.php`, `v2/pages/curso.php`, `v2/pages/aluno.php`)
recebem o texto **já formatado** — por isso não foram alteradas (evita
formatar duas vezes, o que produziria "Online Ao Vivo" a partir de "On-line ao
vivo" já formatado).

**Arquivos alterados:**
`app/Core/Helpers.php`, `app/Services/CursoService.php`,
`app/Controllers/V2/HomeController.php`, `app/Controllers/V2/AlunoController.php`,
`app/Controllers/V2/CatalogoController.php`,
`resources/views/admin/catalogo/index.php`,
`resources/views/professor/catalogo/index.php`,
`resources/views/v4-claude/catalogo.php`, `resources/views/v4-claude/curso.php`,
`resources/views/cursos/index.php`, `resources/views/cursos/show.php`,
`resources/views/meus-cursos/index.php`, `resources/views/admin/cursos/show.php`,
`resources/views/admin/cursos/form.php`, `resources/views/admin/cursos/index.php`,
`resources/views/admin/turmas/show.php`, `resources/views/admin/turmas/index.php`,
`resources/views/admin/area-curso/index.php`,
`resources/views/admin/certificados/emissao_rapida_individual.php`,
`resources/views/components/destaques_home_v3.php`.

**Teste:** `/cursos` (template `v4-claude`, ativo em produção segundo
`md/RELATORIO-V4.md`) renderizado com banco de teste: badges exibidos foram
"Presencial", "On-line ao vivo", "Sob demanda", "Híbrido" — nenhum valor com
underscore. Confirmado também na home e na ficha do curso.

---

## 6) Curso "Oratória: da Retória a Dialética"

**Confirmado no registro real** (curso id 6, slug `oratoria`, status `ativo`):
título gravado exatamente como
`Oratória: da Retória a Dialética` — faltando a sílaba "ic" em "Retórica" e a
crase em "à Dialética".

**Correção:** `UPDATE cursos_eventos SET nome = 'Oratória: da Retórica à
Dialética' WHERE nome = 'Oratória: da Retória a Dialética'` (SQL idempotente —
casa apenas com o texto antigo).

- **Slug:** `oratoria` — genérico, não contém o erro, **não precisou mudar**.
  Nenhuma URL pública é afetada, não há necessidade de redirecionamento.
- **Certificados:** a tabela `certificados` guarda apenas `curso_evento_id`
  (chave estrangeira), não uma cópia do nome do curso — certificados já
  emitidos e a validação pública passam a exibir o nome corrigido
  automaticamente, sem necessidade de tocar em dados de certificado.
- **E-mails:** o nome do curso é interpolado a partir de `cursos_eventos.nome`
  no momento do envio (não há cópia estática do título em templates de
  e-mail) — corrigido automaticamente.

**Arquivos alterados:** `sql/069_correcao_conteudo_institucional_catalogo.sql`
(item 5).

**Teste:** com o SQL aplicado no banco de teste,
`GET /cursos/detalhe?curso_id=6` exibe `<h1>Oratória: da Retórica à
Dialética</h1>`; `GET /cursos?busca=oratoria` também retorna o card com o
título corrigido. SQL reexecutado uma segunda vez sem alterar nada
(idempotência confirmada).

---

## 7) Paginação do catálogo (`/cursos`)

**Causa raiz:** `CursoEvento::allPublic()` sempre buscava **todas** as linhas
que passam no filtro público (sem `LIMIT`); `CursoService::listPublic()` só
devolvia a lista inteira; `CursosController::index()` não lia parâmetro de
página. A mesma limitação existe (não corrigida) em `V2\CatalogoController`,
que já pagina, mas via `array_slice()` em PHP **depois** de carregar tudo do
banco — mesmo anti-padrão. Como a rota reportada é `/cursos` (V1/`v4-claude`,
template ativo em produção), o backend foi corrigido nesse caminho; o
`/v2/catalogo` não foi tocado para não expandir o escopo desnecessariamente
(fica registrado como pendência de melhoria, seção "Riscos e pendências").

**Correção (server-side, `LIMIT`/`OFFSET` reais no MySQL):**
- `App\Models\CursoEvento`: `allPublic()` refeito para reaproveitar os
  mesmos filtros via `montarFiltrosPublicos()` (elimina duplicação de SQL) e
  aceita `$limit`/`$offset`; novo método `countPublic()` faz `SELECT
  COUNT(*)` com os mesmos filtros, sem JOIN de turmas (mais barato).
- `App\Services\CursoService::listPublic()` ganhou parâmetros opcionais
  `$page`/`$perPage` (12 por página); quando informados, calcula
  `total`/`total_paginas`, força a página para o intervalo válido (nunca
  processa uma página inexistente) e delega ao model com `LIMIT`/`OFFSET`.
  Chamadas existentes sem paginação (`listPublicHome`, `SitemapController`,
  `V2\CatalogoController`) continuam funcionando sem mudança de
  comportamento — o parâmetro é opcional e por padrão preserva o
  comportamento anterior.
- `CursosController::index()` e `CategoriasController::cursos()`: leem
  `?pagina=`, validam como inteiro ≥ 1, repassam ao service.
- Views `resources/views/cursos/index.php` (template v1) e
  `resources/views/v4-claude/catalogo.php` (template ativo em produção):
  navegação Anterior/Próxima/números de página, preservando `busca` e
  `categoria` na querystring; contagem total de resultados exibida.

**Requisitos atendidos:**
12 por página · filtros e busca preservados ao trocar de página · categoria
preservada · URLs indexáveis (`/cursos?categoria=x&busca=y&pagina=n`, sem
depender de JavaScript) · `LIMIT`/`OFFSET` no SQL (nunca carrega tudo para
recortar em PHP) · página inválida (`0`, negativa, não numérica, além do
total) é normalizada/clampada para uma página válida, sem erro · ordenação
mantida (`destaque DESC, ordem ASC, nome ASC`) · total de resultados exibido ·
sem consulta N+1 (a contagem é uma única query separada, e a listagem da
página é uma única query com `LIMIT`).

**Arquivos alterados:**
`app/Models/CursoEvento.php`, `app/Services/CursoService.php`,
`app/Controllers/CursosController.php`, `app/Controllers/CategoriasController.php`,
`resources/views/cursos/index.php`, `resources/views/v4-claude/catalogo.php`.

**Teste (servidor local + banco de teste, 46 cursos passam no filtro
público):**

| Requisição | Resultado |
|---|---|
| `GET /cursos` | HTTP 200, 12 cards, "página 1 de 4", 46 cursos no total |
| `GET /cursos?pagina=2` | HTTP 200, 12 cards (registros diferentes da página 1) |
| `GET /cursos?pagina=999` | HTTP 200, clampada para a página 4 (10 cards, o resto) |
| `GET /cursos?pagina=-5` | HTTP 200, tratada como página 1 |
| `GET /cursos?pagina=abc` | HTTP 200, tratada como página 1 (cast para 0 → clamp para 1) |
| `GET /cursos?busca=oratoria` | HTTP 200, 1 resultado, sem paginação exibida (só 1 página) |
| Links "1", "2", "3", "4", "Anterior", "Próxima" | apontam para `/cursos?pagina=N` |

Nenhum erro/aviso no log do PHP em nenhuma dessas 7 chamadas.

---

## 8) Visibilidade do curso 114 (Proteção Digital Infantojuvenil)

**Diagnóstico.** O curso id 114 e a categoria "Proteção Digital
Infantojuvenil" **não existem** no backup de 13/07/2026 (`cursos_eventos` vai
até id 113 nessa data) — ambos foram criados depois, nas duas semanas antes de
hoje. Por isso não foi possível inspecionar os dados reais deste curso
diretamente nesta sessão (sem acesso de escrita/leitura ao banco de
produção). O diagnóstico abaixo é **baseado em código**, com reprodução
empírica usando um curso sintético equivalente no banco de teste.

**Mecanismo confirmado no código** (`app/Models/CursoEvento.php`,
`app/Models/Categoria.php`):
- `CursoEvento::allPublic()` (catálogo, busca, listagem por categoria, home)
  só retorna um curso se `status = 'ativo'` **E** existir pelo menos uma
  `turma` com `status = 'aberta'` vinculada a ele.
- `CursoEvento::findPublicById()` (ficha do curso, `/cursos/detalhe`) só exige
  `status = 'ativo'` — **não** exige turma aberta.
- `Categoria::allPublicWithCounts()`/`findPublicBySlug()` (listagem e
  navegação por categoria) exigem apenas `categorias.status = 'ativo'`; o
  total de cursos de cada categoria já usa a mesma regra de turma aberta.

**Reprodução empírica** (banco de teste, curso e categoria fictícios com o
mesmo nome/categoria do item reportado, status `ativo`, **sem** turma):

| Rota | Resultado |
|---|---|
| `GET /cursos/detalhe?curso_id=<id>` | HTTP 200 — ficha completa, `<h1>` correto |
| `GET /cursos?busca=Dispositivos` | HTTP 200, **0 resultados** — curso invisível |
| `GET /categorias` | categoria aparece na lista (mas com 0 cursos) |
| `GET /categorias/<slug>/cursos` | HTTP 200, **0 resultados** |

Isso reproduz exatamente o sintoma relatado: o curso "não é encontrado
facilmente no catálogo", mas a URL direta da ficha, se alguém já a tiver,
funciona.

**Conclusão:** a causa mais provável é o curso 114 estar com `status='ativo'`
mas **sem nenhuma turma com `status='aberta'`** — ou, alternativamente, a
categoria "Proteção Digital Infantojuvenil" ainda não estar com
`status='ativo'`. Ambas as situações podem ser **intencionais** (curso ainda
não aberto para inscrição) — por isso **não foi forçada nenhuma alteração**
de dado real; a decisão é administrativa.

**Diagnóstico a rodar em produção** (somente leitura, sem qualquer escrita):

```sql
-- 1) Status do curso, categoria, promoção, imagem
SELECT ce.id, ce.nome, ce.status, ce.categoria_id, c.nome AS categoria_nome,
       c.status AS categoria_status, ce.modalidade, ce.em_promocao,
       ce.thumbnail, ce.usar_turmas, ce.deleted_at
FROM cursos_eventos ce
LEFT JOIN categorias c ON c.id = ce.categoria_id
WHERE ce.id = 114;

-- 2) Turmas do curso 114 e seus status (o campo de FK correto é curso_evento_id)
SELECT id, nome, status, vagas, data_inicio, data_fim,
       inscricoes_abrem_em, inscricoes_encerram_em, deleted_at
FROM turmas
WHERE curso_evento_id = 114;

-- 3) Quantos cursos "públicos" (ativos + com turma aberta) essa categoria tem hoje
SELECT COUNT(*) AS total_cursos_publicos
FROM cursos_eventos ce
WHERE ce.categoria_id = (SELECT categoria_id FROM cursos_eventos WHERE id = 114)
  AND ce.deleted_at IS NULL
  AND ce.status = 'ativo'
  AND EXISTS (
      SELECT 1 FROM turmas t
      WHERE t.curso_evento_id = ce.id AND t.deleted_at IS NULL AND t.status = 'aberta'
  );
```

**Passos administrativos (se a intenção for publicar o curso agora):**
1. Confirmar `cursos_eventos.status = 'ativo'` para o id 114 (query 1).
2. Confirmar `categorias.status = 'ativo'` para a categoria do curso (query 1).
3. Criar/abrir uma turma para o curso 114 em
   `/admin/turmas` (ou `/admin/area-curso`), com `status = 'aberta'` e vagas
   definidas (query 2 mostra o estado atual).
4. Reconferir `/cursos`, `/categorias` e a busca pelo nome do curso.

Nenhuma alteração de dado foi aplicada para este item — depende de decisão e
ação do administrador, conforme instruído.

---

## 9) Política de Privacidade

**Causa raiz:** o texto publicado (mesmo já sendo relativamente bom) listava
apenas nome, e-mail, cidade, estado e CPF como dados coletados — faltando
telefone (também pedido no cadastro) e todo o tratamento feito **depois** do
cadastro: pedidos, participantes, inscrições, turmas, progresso acadêmico,
respostas de avaliação, certificados, comprovante de pagamento PIX, dados de
transação do gateway, logs de acesso/auditoria e o histórico de e-mails
transacionais. Também não mencionava cookies, dados de crianças/adolescentes,
nem o gateway de pagamento usado.

**O que foi confirmado no código antes de escrever o novo texto** (nada foi
inventado):
- Cadastro real coleta nome, e-mail, CPF, telefone, cidade, estado e senha
  (`CLAUDE.md` + `Auth`/cadastro).
- Gateway de pagamento: **AbacatePay** (`app/Services/Payments/AbacatePayService.php`,
  webhook `/webhooks/abacatepay`, rota de pagamento
  `/aluno/pedidos/pagar/abacatepay`).
- E-mails: `EmailService`, SMTP próprio, sem serviço externo de e-mail
  marketing; toda `emails_envios` fica registrada com `entidade_tipo`/`entidade_id`.
- Comprovante de PIX: upload manual, aprovação manual (`comprovantes_pix`),
  arquivos sensíveis ficam fora de `public_html` (`config/storage.php`).
- Lixeira/auditoria: toda exclusão relevante passa por `TrashService`
  (justificativa obrigatória) e `AuditService`.
- Cookies: única verificação encontrada é o cookie de sessão nativo do PHP
  (`App\Core\Session`), usado só para autenticação — **nenhum** script de
  analytics, pixel de anúncio ou cookie de terceiros foi encontrado em
  `app/`, `resources/views/` ou `routes/`.
- RBAC: professores não têm acesso a comprovantes PIX (regra já documentada
  em `CLAUDE.md`).

**Marcado explicitamente como pendência** (não inventado): designação formal
de um encarregado (DPO) e nome do fornecedor de hospedagem — não há esse dado
estruturado em nenhuma configuração do sistema.

**Correção:** `conteudo_html` da página (`rota = '/politica-de-privacidade'`)
substituído por versão ampliada com 12 seções (identificação do controlador,
dados tratados, finalidade, base legal, compartilhamento/operadores,
armazenamento e segurança, cookies, prazo de conservação, dados de
crianças/adolescentes, direitos do titular, remoção de conta, canal de
atendimento, versão e data), preservando o tom e a estrutura do texto
anterior. O documento se declara explicitamentente como política operacional,
não parecer jurídico.

**Arquivos alterados:** `sql/069_correcao_conteudo_institucional_catalogo.sql`
(item 4).

**Teste:** `GET /politica-de-privacidade` e `/v2/politica-de-privacidade`
retornam HTTP 200, 1 `<h1>`, conteúdo íntegro (12.144 bytes de HTML
sanitizado, sem tags perigosas after `HtmlSanitizer`/`V2InstitucionalContent`).

---

## 10) Revisão geral de textos públicos

Verificações feitas, além dos itens já descritos acima:
- Busca por "em breve", "lorem ipsum", "em construção", "texto provisório":
  únicas ocorrências são microcópias legítimas e corretas (ex.: "o curso será
  liberado em breve" em telas de status de pagamento pendente,
  `HomeController` anunciando um bloco de avaliações ainda não implementado) —
  nenhuma delas é o tipo de texto de desenvolvimento encontrado no item 1.
- Página `paginas` com `rota = '/teste'`, `titulo/resumo/conteudo_html = "teste"`,
  **já está na lixeira** (`deleted_at` preenchido desde 28/04/2026) — `GET
  /teste` confirma 404 real (página de erro do sistema, não um 404 bruto do
  servidor). Nenhuma ação necessária.
- Nenhuma outra ocorrência de enum bruto (`_` no meio do texto) encontrada em
  telas públicas após a correção do item 5.
- `v2/backup-estatico/*` e os `.html` remanescentes em `v2/*` (protótipos
  estáticos antigos, sem `index.php`-ponte próprio) continuam fisicamente no
  servidor e são alcançáveis por URL direta, mas **não interceptam nenhuma
  rota real** hoje (as pastas com rota ativa já têm `index.php` com
  prioridade, incluindo `v2/contato/` após a correção do item 1). Registrado
  como risco residual de baixa prioridade — ver seção "Riscos e pendências".

---

## Migration / SQL produzido

Arquivo: `sql/069_correcao_conteudo_institucional_catalogo.sql` (próximo
número disponível na sequência de `sql/`, que já tinha `067` e `068`
pendentes de aplicação de sessões anteriores).

Conteúdo (resumo — ver arquivo completo para o SQL exato):
1. `UPDATE paginas` — Termos de Uso: resumo sem "Polo Rainbow" + remove `<h1>`
   redundante do conteúdo.
2. `UPDATE paginas` — Quem Somos: rebaixa `<h1>` interno para `<h2>`.
3. `UPDATE paginas` — Onde Estamos: conteúdo completo (unidade de atendimento
   vs. sede jurídica).
4. `UPDATE paginas` — Política de Privacidade: conteúdo completo (ver item 9).
5. `UPDATE cursos_eventos` — corrige o título do curso "Oratória".

**Por que é seguro:** todos os `UPDATE` usam `REPLACE()` sobre um trecho
específico ou um `WHERE` que só casa com o **valor antigo exato** — reexecutar
o script depois de aplicado não altera nada (idempotência testada: rodei o
script duas vezes seguidas no banco de teste e o segundo `UPDATE cursos_eventos`
não teve efeito, `nome` permaneceu correto). Nenhum `DELETE`, nenhum `DROP`,
nenhuma alteração de schema. Nenhum dado de aluno, pedido, pagamento,
inscrição ou certificado é tocado.

**Como aplicar em produção:**
```bash
mysql -u <usuario> -p <banco> < sql/069_correcao_conteudo_institucional_catalogo.sql
```
Fazer backup do banco antes (`docs/deploy.md`), como já é prática do projeto.

---

## Testes executados nesta sessão

Ambiente: servidor `php -S` local servindo o próprio código de
`public_html/`, apontado (só via variáveis de ambiente, sem tocar o `.env`
real) para uma instância MariaDB temporária, isolada, criada e destruída nesta
sessão, populada com o backup de 13/07/2026 e limpa ao final.

1. `php -l` em **todos os 32 arquivos PHP alterados** — sem erro de sintaxe.
2. `node --check v2/data/cursos.js` (único `.js` alterado) — sem erro.
3. `/cursos` sem filtro — 200, 12 cards, paginação exibida (46 no total).
4. `/cursos?busca=...` — 200, resultado filtrado corretamente (testado com
   "direito" e "oratoria").
5. `/cursos?categoria=...` — 200, filtro por categoria funcionando.
6. Paginação preservando busca/categoria via `pagina=` na querystring — 200,
   validado com `pagina=2`, `pagina=999`, `pagina=-5`, `pagina=abc` (todas
   normalizadas sem erro).
7. Ficha de curso (`/cursos/detalhe?curso_id=6`, Oratória) — 200, título
   corrigido, modalidade formatada.
8. Curso equivalente ao 114 (reprodução sintética) — ficha 200, ausente do
   catálogo/busca/categoria (root cause confirmada).
9. Todas as páginas institucionais (`/quem-somos`, `/termos-de-uso`,
   `/onde-estamos`, `/politica-de-privacidade`, `/contato`, e as 4 variantes
   `/v2/*`) — 200.
10. Contagem de `<h1>` por página — exatamente 1 em todas as 9 páginas acima.
11. Busca por enum bruto (`_`) nos badges de modalidade renderizados — nenhum
    encontrado (home, catálogo, ficha).
12. Busca por "Polo Rainbow"/"demonstração"/texto provisório no HTML
    renderizado das páginas públicas — nenhuma ocorrência.
13. **Não testado nesta sessão:** viewport 375/390/412/1366px (exigiria
    navegador/engine de renderização, indisponível neste ambiente) e o
    comportamento do `DirectoryIndex` do Apache para `/v2/contato/` (o
    servidor de desenvolvimento do PHP não lê `.htaccess`).
14. Log do servidor de teste inspecionado após toda a bateria de chamadas:
    nenhum erro ou aviso do PHP, com exceção do 404 esperado/correto de
    `/teste` (página propositalmente excluída).

---

## Riscos e pendências

- **Banco de produção não foi acessado.** As credenciais do `.env` retornaram
  `Access denied` a partir deste ambiente (host/porta corretos, mas
  autenticação recusada — não investigado further para evitar múltiplas
  tentativas de login contra um serviço de produção). Todo o SQL deste pacote
  deve ser conferido pelo administrador antes de aplicar (os `WHERE` são
  específicos o bastante para não afetar linhas além das pretendidas).
- **Curso 114**: diagnóstico é baseado em código + reprodução sintética, não
  em inspeção direta do registro real (criado depois do backup disponível).
  Rodar as queries de diagnóstico (item 8) em produção antes de decidir a
  ação.
- **`/v2/catalogo`** (`V2\CatalogoController`) tem o mesmo anti-padrão de
  paginação do item 7 (carrega tudo, recorta com `array_slice` em PHP) — não
  corrigido nesta rodada por não ser a rota reportada (`/cursos`) e para não
  ampliar o escopo/risco desta entrega. Recomendo tratar como item futuro.
- **Diretórios estáticos remanescentes** (`v2/backup-estatico/*`, `.html`
  soltos em `v2/*`) continuam no servidor, alcançáveis por URL direta embora
  não interceptem nenhuma rota ativa. Nenhuma exclusão foi feita (preservação
  de artefatos históricos); considerar remoção em uma limpeza futura,
  combinada com o time.
- **Teste visual/mobile (375/390/412/1366px)** não pôde ser feito nesta
  sessão (sem navegador disponível). As mudanças de HTML reaproveitam classes
  CSS já existentes e usadas em outras telas (`dc-btn`, `button-link`,
  `cta-group`, `feature-list`) — nenhum CSS novo foi criado — mas a validação
  visual final deve ser feita manualmente após o deploy.
- Este pacote **não inclui commit, push nem deploy** — apenas os arquivos
  editados no working tree, conforme instruído.

---

## Deploy (quando autorizado)

1. Backup do banco e dos arquivos (`docs/deploy.md`).
2. Revisar e aplicar `sql/069_correcao_conteudo_institucional_catalogo.sql`
   em produção (idempotente, mas revisar antes).
3. Rodar as queries de diagnóstico do item 8 e decidir/agir sobre o curso 114.
4. Subir os arquivos alterados (lista completa na entrega desta conversa) via
   FTP/rotina normal do projeto.
5. Validar em produção: `/contato`, `/v2/contato/`, `/cursos` (com e sem
   filtro, com paginação), `/cursos/detalhe?curso_id=6`, as 4 páginas
   institucionais (com e sem prefixo `/v2/`), e a home.
6. Conferir `storage/logs` após os testes.

## Rollback

- **Código:** `git checkout -- <arquivo>` por arquivo, ou reverter o commit
  específico desta entrega quando ele existir (nenhum commit foi criado nesta
  sessão).
- **SQL:** não há rollback automático de conteúdo (é edição de texto, não
  schema). Antes de aplicar em produção, um `SELECT` dos registros afetados
  (`paginas` com `rota IN ('/termos-de-uso','/quem-somos','/onde-estamos',
  '/politica-de-privacidade')` e `cursos_eventos WHERE id = 6`) permite
  restaurar manualmente o texto anterior a partir deste documento (os textos
  "antes" de cada item estão descritos acima) ou do backup
  `db_desbloqueiacursos_2026-07-13.sql.gz`.
- **V1 preservada:** nenhuma rota, controller ou view da V1 foi removida;
  apenas o fallback (`PaginasController`) e o formatter de modalidade foram
  reforçados — o mecanismo de rollback via `configuracoes_frontend.template_visual_portal`
  continua intacto.

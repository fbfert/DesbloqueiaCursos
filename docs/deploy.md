# Deploy — Desbloqueia Cursos

Deploy é **manual**, sem CI/CD. Não há `composer.json`/`package.json`; não há
build step. O deploy é essencialmente "copiar os arquivos alterados pro
servidor e conferir".

## 1. Estrutura no servidor

Hoje, na prática, todo o repositório (`app/`, `config/`, `resources/`,
`routes/`, `sql/`, `storage/` e `public_html/` propriamente dito) vive dentro
do mesmo diretório servido pelo Apache/LiteSpeed
(`/home/desbloqueiacursos/public_html/`). O ideal descrito no restante deste
projeto — `app/`, `config/`, `resources/`, `routes/`, `sql/`, `storage/` **fora**
da área pública, só `index.php`, `.htaccess` e `assets/` dentro dela — **não
está em vigor no ambiente atual**. Isso significa que arquivos "não-PHP" ou
com extensão diferente de `.php` dentro dessas pastas (ex.: `.bak`, `.tmp`,
`.sql`) ficam acessíveis por URL direta se alguém souber o caminho. Ver
`docs/go-live-checklist.md` §5 para o que isso implica na prática.

## 2. Passo a passo de um deploy

1. **Nunca faça alterações direto em produção** sem antes validar localmente:
   ```bash
   cp .env.example .env
   php -S 127.0.0.1:8000 -t public_html index.php
   ```
   (o `index.php` como router é necessário — sem ele, rotas que não
   correspondem a um arquivo real no disco retornam 404 no servidor embutido
   do PHP, mesmo que funcionem normalmente atrás do Apache real.)

2. **Valide sintaxe** de todo arquivo PHP/JS alterado:
   ```bash
   php -l caminho/Para/Arquivo.php
   node --check public_html/assets/js/algum-arquivo.js
   ```

3. **Rode o smoke test** contra o ambiente alvo (após subir):
   ```bash
   php tests/Smoke/smoke.php https://desbloqueiacursos.com.br
   ```
   Retorna `0` se as rotas críticas responderam como esperado, `1` caso
   contrário.

4. **Migrations de banco**: arquivos SQL simples e numerados em `sql/` (ex.:
   `054_pedido_recuperacao_v1.sql`). Não há runner automático — aplique
   manualmente, em ordem numérica, antes de subir o código que depende deles.

5. **Envio de arquivos**: scripts de FTP em `scripts/ftp_upload_*.ps1`
   (Windows/PowerShell). Envie **apenas os arquivos alterados** — evite subir
   o diretório inteiro para não sobrescrever nada gerado no servidor
   (`storage/`, uploads, `.env` real).

6. **Nunca suba**: `.env` real, `storage/` (fora do controle de versão por
   design — comprovantes, certificados, uploads privados), backups (`.bak-*`,
   `.tmp`), ou qualquer script de diagnóstico solto na raiz (ver o incidente
   documentado em `docs-v2-interno/22-...md` e a limpeza feita na fase de
   preparação para o go-live da V2 — scripts assim já causaram uma exposição
   real de credencial de banco em produção).

## 3. Variáveis de ambiente relevantes ao V2

- `HOME_VERSION` (`v1` padrão, `v2` para tornar a Home V2 a página inicial).
  Ver `config/app.php` e `docs/go-live-checklist.md`. É o **único** flag —
  não existe um "modo V2 geral"; cada rota V1 tem (ou não) seu equivalente
  `/v2/...` já registrado fixo em `routes/web.php`, independente desse flag.
- `ABACATEPAY_*`: a configuração "de verdade" usada em produção normalmente
  vem do banco (`pagamento_gateway_configuracoes`, editável em
  `/admin/pagamentos`), sobrescrevendo o `.env` — ver
  `AbacatePayService::runtimeConfig()`. O `.env` só serve de fallback quando
  não há linha `source=database` configurada.
- `TEMA_PUBLICO` (`v2` padrão, `caderno` ativa o tema caderno) — ver a
  seção 3.1.

## 3.1 Tema público (`TEMA_PUBLICO`) e prévia

Relatório completo: `docs/2026-10-06-tema-caderno.md`.

- **Valores:** `TEMA_PUBLICO=caderno` ativa o tema, sem diferenciar maiúsculas
  nem espaços nas pontas (`Caderno` também vale); qualquer outro valor, ou a
  ausência da chave, mantém a V2. Vale para home, catálogo, categorias,
  curso, institucionais, validar certificado, erro, login, pós-login, cadastro,
  recuperar e redefinir senha e as etapas do checkout. Área do aluno, aula,
  quiz, atividade e minha conta continuam sempre na V2. A raiz `/` só mostra a
  home do tema com `HOME_VERSION=v2`; com `v1`, a home do tema fica em `/v2/`.
- **Deploy:** suba com `TEMA_PUBLICO=v2`. Nada muda para o aluno.
- **Prévia:** logado com um usuário que tenha `conteudo.gerenciar`, abra
  qualquer página do escopo com `?tema=caderno`. A prévia fica na sessão desse
  usuário (faixa "Prévia do tema caderno — sair" no topo) até `?tema=v2`, o
  link "sair" ou o fim da sessão. Para quem não tem a permissão, `?tema` é
  ignorado.
- **Virada:** `TEMA_PUBLICO=caderno` no `.env`, sem deploy de código, só com
  aprovação do produto. Depois, rode o smoke contra a produção, com
  `SMOKE_CURSO_ID=<id de um curso publicado>` para incluir a ficha de um curso real.
- **Rollback:** `TEMA_PUBLICO=v2` no `.env`. Vale na requisição seguinte.
- **Fontes e ícones são do próprio site**, em `assets/caderno/` (CSS, JS,
  `fontes/*.woff2`, com as licenças SIL OFL em `fontes/OFL-*.txt`) e no sprite
  SVG de `resources/views/caderno/partials/icones.php`.
  As páginas do tema não usam Google Fonts nem o CDN do Tabler; suba a pasta
  `assets/caderno/fontes/` junto, ou os títulos caem na fonte de reserva.
  `assets/caderno/exemplos/` não precisa ir para a produção.
- **Nunca aplique `tests/Fixtures/tema_caderno_vitrine.sql` em produção.** Ela
  cria cursos, categorias, turmas, usuários e pedidos de exemplo para o
  ambiente local. (`tests/` também é bloqueado por HTTP no `.htaccess`.)
- **Antes da virada, compressão e cache.** Em 06/10/2026 a produção servia
  CSS, JS e HTML sem `Content-Encoding` e os estáticos sem `Cache-Control`.
  Sem compressão, home, catálogo e curso do tema passam de 2,5 s de LCP na 4G
  lenta; com gzip, ficam em 2,0–2,2 s. Sugestão a validar no cPanel (os
  módulos precisam estar habilitados na hospedagem), em duas partes:

  1. No `.htaccess` da raiz, só compressão e o cache das fontes. **Não**
     coloque ali cache longo para `text/css` ou `application/javascript`: ele
     valeria para o site inteiro, e arquivos linkados sem `?v=` (por exemplo
     `/assets/css/app.css`, `/assets/css/admin.css`, `/v2/assets/css/v2-main.css`
     e `/v2/assets/js/v2-main.js`) ficariam até um ano sem receber correções.

     ```apache
     <IfModule mod_deflate.c>
         AddOutputFilterByType DEFLATE text/html text/css text/plain application/javascript application/json image/svg+xml
     </IfModule>
     <IfModule mod_expires.c>
         ExpiresActive On
         ExpiresByType font/woff2 "access plus 1 year"
     </IfModule>
     ```

  2. Um `.htaccess` novo **dentro de `assets/caderno/`**, com o cache longo
     só para o CSS e o JS do tema, que são seguros porque os links levam
     `?v=<filemtime>` (cada alteração muda a URL):

     ```apache
     <IfModule mod_expires.c>
         ExpiresActive On
         ExpiresByType text/css "access plus 1 year"
         ExpiresByType application/javascript "access plus 1 year"
     </IfModule>
     ```

  O resto do CSS e do JS continua como hoje (sem `Expires`; o navegador
  revalida pelo `ETag`). Confira depois com
  `curl -s -o /dev/null -D - -H "Accept-Encoding: gzip" https://desbloqueiacursos.com.br/v2/` —
  a resposta precisa trazer `Content-Encoding: gzip` — e, com `-D -`, que
  `/assets/caderno/caderno.css?v=...` traz `Expires` de um ano e
  `/assets/css/app.css` não.
- **Capas dos cursos:** as atuais têm 1,7–2,0 MB cada. Na página do curso a
  capa é o maior elemento da tela; reexportá-las em tamanho e formato menores
  (a mesma arte) é o que permite o LCP de 2,5 s também ali.

## 4. Pós-deploy

- Confira `storage/logs/app-YYYY-MM-DD.log` nos minutos seguintes ao deploy
  (`Logger::error` grava aqui) — é a fonte mais rápida pra saber se algo
  quebrou silenciosamente (sem exceção visível pro usuário).
- Para mudanças em fluxo de pagamento/checkout, teste manualmente uma compra
  de ponta a ponta antes de considerar o deploy concluído — não há suíte de
  testes automatizados cobrindo isso hoje (ver `docs/go-live-checklist.md` §7).

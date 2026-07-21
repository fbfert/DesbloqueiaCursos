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

## 4. Pós-deploy

- Confira `storage/logs/app-YYYY-MM-DD.log` nos minutos seguintes ao deploy
  (`Logger::error` grava aqui) — é a fonte mais rápida pra saber se algo
  quebrou silenciosamente (sem exceção visível pro usuário).
- Para mudanças em fluxo de pagamento/checkout, teste manualmente uma compra
  de ponta a ponta antes de considerar o deploy concluído — não há suíte de
  testes automatizados cobrindo isso hoje (ver `docs/go-live-checklist.md` §7).

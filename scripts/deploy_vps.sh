#!/usr/bin/env bash
# =============================================================================
# Deploy do Desbloqueia Cursos na VPS (AlmaLinux + Virtualmin).
#
# Roda NA VPS, como o usuário do domínio (nunca como root), sobre um pacote
# gerado a partir do git. Faz, nesta ordem:
#   1. checagens (usuário, ferramentas, .env, APP_ENV=production, pacote);
#   2. lint de todos os .php do pacote com o PHP escolhido;
#   3. backup do código atual e dump do banco, fora da área pública;
#   4. migrações SQL que você listar (nunca "todas": não há controle de quais
#      já rodaram em produção e várias migrações antigas não são idempotentes);
#   5. cópia do código novo, preservando .env, storage/, uploads e backups;
#   6. verificação HTTP de rotas públicas e do log do dia;
#   7. registro em ~/deploy-historico.log (commit + migrações aplicadas).
#
# -----------------------------------------------------------------------------
# 1) GERAR O PACOTE (na sua máquina, na raiz do repositório):
#
#    git -c core.autocrlf=false archive --format=tar.gz --prefix=pacote/ \
#        -o deploy-$(git rev-parse --short HEAD).tar.gz HEAD
#
#    (o core.autocrlf=false mantém os arquivos exatamente como estão no git;
#     sem ele, no Windows, este script sairia com CRLF e o bash recusaria.)
#
# 2) ENVIAR para a VPS o pacote e este script (scp, WinSCP ou o gerenciador
#    de arquivos do Virtualmin), por exemplo para ~/deploy/.
#
# 3) RODAR na VPS, como o usuário do domínio:
#
#    bash ~/deploy/deploy_vps.sh --pacote ~/deploy/deploy-fb7b504.tar.gz \
#         --migracoes "080 081" --url https://desbloqueiacursos.com.br
#
#    Simulação (não altera nada, mostra o que seria copiado):
#    bash ~/deploy/deploy_vps.sh --pacote ... --simular
#
#    Reverter o código para o backup de um deploy anterior:
#    bash ~/deploy/deploy_vps.sh --reverter ~/backups/deploy-20261007-153000
#    (o banco NÃO é revertido automaticamente: o comando para restaurar o dump
#     é impresso na tela, para você decidir.)
#
# Opções:
#   --pacote ARQ        pacote .tar.gz gerado pelo git archive (obrigatório)
#   --migracoes "L"     migrações a aplicar, por número ou nome de arquivo,
#                       separadas por espaço: "080 081" ou "058_certificados_templates_segunda_pagina.sql"
#   --url URL           URL pública para a verificação final
#   --app-dir DIR       raiz da aplicação (padrão: ~/public_html)
#   --php BIN           binário do PHP usado no lint e nos scripts (padrão: php)
#   --capas             roda scripts/otimizar_capas.php --aplicar ao final
#   --simular           não altera nada
#   --sim               responde "sim" às confirmações
#   --reverter DIR      restaura o código a partir de um backup deste script
# =============================================================================

set -Eeuo pipefail

APP_DIR="${HOME}/public_html"
BACKUP_ROOT="${HOME}/backups"
HISTORICO="${HOME}/deploy-historico.log"
PHP_BIN="php"
PACOTE=""
MIGRACOES=""
URL=""
CAPAS=0
SIMULAR=0
SIM=0
REVERTER=""

# O que nunca é sobrescrito no servidor (gerado em runtime ou segredo) e o que
# não deve ir para produção (a fixture de teste do tema mora em tests/).
PRESERVAR=(.env 'storage/' 'uploads/' 'backups/' 'assets/uploads/' 'public_html/assets/uploads/' '.git/')
NAO_ENVIAR=('tests/' 'docker/' '.claude/' '.superpowers/')

cor() { printf '\033[%sm%s\033[0m\n' "$1" "$2"; }
info() { cor '1;34' "==> $*"; }
ok() { cor '0;32' "    ok: $*"; }
aviso() { cor '1;33' "    AVISO: $*"; }
falha() { cor '1;31' "ERRO: $*" >&2; exit 1; }

confirmar() {
    [ "$SIM" -eq 1 ] && return 0
    local r
    read -r -p "    $1 [s/N] " r
    [[ "$r" =~ ^[sS]$ ]] || falha "interrompido por você."
}

trap 'cor "1;31" "ERRO na linha $LINENO. Nada depois deste ponto foi executado."' ERR

while [ $# -gt 0 ]; do
    case "$1" in
        --pacote) PACOTE="$2"; shift 2 ;;
        --migracoes) MIGRACOES="$2"; shift 2 ;;
        --url) URL="${2%/}"; shift 2 ;;
        --app-dir) APP_DIR="${2%/}"; shift 2 ;;
        --php) PHP_BIN="$2"; shift 2 ;;
        --capas) CAPAS=1; shift ;;
        --simular) SIMULAR=1; shift ;;
        --sim) SIM=1; shift ;;
        --reverter) REVERTER="${2%/}"; shift 2 ;;
        -h|--help) sed -n '2,60p' "$0"; exit 0 ;;
        *) falha "opção desconhecida: $1 (use --help)" ;;
    esac
done

# --- Leitura do .env (só leitura; nunca exporta tudo para o ambiente) --------
env_get() {
    local linha
    linha=$(grep -E "^[[:space:]]*$1[[:space:]]*=" "$APP_DIR/.env" | tail -n1 || true)
    linha="${linha#*=}"
    linha="${linha%$'\r'}"
    linha="${linha#"${linha%%[![:space:]]*}"}"
    linha="${linha%"${linha##*[![:space:]]}"}"
    if [[ "$linha" =~ ^\"(.*)\"$ ]] || [[ "$linha" =~ ^\'(.*)\'$ ]]; then
        linha="${BASH_REMATCH[1]}"
    fi
    printf '%s' "$linha"
}

# Credenciais do banco num arquivo 600 temporário: a senha não aparece no `ps`.
MYCNF=""
preparar_mysql() {
    MYCNF=$(mktemp)
    chmod 600 "$MYCNF"
    {
        echo "[client]"
        echo "host=$(env_get DB_HOST)"
        echo "port=$(env_get DB_PORT)"
        echo "user=$(env_get DB_USERNAME)"
        echo "password=\"$(env_get DB_PASSWORD | sed 's/\\/\\\\/g; s/"/\\"/g')\""
        echo "default-character-set=utf8mb4"
    } > "$MYCNF"
    DB_NAME=$(env_get DB_DATABASE)
}
limpar() { [ -n "$MYCNF" ] && rm -f "$MYCNF"; [ -n "${STAGE:-}" ] && rm -rf "$STAGE"; return 0; }
trap limpar EXIT

# --- Checagens comuns ---------------------------------------------------------
[ "$(id -u)" -ne 0 ] || falha "não rode como root. Entre como o usuário do domínio: su - <usuario-do-dominio>"
for cmd in tar rsync mysql mysqldump curl "$PHP_BIN"; do
    command -v "$cmd" >/dev/null 2>&1 || falha "comando '$cmd' não encontrado (rsync: dnf install rsync)."
done
[ -f "$APP_DIR/index.php" ] && [ -f "$APP_DIR/.env" ] || falha "$APP_DIR não parece a raiz da aplicação (faltam index.php ou .env). Use --app-dir."

# =============================================================================
# Modo reverter
# =============================================================================
if [ -n "$REVERTER" ]; then
    [ -f "$REVERTER/codigo.tar.gz" ] || falha "backup de código não encontrado em $REVERTER/codigo.tar.gz"
    info "Reverter o código de $APP_DIR para $REVERTER"
    confirmar "Restaurar os arquivos de código (storage/, uploads e .env não são tocados)?"
    tar -xzf "$REVERTER/codigo.tar.gz" -C "$APP_DIR"
    ok "código restaurado."
    echo "$(date '+%F %T') REVERTIDO para $REVERTER" >> "$HISTORICO"
    if [ -f "$REVERTER/banco.sql.gz" ]; then
        aviso "o banco não foi revertido. Se precisar (perde o que entrou depois do backup):"
        echo "      gunzip -c $REVERTER/banco.sql.gz | mysql --defaults-extra-file=<arquivo com as credenciais> $(env_get DB_DATABASE)"
    fi
    exit 0
fi

# =============================================================================
# Deploy
# =============================================================================
[ -n "$PACOTE" ] || falha "informe --pacote (veja --help)."
[ -f "$PACOTE" ] || falha "pacote não encontrado: $PACOTE"

info "Checagens"
APP_ENV=$(env_get APP_ENV)
[ "$(printf '%s' "$APP_ENV" | tr '[:upper:]' '[:lower:]')" = "production" ] \
    || falha "APP_ENV no .env é '$APP_ENV'. Em produção precisa ser 'production' (é o que desliga os CPFs de teste)."
ok "APP_ENV=production"
TEMA=$(env_get TEMA_PUBLICO)
ok "TEMA_PUBLICO=${TEMA:-<ausente, vale v2>}"
if [ "$(printf '%s' "$TEMA" | tr '[:upper:]' '[:lower:]' | tr -d ' ')" = "caderno" ]; then
    aviso "o tema caderno já está ligado. O recomendado é subir com v2 e conferir antes com ?tema=caderno."
    confirmar "Seguir mesmo assim?"
fi
ok "PHP: $("$PHP_BIN" -r 'echo PHP_VERSION;')"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.2", ">=") ? 0 : 1);' \
    || aviso "o PHP da linha de comando é anterior ao 8.2; confira se o domínio usa 8.2, 8.3 ou 8.4 no Virtualmin."

# --- Extrai o pacote ---------------------------------------------------------
STAGE=$(mktemp -d)
tar -xzf "$PACOTE" -C "$STAGE"
ORIGEM="$STAGE"
[ -f "$STAGE/pacote/index.php" ] && ORIGEM="$STAGE/pacote"
[ -f "$ORIGEM/index.php" ] && [ -d "$ORIGEM/app" ] || falha "o pacote não tem a estrutura esperada (index.php e app/ na raiz ou em pacote/)."
if grep -q $'\r' "$ORIGEM/scripts/deploy_vps.sh" 2>/dev/null; then
    aviso "o pacote tem fins de linha CRLF em scripts .sh; gere-o com 'git -c core.autocrlf=false archive'."
fi
VERSAO=$(basename "$PACOTE" .tar.gz)
ok "pacote $VERSAO extraído"

# --- Resolve as migrações pedidas ----------------------------------------------
LISTA_SQL=()
for m in $MIGRACOES; do
    if [ -f "$ORIGEM/sql/$m" ]; then
        LISTA_SQL+=("$m")
        continue
    fi
    mapfile -t achados < <(cd "$ORIGEM/sql" && ls -1 "${m}"_*.sql 2>/dev/null || true)
    [ "${#achados[@]}" -gt 0 ] || falha "migração '$m' não existe em sql/ do pacote."
    [ "${#achados[@]}" -eq 1 ] || falha "o número '$m' corresponde a mais de um arquivo (${achados[*]}). Informe o nome completo."
    LISTA_SQL+=("${achados[0]}")
done
for f in "${LISTA_SQL[@]}"; do
    if [ -f "$HISTORICO" ] && grep -q "migracao $f" "$HISTORICO"; then
        aviso "$f já consta como aplicada em $HISTORICO."
        confirmar "Aplicar $f de novo?"
    fi
done
if [ "${#LISTA_SQL[@]}" -gt 0 ]; then ok "migrações: ${LISTA_SQL[*]}"; else aviso "nenhuma migração será aplicada (--migracoes vazio)."; fi

# --- Lint ---------------------------------------------------------------------
info "Lint dos arquivos PHP do pacote"
ERROS_LINT=$(cd "$ORIGEM" && find . -name '*.php' -not -path './tests/*' -print0 \
    | xargs -0 -n 1 -P 4 "$PHP_BIN" -d display_errors=1 -d error_reporting=-1 -l 2>&1 \
    | grep -v '^No syntax errors' || true)
[ -z "$ERROS_LINT" ] || { echo "$ERROS_LINT"; falha "o lint encontrou problemas; nada foi alterado."; }
ok "sem erros de sintaxe nem avisos de depreciação"

# --- O que vai mudar ------------------------------------------------------------
RSYNC_EXCL=()
for p in "${PRESERVAR[@]}" "${NAO_ENVIAR[@]}"; do RSYNC_EXCL+=(--exclude="/$p"); done
RSYNC=(rsync -rlt --checksum --chmod=D755,F644 "${RSYNC_EXCL[@]}" "$ORIGEM/" "$APP_DIR/")
MUDANCAS=$("${RSYNC[@]}" --dry-run --itemize-changes | grep -E '^[<>c]f' | awk '{print $2}' || true)
QTD=$(printf '%s' "$MUDANCAS" | grep -c . || true)
info "$QTD arquivo(s) novos ou alterados"
printf '%s\n' "$MUDANCAS" | head -n 40 | sed 's/^/      /'
[ "$QTD" -le 40 ] || echo "      ... e mais $((QTD - 40))"

if [ "$SIMULAR" -eq 1 ]; then
    info "Simulação: nada foi alterado."
    exit 0
fi
confirmar "Fazer backup, aplicar as migrações e copiar o código?"

# --- Backup ---------------------------------------------------------------------
DESTINO_BKP="$BACKUP_ROOT/deploy-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$DESTINO_BKP"
chmod 700 "$BACKUP_ROOT" "$DESTINO_BKP"
info "Backup em $DESTINO_BKP"
TAR_EXCL=()
for p in "${PRESERVAR[@]}"; do TAR_EXCL+=(--exclude="./${p%/}"); done
tar -czf "$DESTINO_BKP/codigo.tar.gz" "${TAR_EXCL[@]}" -C "$APP_DIR" .
ok "código: $(du -h "$DESTINO_BKP/codigo.tar.gz" | cut -f1)"
preparar_mysql
mysqldump --defaults-extra-file="$MYCNF" --single-transaction --quick --routines --triggers \
    --no-tablespaces "$DB_NAME" | gzip > "$DESTINO_BKP/banco.sql.gz"
[ -s "$DESTINO_BKP/banco.sql.gz" ] || falha "o dump do banco saiu vazio; nada foi alterado."
ok "banco: $(du -h "$DESTINO_BKP/banco.sql.gz" | cut -f1)"

# --- Migrações ----------------------------------------------------------------------
# Antes do código: o código novo pode depender delas, e as desta leva só
# acrescentam (não quebram o código antigo enquanto a cópia não termina).
for f in "${LISTA_SQL[@]}"; do
    info "Migração $f"
    mysql --defaults-extra-file="$MYCNF" "$DB_NAME" < "$ORIGEM/sql/$f" > /dev/null \
        || falha "a migração $f falhou. O código NÃO foi copiado. Backup do banco: $DESTINO_BKP/banco.sql.gz"
    echo "$(date '+%F %T') migracao $f ($VERSAO)" >> "$HISTORICO"
    ok "aplicada"
done

# --- Cópia do código ------------------------------------------------------------------
LOG_HOJE="$APP_DIR/storage/logs/app-$(date +%F).log"
LINHAS_ANTES=0
[ -f "$LOG_HOJE" ] && LINHAS_ANTES=$(wc -l < "$LOG_HOJE")

info "Copiando o código para $APP_DIR"
"${RSYNC[@]}"
mkdir -p "$APP_DIR"/storage/{app,logs,tmp,uploads,cache,private_uploads,trash}
ok "código copiado (arquivos removidos do repositório continuam no servidor; não há exclusão automática)"
echo "$(date '+%F %T') deploy $VERSAO backup=$DESTINO_BKP" >> "$HISTORICO"

# --- Capas (opcional) -------------------------------------------------------------------
if [ "$CAPAS" -eq 1 ]; then
    info "Otimizando capas existentes"
    (cd "$APP_DIR" && "$PHP_BIN" scripts/otimizar_capas.php --aplicar) \
        || aviso "otimizar_capas.php falhou; o site não depende disso. Rode depois à mão."
fi

# --- Verificação ----------------------------------------------------------------------------
PROBLEMAS=0
if [ -n "$URL" ]; then
    info "Verificando $URL"
    for rota in / /cursos /login /sitemap.xml /v2/catalogo; do
        corpo=$(mktemp)
        status=$(curl -sS -L -o "$corpo" -w '%{http_code}' --max-time 30 "$URL$rota" || echo 000)
        if [ "$status" != "200" ]; then
            aviso "$rota respondeu $status"; PROBLEMAS=1
        elif grep -qiE 'Fatal error|Parse error|Stack trace|Warning: |Deprecated: ' "$corpo"; then
            aviso "$rota mostra erro de PHP no corpo"; PROBLEMAS=1
        else
            ok "$rota 200"
        fi
        rm -f "$corpo"
    done
    status=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 30 "$URL/admin/dashboard" || echo 000)
    if [ "$status" = "200" ]; then
        aviso "/admin/dashboard respondeu 200 sem login: falha de segurança, confira já."; PROBLEMAS=1
    else
        ok "/admin/dashboard sem login: $status (redireciona, como deve)"
    fi
    # Pelo conteúdo, não só pelo status: uma página de erro pode vir com 200.
    if curl -sS --max-time 30 "$URL/.env" 2>/dev/null | grep -q 'DB_PASSWORD'; then
        aviso "/.env abre pela web com a senha do banco! Confira o .htaccess e troque a senha."; PROBLEMAS=1
    else
        ok "/.env não abre pela web"
    fi
else
    aviso "sem --url: verificação HTTP pulada."
fi

# Só as linhas gravadas depois da cópia; "seguranca.autenticacao.ausente" é o
# registro esperado da própria checagem do /admin sem login.
if [ -f "$LOG_HOJE" ]; then
    NOVAS=$(tail -n +"$((LINHAS_ANTES + 1))" "$LOG_HOJE" | grep '"level":"error"' | grep -v 'seguranca.autenticacao.ausente' || true)
    if [ -n "$NOVAS" ]; then
        aviso "erros no log desde o deploy ($LOG_HOJE):"
        printf '%s\n' "$NOVAS" | tail -n 5 | cut -c1-300 | sed 's/^/      /'
        PROBLEMAS=1
    else
        ok "nenhum erro novo no log"
    fi
fi

echo
if [ "$PROBLEMAS" -eq 0 ]; then
    cor '1;32' "Deploy $VERSAO concluído."
else
    cor '1;33' "Deploy $VERSAO concluído com avisos (veja acima)."
fi
cat <<FIM

Próximos passos manuais:
  - Rodar o smoke completo da sua máquina:  php tests/Smoke/smoke.php ${URL:-<url>}
  - Fazer uma compra real de ponta a ponta com PIX.
  - Conferir o tema com ?tema=caderno logado como admin; para ligar, TEMA_PUBLICO=caderno no .env.

Para desfazer o código:  bash $0 --reverter $DESTINO_BKP
FIM

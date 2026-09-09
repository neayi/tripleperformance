#!/usr/bin/env bash
#
# mysql_migrate_auth_caching_sha2.sh
#
# Migrates every MySQL account still using the mysql_native_password plugin
# (inherited from the MySQL 5.7 era) to caching_sha2_password.
#
# Why: mysql_native_password is disabled by default in MySQL 8.4 and REMOVED
# entirely in MySQL 9.x. Every account must be on caching_sha2_password *before*
# 8.4 starts. Run this on the still-running 8.0 instance (caching_sha2_password
# works there too), then do the 8.0 -> 8.4 switch.
#
# There is no in-place hash conversion: native_password and caching_sha2_password
# store different hashes, so the plaintext password is required for each account.
#
# Passwords are auto-discovered from the repo — run it from the repo root on the
# host and it finds every account:
#   * .env               -> MYSQL_USER + MYSQL_PASSWORD   (wiki_prod),
#                            root + MYSQL_ROOT_PASSWORD,
#                            ITINERA_MYSQL_USER + ITINERA_MYSQL_PASSWORD
#   * .env.preprod        -> MYSQL_USER + MYSQL_PASSWORD   (wiki_preprod)
#   * insights/.env       -> DB_USERNAME + DB_PASSWORD     (insights_prod), + _WIKI conn
#   * insights/.env.preprod -> DB_USERNAME + DB_PASSWORD   (insights_preprod)
#   * the Matomo / Piwigo containers -> their own config files (matomo, piwigo)
# Anything left unresolved can be supplied with --creds FILE (see format below).
#
# The reserved internal accounts (mysql.infoschema / mysql.session / mysql.sys)
# are converted with a plugin-only ALTER (no password) since they stay LOCKED.
#
# --creds FILE format: one account per line, "user" or "user@host", then a TAB
# (or a ':') , then the password (rest of the line, verbatim). '#' comments and
# blank lines ignored. "user" with no host matches that user on any host.
#   Example:
#     wiki_prod        s3cr3t-prod-pw
#     wiki_preprod:another:pw:with:colons
#     insights_prod@%  laravel-pw
#
# Usage (from the repo root):
#   ./bin/mysql_migrate_auth_caching_sha2.sh            # dry run, auto-discovered
#   ./bin/mysql_migrate_auth_caching_sha2.sh --apply
#   ./bin/mysql_migrate_auth_caching_sha2.sh --creds extra.txt --apply
#
# root auth: uses backup/.mysql.cnf via --defaults-extra-file (same as the backup
# scripts) if present, else MYSQL_ROOT_PASSWORD from .env, else ROOT_PW=… env.
#
# Env overrides: ENV_FILE, DB_SERVICE, DB_CONTAINER, MATOMO_SERVICE,
#                PIWIGO_SERVICE, ROOT_CNF (path to a my.cnf), ROOT_PW
# Flags: --apply   actually run the ALTER USERs
#        --skip-reserved   leave mysql.infoschema/session/sys untouched
#
# IMPORTANT — run on preprod first and check every service reconnects.
# caching_sha2_password's first handshake on a non-TLS connection needs the
# client to fetch the server's RSA public key (the official mysql image
# auto-generates it); PHP 8.2 mysqlnd, a recent Node mysql2 and dbgate all
# support this, but verify.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

ENV_FILE="${ENV_FILE:-${REPO_DIR}/.env}"
DB_SERVICE="${DB_SERVICE:-db}"
MATOMO_SERVICE="${MATOMO_SERVICE:-matomo}"
PIWIGO_SERVICE="${PIWIGO_SERVICE:-piwigo}"
APPLY=0
SKIP_RESERVED=0
CREDS_FILE=""

while [ $# -gt 0 ]; do
  case "$1" in
    --apply)         APPLY=1 ;;
    --skip-reserved) SKIP_RESERVED=1 ;;
    --creds)         CREDS_FILE="${2:-}"; shift ;;
    --creds=*)       CREDS_FILE="${1#--creds=}" ;;
    *) echo "unknown arg: $1" >&2; exit 2 ;;
  esac
  shift
done

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m warn:\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

# escape a value for use inside a single-quoted MySQL string literal
sql_str() { printf "%s" "$1" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g"; }

command -v docker >/dev/null || die "docker not found in PATH"

# ---------------------------------------------------------------------------
# credential store: two assoc arrays, keyed by "user@host" and by bare "user"
# ---------------------------------------------------------------------------
declare -A PW_BY_UH   # exact user@host  -> password
declare -A PW_BY_U    # user (any host)  -> password

add_cred() { # $1=user  $2=host(optional, "" = any)  $3=password
  local u="$1" h="$2" p="$3"
  [ -n "$u" ] || return 0
  if [ -n "$h" ]; then PW_BY_UH["${u}@${h}"]="$p"; else PW_BY_U["$u"]="$p"; fi
}

# --- read one key from an env-style file ------------------------------------
efget() {
  local file="$1" key="$2" val
  [ -f "$file" ] || return 0
  val="$(grep -E "^[[:space:]]*(export[[:space:]]+)?${key}=" "$file" 2>/dev/null | tail -n1 || true)"
  val="${val#*"${key}="}"
  val="${val%$'\r'}"; val="${val#\"}"; val="${val%\"}"; val="${val#\'}"; val="${val%\'}"
  printf '%s' "$val"
}

load_env_file() { # MediaWiki-style .env
  local f="$1"; [ -f "$f" ] || return 0
  local u p
  u="$(efget "$f" MYSQL_USER)";       p="$(efget "$f" MYSQL_PASSWORD)"
  if [ -n "$u" ] && [ -n "$p" ]; then add_cred "$u" "" "$p"; log "creds: ${u} <- $(basename "$f") MYSQL_PASSWORD"; fi
  p="$(efget "$f" MYSQL_ROOT_PASSWORD)"
  if [ -n "$p" ]; then add_cred "root" "" "$p"; fi
  u="$(efget "$f" ITINERA_MYSQL_USER)"; p="$(efget "$f" ITINERA_MYSQL_PASSWORD)"
  [ -n "$u" ] || u="itinera_user"
  if [ -n "$p" ]; then add_cred "$u" "" "$p"; log "creds: ${u} <- $(basename "$f") ITINERA_MYSQL_PASSWORD"; fi
  return 0
}

load_laravel_env() { # insights-style .env (DB_USERNAME/DB_PASSWORD, plus the _WIKI conn)
  local f="$1"; [ -f "$f" ] || return 0
  local u p suffix
  for suffix in "" "_WIKI"; do
    u="$(efget "$f" "DB_USERNAME${suffix}")"; p="$(efget "$f" "DB_PASSWORD${suffix}")"
    if [ -n "$u" ] && [ -n "$p" ]; then add_cred "$u" "" "$p"; log "creds: ${u} <- ${f#"$REPO_DIR"/} DB_PASSWORD${suffix}"; fi
  done
  return 0
}

# --- user-provided credentials file ---------------------------------------
load_creds_file() {
  local f="$1"; [ -f "$f" ] || die "--creds file not found: $f"
  local line user host pw n=0
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%$'\r'}"
    case "$line" in ''|\#*) continue ;; esac
    if [[ "$line" == *$'\t'* ]]; then user="${line%%$'\t'*}"; pw="${line#*$'\t'}"
    elif [[ "$line" == *:* ]];     then user="${line%%:*}";   pw="${line#*:}"
    else warn "creds file: no TAB or ':' on line: ${line}"; continue
    fi
    host=""
    [[ "$user" == *@* ]] && { host="${user#*@}"; user="${user%@*}"; }
    add_cred "$user" "$host" "$pw"; n=$((n+1))
  done < "$f"
  log "creds: loaded ${n} entr$([ "$n" = 1 ] && echo y || echo ies) from ${f}"
}

PROJECT="$(efget "$ENV_FILE" COMPOSE_PROJECT_NAME)"
find_container() {
  local svc="$1" cid=""
  [ -n "$PROJECT" ] && cid="$(docker ps -q \
    --filter "label=com.docker.compose.project=${PROJECT}" \
    --filter "label=com.docker.compose.service=${svc}" | head -n1)"
  [ -z "$cid" ] && [ -n "$PROJECT" ] && \
    cid="$(docker ps -q -f "name=^${PROJECT}[-_]${svc}[-_]1$" | head -n1)"
  printf '%s' "$cid"
}

# --- gather credentials ---------------------------------------------------
[ -n "$CREDS_FILE" ] && load_creds_file "$CREDS_FILE"
load_env_file    "$ENV_FILE"
load_env_file    "${REPO_DIR}/.env.preprod"
load_laravel_env "${REPO_DIR}/insights/.env"
load_laravel_env "${REPO_DIR}/insights/.env.preprod"

# Matomo
MCID="$(find_container "$MATOMO_SERVICE" || true)"
if [ -n "$MCID" ]; then
  MCONF="$(docker exec -i "$MCID" cat /var/www/html/config/config.ini.php 2>/dev/null || true)"
  mu="$(printf '%s\n' "$MCONF" | sed -nE 's/^username[[:space:]]*=[[:space:]]*"?([^"]*)"?[[:space:]]*$/\1/p' | head -n1)"
  mp="$(printf '%s\n' "$MCONF" | sed -nE 's/^password[[:space:]]*=[[:space:]]*"?([^"]*)"?[[:space:]]*$/\1/p' | head -n1)"
  if [ -n "$mu" ] && [ -n "$mp" ]; then add_cred "$mu" "" "$mp"; log "creds: ${mu} <- Matomo config.ini.php"; fi
fi
# Piwigo (LinuxServer image: /config/www/gallery/local/config/database.inc.php)
PCID="$(find_container "$PIWIGO_SERVICE" || true)"
if [ -n "$PCID" ]; then
  for ppath in \
      /config/www/local/config/database.inc.php \
      /config/www/gallery/local/config/database.inc.php \
      /config/www/piwigo/local/config/database.inc.php; do
    PCONF="$(docker exec -i "$PCID" cat "$ppath" 2>/dev/null || true)"
    [ -n "$PCONF" ] || continue
    pu="$(printf '%s\n' "$PCONF" | sed -nE "s/.*db_user'\][[:space:]]*=[[:space:]]*'([^']*)'.*/\1/p" | head -n1)"
    pp="$(printf '%s\n' "$PCONF" | sed -nE "s/.*db_password'\][[:space:]]*=[[:space:]]*'([^']*)'.*/\1/p" | head -n1)"
    if [ -n "$pu" ] && [ -n "$pp" ]; then add_cred "$pu" "" "$pp"; log "creds: ${pu} <- Piwigo database.inc.php"; fi
    break
  done
fi

# --- connect to the DB ---------------------------------------------------
CID="${DB_CONTAINER:-$(find_container "$DB_SERVICE")}"
[ -n "$CID" ] || die "no running '${DB_SERVICE}' container found. Set DB_CONTAINER=…"

# root auth: try, in order, the backup/.mysql.cnf that the backup scripts use
# (copied in and read via --defaults-extra-file), then any password string we
# have (ROOT_PW env override, --creds 'root', .env MYSQL_ROOT_PASSWORD).
ROOT_ARGS=()
CNF_LOCAL="${ROOT_CNF:-${REPO_DIR}/backup/.mysql.cnf}"
CNF_REMOTE="/tmp/.migrate_auth_root_$$.cnf"
cleanup_cnf() { docker exec "$CID" rm -f "$CNF_REMOTE" >/dev/null 2>&1 || true; }
trap cleanup_cnf EXIT

mysql_root() { docker exec -i "$CID" mysql "${ROOT_ARGS[@]}" -uroot -N -B "$@"; }
root_ok() { mysql_root -e "SELECT 1" >/dev/null 2>&1; }

if [ -f "$CNF_LOCAL" ] && docker cp "$CNF_LOCAL" "$CID:$CNF_REMOTE" >/dev/null 2>&1; then
  ROOT_ARGS=(--defaults-extra-file="$CNF_REMOTE")
  root_ok && log "root auth: ${CNF_LOCAL#"$REPO_DIR"/}"
fi
if ! root_ok; then
  for cand in "${ROOT_PW:-}" "${PW_BY_U[root]:-}" "$(efget "$ENV_FILE" MYSQL_ROOT_PASSWORD)"; do
    [ -n "$cand" ] || continue
    ROOT_ARGS=(-p"$cand")
    root_ok && { log "root auth: password string"; break; }
  done
fi
root_ok || die "cannot connect as root.
Tried backup/.mysql.cnf and MYSQL_ROOT_PASSWORD from ${ENV_FILE}.
Fix: put the real root password in backup/.mysql.cnf ([client] / password=…),
or run with  ROOT_PW='<real root password>' ./bin/mysql_migrate_auth_caching_sha2.sh …"
log "Connected to $(docker inspect -f '{{.Name}}' "$CID" | sed 's#^/##') — $(mysql_root -e 'SELECT VERSION()')"

# --- build the plan ----------------------------------------------------------
mapfile -t ROWS < <(mysql_root -e \
  "SELECT user, host FROM mysql.user
   WHERE plugin='mysql_native_password' ORDER BY user,host;")

if [ "${#ROWS[@]}" -eq 0 ]; then
  log "No account uses mysql_native_password — safe to start MySQL 8.4."
  exit 0
fi

is_reserved() { case "$1" in mysql.infoschema|mysql.session|mysql.sys) return 0 ;; *) return 1 ;; esac; }

log "Accounts still on mysql_native_password:"
STMTS=() ; MISSING=0
for row in "${ROWS[@]}"; do
  IFS=$'\t' read -r u h <<< "$row"
  if is_reserved "$u"; then
    if [ "$SKIP_RESERVED" -eq 1 ]; then
      printf '   \033[1;33m-\033[0m %s@%s  — reserved, skipped (--skip-reserved)\n' "$u" "$h"
      continue
    fi
    printf '   \033[1;36m*\033[0m %s@%s  — reserved, plugin-only conversion\n' "$u" "$h"
    STMTS+=("ALTER USER '$(sql_str "$u")'@'$(sql_str "$h")' IDENTIFIED WITH caching_sha2_password;")
    continue
  fi
  key_uh="${u}@${h}"
  pw="${PW_BY_UH[$key_uh]:-}"
  [ -z "$pw" ] && pw="${PW_BY_U[$u]:-}"
  if [ -z "$pw" ]; then
    printf '   \033[1;31m?\033[0m %s@%s  — no known password, SKIPPED\n' "$u" "$h"
    MISSING=1
    continue
  fi
  printf '   \033[1;32m✓\033[0m %s@%s\n' "$u" "$h"
  STMTS+=("ALTER USER '$(sql_str "$u")'@'$(sql_str "$h")' IDENTIFIED WITH caching_sha2_password BY '$(sql_str "$pw")';")
done

[ "${#STMTS[@]}" -gt 0 ] || die "nothing resolvable — provide a --creds file"

if [ "$APPLY" -eq 0 ]; then
  echo
  log "DRY RUN — statements that would run (passwords redacted):"
  for s in "${STMTS[@]}"; do
    case "$s" in
      *"BY '"*) echo "   ${s%% IDENTIFIED*} IDENTIFIED WITH caching_sha2_password BY '***';" ;;
      *)        echo "   $s" ;;
    esac
  done
  echo
  [ "$MISSING" -eq 1 ] && warn "Some accounts have no known password — add them to --creds before --apply."
  log "Re-run with --apply to execute."
  exit 0
fi

if [ "$MISSING" -eq 1 ]; then
  warn "Some accounts still have no known password and will stay on mysql_native_password."
  warn "They will break under MySQL 8.4. Ctrl-C now and complete the --creds file, or"
  read -r -p "  type 'yes' to apply the resolvable ones anyway: " ans
  [ "$ans" = "yes" ] || die "aborted"
fi

log "Applying…"
{ echo "SET SESSION sql_log_bin=0;"; printf '%s\n' "${STMTS[@]}"; } | docker exec -i "$CID" mysql "${ROOT_ARGS[@]}" -uroot

echo
log "Remaining mysql_native_password accounts:"
LEFT="$(mysql_root -e "SELECT CONCAT('   ',user,'@',host) FROM mysql.user WHERE plugin='mysql_native_password';")"
if [ -n "$LEFT" ]; then printf '%s\n' "$LEFT"; else log "   (none)"; fi
echo
log "Verify each service still connects (wiki, itinera, insights, matomo, piwigo, dbgate)."
[ -z "$LEFT" ] && log "List is empty — proceed with: ./bin/mysql_pre_upgrade_shutdown.sh && docker compose ... up -d"

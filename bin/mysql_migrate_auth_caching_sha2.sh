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
# This script sources them from .env (and, for Matomo, from its config.ini.php).
#
#   root@%, root@localhost   <- MYSQL_ROOT_PASSWORD
#   wiki@%                    <- MYSQL_PASSWORD   (wiki + insights + wiki_* DBs)
#   itinera_user@%            <- ITINERA_MYSQL_PASSWORD
#   matomo@% (if present)     <- Matomo container's config/config.ini.php
#
# Usage:
#   ./bin/mysql_migrate_auth_caching_sha2.sh            # dry run: shows the plan
#   ./bin/mysql_migrate_auth_caching_sha2.sh --apply    # execute the ALTER USERs
#
# Env overrides: ENV_FILE, DB_SERVICE, DB_CONTAINER, MATOMO_SERVICE (default matomo)
#
# IMPORTANT — test on preprod first. caching_sha2_password's *first* handshake on
# a non-TLS connection needs the client to fetch the server's RSA public key
# (the official mysql image auto-generates it). PHP 8.2 mysqlnd, a recent Node
# mysql2 (Itinera) and dbgate all support this, but verify every service
# reconnects before doing prod.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

ENV_FILE="${ENV_FILE:-${REPO_DIR}/.env}"
DB_SERVICE="${DB_SERVICE:-db}"
MATOMO_SERVICE="${MATOMO_SERVICE:-matomo}"
APPLY=0
[ "${1:-}" = "--apply" ] && APPLY=1

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m warn:\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

env_get() {
  local key="$1"
  [ -f "$ENV_FILE" ] || return 0
  local val
  val="$(grep -E "^[[:space:]]*${key}=" "$ENV_FILE" | tail -n1 | cut -d= -f2-)"
  val="${val%$'\r'}"; val="${val#\"}"; val="${val%\"}"; val="${val#\'}"; val="${val%\'}"
  printf '%s' "$val"
}

# escape a value for use inside a single-quoted MySQL string literal
sql_str() { printf "%s" "$1" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g"; }

command -v docker >/dev/null || die "docker not found in PATH"

PROJECT="$(env_get COMPOSE_PROJECT_NAME)"
ROOT_PW="$(env_get MYSQL_ROOT_PASSWORD)"
WIKI_PW="$(env_get MYSQL_PASSWORD)"
ITINERA_PW="$(env_get ITINERA_MYSQL_PASSWORD)"
[ -n "$ROOT_PW" ] || die "MYSQL_ROOT_PASSWORD not found in ${ENV_FILE}"

find_container() {
  local svc="$1" cid=""
  [ -n "$PROJECT" ] && cid="$(docker ps -q \
    --filter "label=com.docker.compose.project=${PROJECT}" \
    --filter "label=com.docker.compose.service=${svc}" | head -n1)"
  [ -z "$cid" ] && [ -n "$PROJECT" ] && \
    cid="$(docker ps -q -f "name=^${PROJECT}[-_]${svc}[-_]1$" | head -n1)"
  printf '%s' "$cid"
}

CID="${DB_CONTAINER:-$(find_container "$DB_SERVICE")}"
[ -n "$CID" ] || die "no running '${DB_SERVICE}' container found. Set DB_CONTAINER=…"
mysql_root() { docker exec -i "$CID" mysql -uroot -p"$ROOT_PW" -N -B "$@"; }

mysql_root -e "SELECT 1" >/dev/null 2>&1 || die "cannot connect as root with MYSQL_ROOT_PASSWORD"
log "Connected to $(docker inspect -f '{{.Name}}' "$CID" | sed 's#^/##') — $(mysql_root -e 'SELECT VERSION()')"

# --- try to recover Matomo's DB password from its config ---------------------
MATOMO_USER="" ; MATOMO_PW=""
MCID="$(find_container "$MATOMO_SERVICE" || true)"
if [ -n "$MCID" ]; then
  MCONF="$(docker exec -i "$MCID" cat /var/www/html/config/config.ini.php 2>/dev/null || true)"
  if [ -n "$MCONF" ]; then
    MATOMO_USER="$(printf '%s\n' "$MCONF" | sed -nE 's/^username[[:space:]]*=[[:space:]]*"?([^"]*)"?[[:space:]]*$/\1/p' | head -n1)"
    MATOMO_PW="$(printf '%s\n' "$MCONF" | sed -nE 's/^password[[:space:]]*=[[:space:]]*"?([^"]*)"?[[:space:]]*$/\1/p' | head -n1)"
    [ -n "$MATOMO_USER" ] && log "Matomo config: DB user '${MATOMO_USER}' (password recovered: $([ -n "$MATOMO_PW" ] && echo yes || echo no))"
  fi
fi

# --- resolve a password for a given account name ----------------------------
pw_for() {
  case "$1" in
    root)                 printf '%s' "$ROOT_PW" ;;
    wiki)                 printf '%s' "$WIKI_PW" ;;
    itinera_user|itinera) printf '%s' "$ITINERA_PW" ;;
    "$MATOMO_USER")       printf '%s' "$MATOMO_PW" ;;
    matomo)               printf '%s' "$MATOMO_PW" ;;
    *)                    printf '' ;;
  esac
}

# `mysql -N -B` separates columns with a real tab
mapfile -t ROWS < <(mysql_root -e \
  "SELECT user, host FROM mysql.user
   WHERE plugin='mysql_native_password' ORDER BY user,host;")

if [ "${#ROWS[@]}" -eq 0 ]; then
  log "No account uses mysql_native_password — safe to start MySQL 8.4."
  exit 0
fi

log "Accounts still on mysql_native_password:"
STMTS=() ; MISSING=0
for row in "${ROWS[@]}"; do
  IFS=$'\t' read -r u h <<< "$row"
  pw="$(pw_for "$u")"
  if [ -z "$pw" ]; then
    printf '   \033[1;31m?\033[0m %s@%s  — no known password, SKIPPED\n' "$u" "$h"
    MISSING=1
    continue
  fi
  printf '   \033[1;32m✓\033[0m %s@%s\n' "$u" "$h"
  STMTS+=("ALTER USER '$(sql_str "$u")'@'$(sql_str "$h")' IDENTIFIED WITH caching_sha2_password BY '$(sql_str "$pw")';")
done

[ "${#STMTS[@]}" -gt 0 ] || die "no account has a resolvable password — aborting"

if [ "$APPLY" -eq 0 ]; then
  echo
  log "DRY RUN — statements that would run (passwords redacted):"
  for s in "${STMTS[@]}"; do echo "   ${s%% IDENTIFIED*} IDENTIFIED WITH caching_sha2_password BY '***';"; done
  echo
  log "Re-run with --apply to execute."
  [ "$MISSING" -eq 1 ] && warn "Some accounts were skipped — resolve their passwords before dropping the native_password flag."
  exit 0
fi

log "Applying…"
{ echo "SET SESSION sql_log_bin=0;"; printf '%s\n' "${STMTS[@]}"; } | docker exec -i "$CID" mysql -uroot -p"$ROOT_PW"
log "Done. Remaining mysql_native_password accounts:"
mysql_root -e "SELECT user,host FROM mysql.user WHERE plugin='mysql_native_password';" | sed 's/^/   /' || true

echo
log "Verify each service still connects (wiki, itinera, matomo, dbgate)."
log "When the list above is empty you can proceed with the 8.0 -> 8.4 switch:"
log "  ./bin/mysql_pre_upgrade_shutdown.sh && docker compose ... up -d"

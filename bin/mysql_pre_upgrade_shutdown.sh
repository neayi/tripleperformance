#!/usr/bin/env bash
#
# mysql_pre_upgrade_shutdown.sh
#
# Cleanly stops the currently running MySQL 5.7 `db` container so that the next
# `docker compose up` (which will start MySQL 8.0) can perform its in-place
# data-dictionary upgrade.
#
# MySQL 8.0 refuses to upgrade a 5.7 data directory that was not shut down
# cleanly ("Upgrade is not supported after a crash or shutdown with
# innodb_fast_shutdown = 2 ... redo log ... logically non empty"). This script
# sets innodb_fast_shutdown=0 and then stops the container gracefully, which
# flushes the redo log and leaves the data directory in an upgradable state.
#
# Intended prod workflow:
#   git pull                     # brings in the mysql:8.0 bump + prod.cnf fixes
#   ./bin/mysql_pre_upgrade_shutdown.sh
#   docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
#
# The script only STOPS the old container. It never starts anything and never
# touches the data on disk directly.
#
# Env overrides:
#   ENV_FILE       path to the .env file        (default: <repo>/.env)
#   DB_SERVICE     compose service name         (default: db)
#   DB_CONTAINER   explicit container name/id   (default: auto-detected)
#   STOP_TIMEOUT   seconds to wait for a clean shutdown before SIGKILL
#                  (default: 600 — a slow shutdown on a large buffer pool /
#                  purge backlog can take several minutes)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

ENV_FILE="${ENV_FILE:-${REPO_DIR}/.env}"
DB_SERVICE="${DB_SERVICE:-db}"
STOP_TIMEOUT="${STOP_TIMEOUT:-600}"

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m warn:\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

# --- read a single key from the .env file (no full sourcing) -----------------
env_get() {
  local key="$1"
  [ -f "$ENV_FILE" ] || return 0
  local val
  val="$(grep -E "^[[:space:]]*${key}=" "$ENV_FILE" | tail -n1 | cut -d= -f2-)"
  # strip surrounding quotes and a trailing inline comment / whitespace
  val="${val%$'\r'}"
  val="${val#\"}"; val="${val%\"}"
  val="${val#\'}"; val="${val%\'}"
  printf '%s' "$val"
}

command -v docker >/dev/null || die "docker not found in PATH"

PROJECT="$(env_get COMPOSE_PROJECT_NAME)"
ROOT_PW="$(env_get MYSQL_ROOT_PASSWORD)"
[ -n "$ROOT_PW" ] || die "MYSQL_ROOT_PASSWORD not found in ${ENV_FILE}"

# --- locate the running db container ----------------------------------------
CID="${DB_CONTAINER:-}"
if [ -z "$CID" ]; then
  if [ -n "$PROJECT" ]; then
    CID="$(docker ps -q \
      --filter "label=com.docker.compose.project=${PROJECT}" \
      --filter "label=com.docker.compose.service=${DB_SERVICE}" | head -n1)"
  fi
  # fallback: conventional compose container names
  if [ -z "$CID" ] && [ -n "$PROJECT" ]; then
    CID="$(docker ps -q -f "name=^${PROJECT}[-_]${DB_SERVICE}[-_]1$" | head -n1)"
  fi
fi
[ -n "$CID" ] || die "no running '${DB_SERVICE}' container found (project '${PROJECT:-?}'). Set DB_CONTAINER=… to override."

CNAME="$(docker inspect -f '{{.Name}}' "$CID" | sed 's#^/##')"
IMAGE="$(docker inspect -f '{{.Config.Image}}' "$CID")"
log "Target container: ${CNAME} (${CID:0:12}), image ${IMAGE}"

# --- verify we can talk to it and that it is actually 5.x -------------------
mysql_exec() { docker exec -i "$CID" mysql -uroot -p"$ROOT_PW" -N -B "$@"; }

SERVER_VERSION="$(mysql_exec -e "SELECT VERSION();" 2>/dev/null || true)"
[ -n "$SERVER_VERSION" ] || die "cannot connect to MySQL in ${CNAME} with the root password from ${ENV_FILE}"
log "MySQL reports version: ${SERVER_VERSION}"

case "$SERVER_VERSION" in
  5.*) : ;;
  8.*|9.*)
    warn "This container is already MySQL ${SERVER_VERSION%%-*} — no pre-upgrade shutdown needed."
    warn "If you still want a clean stop, run:  docker stop -t ${STOP_TIMEOUT} ${CNAME}"
    exit 0
    ;;
  *) warn "Unexpected version string '${SERVER_VERSION}', continuing anyway." ;;
esac

# --- the actual clean shutdown --------------------------------------------------
log "Setting innodb_fast_shutdown=0 (full slow shutdown: flush redo log, purge, merge change buffer)"
mysql_exec -e "SET GLOBAL innodb_fast_shutdown=0;"

log "Stopping ${CNAME} gracefully (timeout ${STOP_TIMEOUT}s)…"
START_TS=$(date +%s)
# 'restart: always' does not re-trigger on a manual `docker stop`.
docker stop -t "$STOP_TIMEOUT" "$CID" >/dev/null
ELAPSED=$(( $(date +%s) - START_TS ))
log "Container stopped after ${ELAPSED}s"

if [ "$ELAPSED" -ge "$STOP_TIMEOUT" ]; then
  die "shutdown hit the ${STOP_TIMEOUT}s timeout and was likely SIGKILLed — the data dir may still be unclean.
Increase STOP_TIMEOUT and re-run: start the container again (docker start ${CNAME}), then re-run this script."
fi

# --- confirm the shutdown was clean ------------------------------------------
TAIL="$(docker logs --tail 40 "$CID" 2>&1 || true)"
if printf '%s\n' "$TAIL" | grep -qiE 'Shutdown complete|Shutting down complete'; then
  log "Verified: MySQL logged a clean 'Shutdown complete'."
else
  warn "Could not find 'Shutdown complete' in the last 40 log lines. Check manually:"
  warn "  docker logs --tail 100 ${CNAME}"
fi
if printf '%s\n' "$TAIL" | grep -qE '\[ERROR\]'; then
  warn "The tail of the log contains [ERROR] lines — review before starting 8.0:"
  printf '%s\n' "$TAIL" | grep -E '\[ERROR\]' | sed 's/^/    /'
fi

cat <<EOF

$(printf '\033[1;32mDone.\033[0m') The 5.7 data directory is ready for the in-place upgrade.

Next step (starts MySQL 8.0 and runs the upgrade automatically):

  docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

Then watch the upgrade:

  docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f ${DB_SERVICE}

Look for: "Server upgrade from '50700' to '80xxx' completed".
Keep a fresh mysqldump (backup/backup.sh) from before this run — a downgrade to
5.7 is only possible by restoring that dump.
EOF

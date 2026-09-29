#!/bin/bash
#
# Restaure interactivement un backup de backup/DBs/ dans une base MySQL.
#
# - liste les dumps (.sql / .sql.gz) de backup/DBs/, du plus récent au plus ancien
# - liste les bases existantes du serveur (ou permet d'en saisir une nouvelle)
# - DROP puis CREATE de la base cible, puis chargement du dump
#
# Usage:
#   ./restore-db.sh
#
# Variables d'environnement optionnelles:
#   DB_CONTAINER    nom du conteneur MySQL (défaut: détection du conteneur *-db-1 en cours d'exécution)

set -euo pipefail

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
CNF="/etc/mysql/conf.d/mysqlpassword.cnf"
SYSTEM_DBS="information_schema|mysql|performance_schema|sys"

if [ ! -f "$DIR/.mysql.cnf" ]; then
    echo "Erreur : $DIR/.mysql.cnf introuvable (doit contenir [client] password=...)." >&2
    exit 1
fi

# --- Conteneur MySQL ---------------------------------------------------------

if [ -z "${DB_CONTAINER:-}" ]; then
    mapfile -t CONTAINERS < <(docker ps --format '{{.Names}}' | grep -E -- '-db-1$' || true)
    case ${#CONTAINERS[@]} in
        0) echo "Erreur : aucun conteneur *-db-1 en cours d'exécution." >&2; exit 1 ;;
        1) DB_CONTAINER="${CONTAINERS[0]}" ;;
        *)
            echo "Plusieurs conteneurs MySQL trouvés :"
            PS3="Conteneur ? "
            DB_CONTAINER=""
            select SEL in "${CONTAINERS[@]}"; do
                if [ -n "${SEL:-}" ]; then
                    DB_CONTAINER="$SEL"
                    break
                fi
            done
            [ -n "$DB_CONTAINER" ] || { echo "Annulé."; exit 1; }
            ;;
    esac
elif ! docker ps --format '{{.Names}}' | grep -qx "$DB_CONTAINER"; then
    echo "Erreur : conteneur '$DB_CONTAINER' introuvable ou arrêté." >&2
    exit 1
fi
echo "Conteneur : $DB_CONTAINER"
echo

MYSQL() {
    docker exec -i "$DB_CONTAINER" /usr/bin/mysql --defaults-extra-file="$CNF" -u root "$@"
}

cleanup() {
    docker exec "$DB_CONTAINER" rm -f "$CNF" 2>/dev/null || true
}
trap cleanup EXIT

docker cp "$DIR/.mysql.cnf" "$DB_CONTAINER:$CNF"

# --- Choix du backup ---------------------------------------------------------

mapfile -t BACKUPS < <(cd "$DIR/DBs" && ls -1t -- *.sql *.sql.gz 2>/dev/null || true)
if [ ${#BACKUPS[@]} -eq 0 ]; then
    echo "Erreur : aucun backup (.sql / .sql.gz) dans $DIR/DBs." >&2
    exit 1
fi

LABELS=()
for F in "${BACKUPS[@]}"; do
    LABELS+=("$(printf '%-55s %8s  %s' "$F" \
        "$(du -h "$DIR/DBs/$F" | cut -f1)" \
        "$(date -r "$DIR/DBs/$F" '+%Y-%m-%d %H:%M')")")
done

echo "Backups disponibles :"
PS3="Backup à importer ? "
BACKUP=""
select SEL in "${LABELS[@]}"; do
    if [ -n "${SEL:-}" ]; then
        BACKUP="${BACKUPS[$((REPLY - 1))]}"
        break
    fi
done
[ -n "$BACKUP" ] || { echo "Annulé."; exit 1; }
echo

# --- Choix de la base cible --------------------------------------------------

mapfile -t DATABASES < <(MYSQL -N -s -e "SHOW DATABASES" </dev/null | grep -Ev "^($SYSTEM_DBS)$" || true)
NEW_DB_LABEL="<nouvelle base>"

echo "Bases cibles :"
PS3="Base à écraser ? "
TARGET=""
select SEL in "${DATABASES[@]}" "$NEW_DB_LABEL"; do
    if [ -n "${SEL:-}" ]; then
        TARGET="$SEL"
        break
    fi
done
[ -n "$TARGET" ] || { echo "Annulé."; exit 1; }

if [ "$TARGET" = "$NEW_DB_LABEL" ]; then
    read -r -p "Nom de la nouvelle base : " TARGET
fi

if ! [[ "$TARGET" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Erreur : nom de base invalide '$TARGET'." >&2
    exit 1
fi
if [[ "$TARGET" =~ ^($SYSTEM_DBS)$ ]]; then
    echo "Erreur : refus d'écraser la base système '$TARGET'." >&2
    exit 1
fi

# --- Confirmation ------------------------------------------------------------

echo
echo "Conteneur : $DB_CONTAINER"
echo "Backup    : $DIR/DBs/$BACKUP"
echo "Cible     : $TARGET (sera SUPPRIMÉE puis recréée)"
echo
read -r -p "Confirmer l'écrasement de $TARGET ? [tape '$TARGET'] " ANSWER
[ "$ANSWER" = "$TARGET" ] || { echo "Annulé."; exit 1; }

# --- Restauration ------------------------------------------------------------

echo "==> Recréation de $TARGET"
MYSQL -e "DROP DATABASE IF EXISTS \`$TARGET\`;
          CREATE DATABASE \`$TARGET\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" </dev/null

echo "==> Chargement de $BACKUP dans $TARGET"
case "$BACKUP" in
    *.gz) gunzip -c "$DIR/DBs/$BACKUP" | MYSQL -D "$TARGET" ;;
    *)    MYSQL -D "$TARGET" < "$DIR/DBs/$BACKUP" ;;
esac

echo
echo "Terminé : $BACKUP importé dans $TARGET."

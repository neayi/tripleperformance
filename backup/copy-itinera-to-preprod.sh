#!/bin/bash
#
# Copie la base Itinera de prod (itinera_db) vers la base de recette (itinera_preprod).
# À exécuter sur le serveur de PRODUCTION.
#
# - dump de itinera_db (gzip horodaté conservé dans backup/DBs/ pour rollback)
# - DROP puis CREATE de itinera_preprod
# - chargement du dump dans itinera_preprod
# - (re)attribution des droits à itinera_user sur itinera_preprod
#
# Usage:
#   ./copy-itinera-to-preprod.sh [-y]
#
#   -y   ne pas demander de confirmation (itinera_preprod est écrasée sans prompt)
#
# Variables d'environnement optionnelles:
#   DB_CONTAINER    nom du conteneur MySQL         (défaut: tripleperformance_prod-db-1)
#   SRC_DB          base source                    (défaut: itinera_db)
#   DST_DB          base destination               (défaut: itinera_preprod)
#   ITINERA_DB_USER compte applicatif à autoriser  (défaut: itinera_user)

set -euo pipefail

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

DB_CONTAINER="${DB_CONTAINER:-tripleperformance_prod-db-1}"
SRC_DB="${SRC_DB:-itinera_db}"
DST_DB="${DST_DB:-itinera_preprod}"
ITINERA_DB_USER="${ITINERA_DB_USER:-itinera_user}"
CNF="/etc/mysql/conf.d/mysqlpassword.cnf"

ASSUME_YES=0
[ "${1:-}" = "-y" ] && ASSUME_YES=1

if [ ! -f "$DIR/.mysql.cnf" ]; then
    echo "Erreur : $DIR/.mysql.cnf introuvable (doit contenir [client] password=...)." >&2
    exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$DB_CONTAINER"; then
    echo "Erreur : conteneur '$DB_CONTAINER' introuvable ou arrêté." >&2
    exit 1
fi

MYSQL() {
    docker exec -i "$DB_CONTAINER" /usr/bin/mysql --defaults-extra-file="$CNF" -u root "$@"
}

echo "Conteneur      : $DB_CONTAINER"
echo "Source         : $SRC_DB"
echo "Destination    : $DST_DB (sera SUPPRIMÉE puis recréée)"
echo "Compte autorisé: $ITINERA_DB_USER"
echo

if [ "$ASSUME_YES" -ne 1 ]; then
    read -r -p "Confirmer l'écrasement de $DST_DB ? [tape 'oui'] " ANSWER
    [ "$ANSWER" = "oui" ] || { echo "Annulé."; exit 1; }
fi

cleanup() {
    docker exec "$DB_CONTAINER" rm -f "$CNF" 2>/dev/null || true
}
trap cleanup EXIT

docker cp "$DIR/.mysql.cnf" "$DB_CONTAINER:$CNF"

# Vérifie que la base source existe
if ! MYSQL -N -e "SHOW DATABASES LIKE '$SRC_DB'" | grep -qx "$SRC_DB"; then
    echo "Erreur : la base source '$SRC_DB' n'existe pas." >&2
    exit 1
fi

mkdir -p "$DIR/DBs"
DUMP="$DIR/DBs/${SRC_DB}-to-${DST_DB}-$(date +%Y%m%d-%H%M%S).sql.gz"

echo "==> Dump de $SRC_DB vers $DUMP"
docker exec "$DB_CONTAINER" /usr/bin/mysqldump --defaults-extra-file="$CNF" -u root \
    --single-transaction --routines --triggers --events "$SRC_DB" | gzip > "$DUMP"

echo "==> Recréation de $DST_DB"
MYSQL -e "DROP DATABASE IF EXISTS \`$DST_DB\`;
          CREATE DATABASE \`$DST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> Chargement du dump dans $DST_DB"
gunzip -c "$DUMP" | MYSQL -D "$DST_DB"

echo "==> Attribution des droits à '$ITINERA_DB_USER' sur $DST_DB"
MYSQL -e "GRANT ALL PRIVILEGES ON \`$DST_DB\`.* TO '$ITINERA_DB_USER'@'%';
          FLUSH PRIVILEGES;"

echo
echo "Terminé. Dump conservé : $DUMP"
echo "Pense à redémarrer le service : docker compose -f $DIR/../docker-compose.prod.yml restart itinera_preprod"

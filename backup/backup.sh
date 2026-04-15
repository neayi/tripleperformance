#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

docker cp $DIR/.mysql.cnf tripleperformance_prod-db-1:/etc/mysql/conf.d/mysqlpassword.cnf

for DB in $(docker exec tripleperformance_prod-db-1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -s -e "SHOW DATABASES" --skip-column-names); do
    docker exec tripleperformance_prod-db-1 /usr/bin/mysqldump --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf --single-transaction $DB -u root | gzip > $DIR/DBs/$DB-$(date +%Y%m%d).sql.gz
done

docker exec tripleperformance_prod-db-1 rm /etc/mysql/conf.d/mysqlpassword.cnf

rsync -va $DIR/DBs/*-$(date +%Y%m%d).sql.gz neayi.com:~/backup
rsync -va $DIR/../.data/images neayi.com:~/backup
rsync -va $DIR/../.data/insights_prod_storage neayi.com:~/backup
rsync -va /var/www/discourse/shared/standalone/backups neayi.com:~/discourse_backup/
rsync -va /var/www/discourse/containers neayi.com:~/discourse_backup/
rsync -va /var/www/tripleperformance_docker/piwigo/config/www/_data/i/upload neayi.com:~/piwigo_backup/
scp $DIR/../.env $DIR/../.env.preprod neayi.com:~/backup/
scp $DIR/../insights/.env neayi.com:~/backup/.env.insights

docker exec tripleperformance_prod-n8n-1 sh -c "n8n export:workflow --backup --output=/files/workflows/"
docker exec tripleperformance_prod-n8n-1 sh -c "n8n export:credentials --backup --output=/files/credentials/"
rsync -va $DIR/n8n neayi.com:~/backup/n8n

# Snapshot Qdrant
QDRANT_SNAPSHOT_DIR=$DIR/qdrant_snapshots
mkdir -p $QDRANT_SNAPSHOT_DIR
# Déclenche un snapshot de toutes les collections
for COLLECTION in $(curl -s http://localhost:6333/collections | python3 -c "import sys,json; [print(c['name']) for c in json.load(sys.stdin)['result']['collections']]"); do
    curl -s -X POST "http://localhost:6333/collections/${COLLECTION}/snapshots" > /dev/null
done
# Télécharge les snapshots
for COLLECTION in $(curl -s http://localhost:6333/collections | python3 -c "import sys,json; [print(c['name']) for c in json.load(sys.stdin)['result']['collections']]"); do
    SNAPSHOT_NAME=$(curl -s "http://localhost:6333/collections/${COLLECTION}/snapshots" | python3 -c "import sys,json; snaps=json.load(sys.stdin)['result']; print(sorted(snaps, key=lambda s: s['creation_time'])[-1]['name']) if snaps else None")
    if [ -n "$SNAPSHOT_NAME" ] && [ "$SNAPSHOT_NAME" != "None" ]; then
        curl -s -o "$QDRANT_SNAPSHOT_DIR/${COLLECTION}-$(date +%Y%m%d).snapshot" \
            "http://localhost:6333/collections/${COLLECTION}/snapshots/${SNAPSHOT_NAME}"
    fi
done
rsync -va $QDRANT_SNAPSHOT_DIR neayi.com:~/backup/
find $QDRANT_SNAPSHOT_DIR -name "*.snapshot" -type f -mtime +10 -exec rm -f {} \;

find $DIR/DBs -name "*.gz" -type f -mtime +10 -exec rm -f {} \;

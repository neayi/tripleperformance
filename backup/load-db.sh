#!/bin/bash

if [ $# -ne 2 ]; then
    echo "Usage: $0 <fichier.sql> <nom_db>"
    exit 1
fi

SQL_FILE="$1"
DB_NAME="$2"

if [ ! -f "$SQL_FILE" ]; then
    echo "Erreur : fichier '$SQL_FILE' introuvable."
    exit 1
fi

docker compose -f ../docker-compose.yml cp .mysql.cnf db:/etc/mysql/conf.d/mysqlpassword.cnf

docker compose -f ../docker-compose.yml exec -T db /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D "$DB_NAME" < "$SQL_FILE"

docker compose -f ../docker-compose.yml exec -T db rm /etc/mysql/conf.d/mysqlpassword.cnf

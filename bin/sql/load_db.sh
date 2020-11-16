#!/bin/bash

echo "creating wiki database"
mysql -u root --password=root -e "CREATE DATABASE IF NOT EXISTS wiki"

echo "creating insights database"
mysql -u root --password=root -e "CREATE DATABASE IF NOT EXISTS insights"
mysql -u root --password=root -e "GRANT ALL ON insights.* TO 'wiki'@'%'"

if [ -f wiki.sql ]; then
  echo "loading wiki"
  mysql -u root --password=root wiki < wiki.sql
fi

if [ -f wiki_prod.sql ]; then
  echo "loading prod wiki"
  mysql -u root --password=root -e "CREATE DATABASE IF NOT EXISTS wiki_prod"
  mysql -u root --password=root -e "GRANT ALL PRIVILEGES ON wiki_prod.* TO 'wiki'@'%'"
  mysql -u root --password=root wiki_prod < wiki_prod.sql
fi

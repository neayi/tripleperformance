
docker-compose run --rm -v /var/www/tripleperformance_docker/discourse:/pgpass:ro -e DISABLE_WELCOME_MESSAGE=yes -e PGPASSFILE=/pgpass/.pgpass postgresql pg_dump -F c -U postgres -h postgresql bitnami_discourse > discourse.sql.dump

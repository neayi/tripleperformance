
docker-compose run --rm -v ~/tripleperformance/discourse:/pgpass:ro -e DISABLE_WELCOME_MESSAGE=yes -e PGPASSFILE=/pgpass/.pgpass postgresql pg_restore --clean -U postgres -h postgresql /pgpass/discourse.sql

# Copilot Instructions for tripleperformance

## Project Overview
This repository manages the infrastructure and deployment scripts for the Triple Performance platform, including Docker environments, Apache/SQL configs, certificate generation, backups, and environment setup for dev, preprod, and prod. It also orchestrates integration with the `insights` service and MediaWiki.

## Architecture & Key Components
- **Docker-based**: All environments (dev, prod, preprod) are managed via Docker Compose files (`docker-compose*.yml`).
- **Engine**: Core server setup in `engine/php_server/` (Apache, PHP, msmtp, Traefik configs).
- **Config**: Main configuration files in `config/` (MediaWiki, robots, etc).
- **Backup**: Scripts for DB and file backups in `backup/`.
- **Bin/Tools**: Utility scripts for project build, error logs, and data manipulation.
- **Insights**: External service, cloned into `insights/`.

## Essential Workflows
- **Environment Setup**: Clone both `tripleperformance` and `insights`. Copy `.env` and override files from `.dist` or `.example` sources.
- **Certificate Generation**: Use `engine/traefik/dev/generate-certs.sh` and recreate Traefik container after changes.
- **Build & Launch**: Use `docker compose up --build -d` for all environments. For production, use `docker compose -f docker-compose.prod.yml up -d`.
- **Code Extraction**: Run `php wiki.php build_project.php --create-env` (or `--update`) to set up `/var/www/src` and symlink configs.
- **Database Setup**: Use `docker compose exec -w /var/sql db ./load_db.sh` or direct MySQL import commands.
- **Insights Install**: `docker compose run --rm --user="$UID:$GID" insights_php ./install.sh` and set permissions.
- **ElasticSearch Indexing**: `docker compose exec web php bin/build_project.php --initElasticSearch`.
- **MediaWiki Maintenance**: Use maintenance scripts in `/var/www/html/maintenance` via Docker commands.

## Conventions & Patterns
- **Config Files**: Always copy `.env` and override files from their `.dist` or `.example` sources before editing.
- **Logs**: All logs should be sent to STDOUT/STDERR, not files.
- **Service Access**: Use Docker Compose exec/run for shell access to containers (`web`, `db`, `insights_php`).
- **Secrets**: Store DB and ElasticSearch passwords in `.env` and `backup/.mysql.cnf`.
- **Image Management**: Images for the wiki are not versioned; coordinate with team for transfers.

## Integration Points
- **External Services**: ElasticSearch, phpMyAdmin, ElasticVue, Insights.
- **Traefik**: Handles SSL and routing; certs must be regenerated and containers recreated on changes.
- **MediaWiki**: Core wiki logic, extensions, and maintenance scripts.

## Debugging & VSCode
- **XDebug**: Pre-installed in dev; verify with `php -i`. Use Chrome XDebug Helper and set IDE key to `VSCODE`.

## Examples
- Build project: `php wiki.php build_project.php --create-env`
- Update code: `docker compose exec web php bin/build_project.php --update`
- DB import: `docker compose exec -T db mysql -u root --password=xxxxxx wiki < bin/sql/wiki.sql`
- Index wiki: `docker compose exec web php bin/build_project.php --initElasticSearch`

## Key Files & Directories
- `docker-compose*.yml` — Environment orchestration
- `engine/php_server/` — Server configs
- `config/` — Main configs
- `backup/` — Backup scripts
- `bin/` — Build and utility scripts
- `wiki.php` — Entrypoint for project scripts

---
_Review and update these instructions as workflows or architecture evolve. If any section is unclear or missing, please provide feedback for improvement._

# Deploy

> A small self-hosted deploy manager: signed GitHub webhooks in, Docker builds
> and `docker compose up` out — with a UI for pipelines, live logs and history.

Deploy is the middle ground between a raw webhook-to-bash script and a full
PaaS. It runs on the server it deploys to, builds images against the local
Docker daemon (warm layer cache, no registry required), and keeps a browsable
record of every deployment.

## What it does

- **Webhook receiver** — one endpoint per project, verified with a per-project
  secret. GitHub (`X-Hub-Signature-256`), Gitea (`X-Gitea-Signature`), GitLab
  (`X-Gitlab-Token`), and generic callers (`X-Deploy-Secret`) are supported;
  pings are answered and the payload repository must match the project.
- **Branch filtering** — workflows deploy the repository's default branch by
  default; pin them to a specific branch or `*` for all. Pushes no workflow
  cares about are ignored, not queued.
- **Workflows as steps** — a workflow maps an event (push) + branch to a
  target server and an ordered list of steps:
  - **Docker deploy**: shallow-clone the pushed repo, build
    `deploy/build.sh` → `Dockerfile.dist` → `Dockerfile`, tag `latest` **and
    the commit SHA**, optionally build a `docker/nginx.Dockerfile` companion
    image, then `docker compose -f <your compose file> up -d`.
  - **Script**: run shell commands you define.
  - **Script file**: execute an existing script on the server.
- **Targets** — steps run locally (Docker socket) or on a remote server over
  SSH; every SSH server gets its own generated Ed25519 keypair.
- **UI** — deployment history with per-step status, duration, exit codes and
  live ANSI-colored output; manual deploys (configurable branch); cancellation,
  **retry** (pinned to the failed run's commit), and **rollback** (instant
  image retag while the SHA image survives pruning, rebuild-from-commit after);
  a copyable GitHub Actions trigger snippet per project.
- **Queue-based** — deployments run on a worker with a per-project lock;
  superseded pending deployments are auto-canceled.
- **Housekeeping** — optional failure notifications (`DEPLOY_NOTIFY_URL` gets a
  JSON POST — ntfy, Slack, healthchecks.io, ...) and automatic retention
  pruning (`DEPLOY_RETENTION_DAYS`, default 100).

## Running it

The production image is published to GHCR and serves the app with FrankenPHP
on `:80`. It ships `git` and the Docker CLI so a worker container can execute
Docker deploy steps against a mounted socket.

```yaml
services:
  deploy:
    image: ghcr.io/alexanderglueck/deploy:latest
    restart: unless-stopped
    ports: ["8080:80"]        # or put it behind your reverse proxy / tunnel
    env_file: [deploy.env]
    volumes:
      - deploy-storage:/app/storage
      - deploy-db:/data       # SQLite lives here (see env below)

  worker:
    image: ghcr.io/alexanderglueck/deploy:latest
    command: php artisan queue:work --sleep=1 --max-time=3600
    restart: unless-stopped
    env_file: [deploy.env]
    volumes:
      - deploy-storage:/app/storage
      - deploy-db:/data
      # Root-equivalent: only add this if you use Docker deploy steps.
      - /var/run/docker.sock:/var/run/docker.sock

volumes:
  deploy-storage:
  deploy-db:
```

Minimal `deploy.env` (SQLite, no external database needed):

```env
APP_KEY=            # docker run --rm ghcr.io/alexanderglueck/deploy:latest php artisan key:generate --show
APP_URL=https://deploy.example.com
DB_CONNECTION=sqlite
DB_DATABASE=/data/deploy.sqlite
QUEUE_CONNECTION=database
AUTO_MIGRATE=1      # migrate on container start (updates = pull + restart)
DEPLOY_GIT_TOKEN=   # token for cloning private repos (contents:read)
DEPLOY_NOTIFY_URL=  # optional: JSON POST here when a deployment fails
```

`AUTO_MIGRATE=1` creates/updates the schema on start (`--isolated`, so app and
worker don't race). Then create your account:

```sh
docker compose exec deploy php artisan deploy:user
```

(Self-service registration stays disabled unless you set
`REGISTRATION_ENABLED=true`.)

### Backups

Back up **both** the database and your `APP_KEY`: server SSH keys and webhook
secrets are encrypted with the key, so a database backup without it is
unusable.

> **Security note:** anything that can trigger builds through the Docker
> socket is root-equivalent on the host. Keep the UI behind additional
> authentication (e.g. Cloudflare Access) and treat the worker container
> accordingly. Deployed apps run in their own containers.

## Development

```sh
docker compose up -d        # nginx + php-fpm (dev target) + worker + mariadb
npm run dev
vendor/bin/phpunit
```

## How a Docker deploy step finds things

| Convention | Default | Override |
|---|---|---|
| App name | repo name, `.` → `-` | step config `app` |
| Build file | `deploy/build.sh` → `Dockerfile.dist` → `Dockerfile` | — |
| Build target | none | step config `target` |
| Compose file | `DEPLOY_COMPOSE_FILE` pattern (`{app}` placeholder) | step config `compose_file` |
| Clone URL | `DEPLOY_GIT_BASE` + repository + `DEPLOY_GIT_TOKEN` | — |

## License

Copyright (C) 2026 Alexander Glück

Licensed under the [GNU Affero General Public License v3.0](LICENSE)
(AGPL-3.0-only). You can use, self-host, and modify it freely; if you run a
modified version as a network service, you must offer its source to that
service's users.

# Deploy

> A small self-hosted deploy manager: signed GitHub webhooks in, Docker builds
> and `docker compose up` out — with a UI for pipelines, live logs and history.

Deploy is the middle ground between a raw webhook-to-bash script and a full
PaaS. It runs on the server it deploys to, builds images against the local
Docker daemon (warm layer cache, no registry required), and keeps a browsable
record of every deployment.

## What it does

- **Webhook receiver** — one endpoint per project, verified with a per-project
  secret (GitHub `X-Hub-Signature-256` HMAC, or an `X-Deploy-Secret` header for
  generic callers). GitHub pings are answered, payload repository must match
  the project.
- **Workflows as steps** — a workflow maps an event (push) to a target server
  and an ordered list of steps:
  - **Docker deploy**: shallow-clone the pushed repo, build
    `deploy/build.sh` → `Dockerfile.dist` → `Dockerfile`, tag `latest` **and
    the commit SHA**, optionally build a `docker/nginx.Dockerfile` companion
    image, then `docker compose -f <your compose file> up -d`.
  - **Script**: run shell commands you define.
  - **Script file**: execute an existing script on the server.
- **Targets** — steps run locally (Docker socket) or on a remote server over
  SSH; every SSH server gets its own generated Ed25519 keypair.
- **UI** — deployment history with per-step status, duration, exit codes and
  live ANSI-colored output; manual deploys (configurable branch); cancellation;
  a copyable GitHub Actions trigger snippet per project.
- **Queue-based** — deployments run on a worker with a per-project lock;
  superseded pending deployments are auto-canceled.

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
REGISTRATION_ENABLED=true   # register your account, then set to false
DEPLOY_GIT_TOKEN=   # token for cloning private repos (contents:read)
```

`AUTO_MIGRATE=1` creates/updates the schema on start (`--isolated`, so app and
worker don't race). Register your user, then turn `REGISTRATION_ENABLED` back
off.

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

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
- **Docker dashboard** — per-server view of containers (grouped by compose
  stack, with state, health, why/when they exited, ports) and images, plus
  on-demand CPU/memory stats and daemon disk usage. Start/stop/restart/
  unpause/kill with a per-server audit trail of who ran what, and log tailing
  with time-window, tail-length and timestamp filters. Works on the local host
  and remote SSH servers through the same mechanism.
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
      # Powers the Docker dashboard for the local server. Root-equivalent —
      # only add it if you deploy to / manage this host, and keep the UI
      # behind authentication.
      - /var/run/docker.sock:/var/run/docker.sock

  worker:
    image: ghcr.io/alexanderglueck/deploy:latest
    command: php artisan queue:work --sleep=1 --max-time=3600
    restart: unless-stopped
    env_file: [deploy.env]
    volumes:
      - deploy-storage:/app/storage
      - deploy-db:/data
      # Root-equivalent: needed for Docker deploy steps on the local server.
      - /var/run/docker.sock:/var/run/docker.sock

  # Optional: realtime dashboard updates over websockets. Route your public
  # websocket hostname (REVERB_CLIENT_HOST) at this container; without it,
  # set BROADCAST_CONNECTION=null and the dashboard falls back to polling.
  reverb:
    image: ghcr.io/alexanderglueck/deploy:latest
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    restart: unless-stopped
    ports: ["8081:8080"]
    env_file: [deploy.env]
    volumes:
      - deploy-storage:/app/storage
      - deploy-db:/data

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

# Realtime dashboard updates (reverb service above); use BROADCAST_CONNECTION=null
# to skip websockets entirely — everything degrades to polling.
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=deploy
REVERB_APP_KEY=     # openssl rand -hex 24
REVERB_APP_SECRET=  # openssl rand -hex 24
REVERB_HOST=reverb  # where THIS APP delivers events (compose service name)
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_CLIENT_HOST=deploy-ws.example.com  # where BROWSERS connect
REVERB_CLIENT_PORT=443
REVERB_CLIENT_SCHEME=https
```

Pick a websocket hostname your certificate actually covers — Cloudflare's
universal certificate is a first-level wildcard only, so use a sibling
(`deploy-ws.example.com`), not a nested one (`ws.deploy.example.com`).
Alternatively serve the websocket on the app's own hostname by path: set
`REVERB_SERVER_PATH=/ws` and `REVERB_CLIENT_PATH=/ws` and route
`deploy.example.com/ws*` at the reverb container in your proxy.

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

> **Security note:** both containers mount the Docker socket (the worker for
> builds, the web app for the Docker dashboard), which is root-equivalent on
> the host. Keep the UI behind additional authentication (e.g. Cloudflare
> Access) — anyone who reaches it can control every container on the box.
> Deployed apps run in their own containers.

## Development

```sh
docker compose up -d        # nginx + php-fpm (dev target) + worker + mariadb
npm run dev
vendor/bin/phpunit
```

The dev containers ship no docker CLI and don't mount the docker socket, so a
"This server" (local) entry can't power the Docker dashboard or deploys in
development — register the docker host (or any other box) as an SSH server
instead. The production image has the CLI baked in.

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

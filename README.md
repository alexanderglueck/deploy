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
- **Management API** — the project screens as a scriptable `/api/v1`: register
  projects, give them workflows and steps, trigger deploys and poll their
  status with a token instead of a browser.
- **Project variables** — GitLab-style environment variables per project,
  exported into every step (and into `docker build` for the ones that need to
  reach a Vite build). Stored encrypted, never returned by the API, and masked
  out of deployment output.
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
# DEPLOY_GIT_TOKEN_USER=x-access-token   # oauth2 on GitLab
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

## Management API

Everything the project screens do, scriptable — so a fleet of projects can be
registered from a terminal instead of clicked through a browser. Authenticate
with a token from the UI's **API Tokens** screen:

```bash
curl -sS https://deploy.example.com/api/v1/projects \
    -H "Authorization: Bearer $DEPLOY_TOKEN" -H 'Accept: application/json'
```

Everything is scoped to the token owner's teams, records are addressed by their
public ULID, and anything belonging to another team answers `404` rather than
`403`, so a token cannot probe for identifiers it should not see. Servers are
not managed here — create them in the UI and pass their ULID. If the UI sits
behind an access proxy that rejects bearer tokens, call the API from inside the
network instead (`http://deploy/api/v1/...`).

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/v1/projects` | |
| `POST` | `/api/v1/projects` | returns `webhook_secret` **once** |
| `GET` `PATCH` | `/api/v1/projects/{project}` | |
| `POST` | `/api/v1/projects/{project}/deploy` | `{ref?, sha?}` → `202` and a `status_url` |
| `GET` `PUT` | `/api/v1/projects/{project}/variables` | environment variables; values are write-only |
| `DELETE` | `/api/v1/projects/{project}/variables/{variable}` | |
| `GET` | `/api/v1/projects/{project}/workflows` | steps included |
| `POST` | `/api/v1/projects/{project}/workflows` | `server` and `steps` required |
| `GET` `PATCH` `DELETE` | `/api/v1/projects/{project}/workflows/{workflow}` | |
| `GET` | `…/workflows/{workflow}/steps` | |
| `PUT` | `…/workflows/{workflow}/steps` | replaces the whole list |
| `POST` | `…/workflows/{workflow}/steps` | appends to it |
| `DELETE` | `…/workflows/{workflow}/steps/{step}` | |
| `GET` | `/api/v1/deployments/{deployment}` | `pending`, `deployed`, `failed` or `canceled` |

### From nothing to a deployed app

```bash
API=https://deploy.example.com/api/v1
AUTH="Authorization: Bearer $DEPLOY_TOKEN"
JSON='Content-Type: application/json'

# 1. Register it. webhook_secret comes back exactly once: together with
#    deploy_url it is what CI needs to trigger a deployment.
curl -sS -X POST "$API/projects" -H "$AUTH" -H "$JSON" \
    -d '{"name":"contacts","repository":"jondoe/contacts","default_branch":"master"}'

# 2. Give it a workflow -- PROJECT is the ulid from step 1, SERVER a server's
#    ulid from the UI. Without a workflow every push is accepted and ignored;
#    without steps it is accepted and then fails.
curl -sS -X POST "$API/projects/$PROJECT/workflows" -H "$AUTH" -H "$JSON" \
    -d '{"server":"'"$SERVER"'","steps":[{"type":"docker_deploy"}]}'

# 3. Deploy without waiting for a push, then poll the returned status_url
#    until it reports deployed or failed.
curl -sS -X POST "$API/projects/$PROJECT/deploy" -H "$AUTH" -H 'Accept: application/json'
```

### Steps

Steps are the only thing a deployment actually runs, so `steps` is required
when creating a workflow. Each is `{"type": …, "config": {…}}`; config keys the
type does not use are dropped rather than stored.

| Type | Config |
|---|---|
| `docker_deploy` | `app`, `compose_file`, `target` — all optional, the conventions below fill them in |
| `inline_script` | `script` (required) |
| `script_file` | `path` (required), `args` |

`PUT …/steps` replaces the list and renumbers from 1, which is also what the
editor's save does; `POST …/steps` appends.

### Variables

Environment variables defined on a project, in the spirit of GitLab's CI/CD
variables: exported into **every step of every deployment** it runs — script
steps, the app's own `deploy/build.sh`, and `docker compose up`, which
interpolates them into the compose file.

```bash
curl -sS -X PUT "$API/projects/$PROJECT/variables" -H "$AUTH" -H "$JSON" -d '{
  "variables": [
    {"key": "VITE_PUSHER_APP_KEY", "value": "pk_live_abc", "build_arg": true, "masked": false},
    {"key": "DB_PASSWORD",         "value": "hunter2"}
  ]
}'
```

Values are **write-only**: responses carry the key, `has_value` and the flags,
never the value. Submitting a variable with a blank value therefore keeps the
stored one, which is how a flag can be flipped without resending the secret. PUT
replaces the whole list, so anything absent is deleted.

| Flag | Meaning |
|---|---|
| `build_arg` | also passed to `docker build` as `--build-arg`. Required for `VITE_*`, because Vite inlines those *during* the image build and never sees an exported shell variable. Opt-in, because build args are recorded in the image's `docker history` — right for values that ship in the client bundle, wrong for a token. |
| `masked` (default) | the value is replaced with `[masked]` in stored deployment output and in failure notifications. Values shorter than 5 characters are not masked, as that would shred unrelated output. |

Two things worth knowing: a repository with its own `deploy/build.sh` controls
its `docker build` invocation, so the manager cannot add `--build-arg` there —
have the script forward what it needs (the variables are exported, so
`--build-arg VITE_X="$VITE_X"` works). And variables are read at deploy time,
not snapshotted, so retrying an old deployment uses today's values.

### Branch filtering and other fields

`branch` has three meanings: omitted or `null` deploys the repository's default
branch, `"*"` matches any branch, and anything else is an exact branch name —
responses spell the choice out as `branch_mode`. `event` is `push` on input,
while responses carry the stored integer constant.

A project whose repository is not on `DEPLOY_GIT_BASE` carries its own
`git_base`, `git_token_user` and `git_token`. The token is write-only: it is
stored encrypted, never returned, kept when a `PATCH` omits it, and cleared by
sending `null`. Responses report `has_git_token` instead.

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
| Clone URL | `DEPLOY_GIT_BASE` + repository + `DEPLOY_GIT_TOKEN`/`DEPLOY_GIT_TOKEN_USER` | project `git_base`, `git_token`, `git_token_user` |

## License

Copyright (C) 2026 Alexander Glück

Licensed under the [GNU Affero General Public License v3.0](LICENSE)
(AGPL-3.0-only). You can use, self-host, and modify it freely; if you run a
modified version as a network service, you must offer its source to that
service's users.

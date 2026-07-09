# Deploy App — Rework Plan

Turn the app from an SSH shell-runner into a self-hosted deploy manager: a webhook-driven
control plane that can **build & deploy Docker apps on the box it runs on** (replacing the
server repo's `deploy-app` hook) and **run scripts locally or on remote servers over SSH**
(preserving the old-server workflow). Shipped open source as a public GHCR image, wired into
the server repo as `tools/deploy/`.

## Target domain model

```
Team ─┬─ Project ── Workflow (event → server → steps) ── WorkflowStep
      │      └───── Deployment ── DeploymentStep (status, exit code, output)
      └─ Server (type: local | ssh, per-server keypair)
```

- **Project** — gains `repository` (e.g. `alex/lastfmreminder`, matched against the webhook
  payload) and `webhook_secret` (encrypted; per-project HMAC secret). Keeps `deploy_endpoint`
  as the URL route key — endpoint + HMAC is defense in depth.
- **Server** — gains `type` (`local` | `ssh`). A `local` row is seeded on install (no
  host/port/key; executes in-process). SSH servers get a **per-server ed25519 keypair**
  generated on create, private key stored with `encrypted` cast. The shared
  `storage/app/temp_id_rsa` is deleted. UI shows the public key for manual
  `authorized_keys` install; the existing password-based `copyPublicKey()` flow survives as a
  convenience but uses the per-server key.
- **Workflow** — keeps `(project_id, server_id, event)`; the single `actions` text column is
  replaced by ordered **WorkflowStep** rows: `(workflow_id, position, type, config json)`.
  Step types:
  1. `docker_deploy` — clone → build → compose up (the flagship; config: compose file path,
     build target, image name overrides — all defaulted by convention).
  2. `inline_script` — free-form shell stored in the DB (today's behavior, kept as escape
     hatch and for old-school SSH deploys).
  3. `script_file` — execute an existing file on the target (e.g.
     `/srv/server-config/deploy/update-server-config.sh`).
- **Deployment** — gains `commit_sha` and `failed_at` (state machine becomes
  pending → deploying → deployed / failed / canceled). Steps snapshot into
  **DeploymentStep** rows: `(deployment_id, position, type, config, status,
  exit_code, started_at, finished_at, output longtext)`. The separate `Log` model is
  absorbed by `DeploymentStep.output`; the UI renders steps as an accordion with per-step
  status, duration, and ANSI-colored output (existing `ansi_up` component reused).
- **Teams** stay (already built, good for the open-source story). Registration stays off by
  default.

## Webhook receiving

- `POST /api/deploy/{project:deploy_endpoint}` — parses real GitHub payloads: event from
  `X-GitHub-Event`, `ref`, `repository.full_name`, `head_commit.id`, default branch.
- **HMAC middleware**: verify `X-Hub-Signature-256` against the project's secret with
  `hash_equals`; 403 otherwise. No unauthenticated triggering — the secret URL alone is no
  longer sufficient.
- Non-GitHub callers (generic/manual): accept a shared-secret header (`X-Deploy-Secret`)
  as an alternative to HMAC, for curl-style triggers.
- The legacy `GET` endpoint is dropped (we're migrating everything anyway); the manual
  deploy button and cancel flow stay as-is.
- Superseded-deployment cancelation (already implemented) stays; add a **per-project lock**
  during execution (`WithoutOverlapping` job middleware keyed on project), mirroring
  `update-app.sh`'s flock.

## Execution layer

- `Executor` contract: `run(string $script, ?callable $onOutput): Result` with streaming
  output callback and exit code.
  - `LocalExecutor` — Laravel `Process` facade (streamed).
  - `SshExecutor` — refactor of the existing `App\SSH\Connection` (phpseclib), using the
    per-server key.
  - `FakeExecutor` — for tests; replaces the `ip == 'test'` hack in `ProcessDeployments`.
- Step handlers resolve config + executor and append output to their `DeploymentStep`:
  - **DockerDeployStep** (ports `update-app.sh` conventions to PHP):
    1. workdir `/tmp/build-{deployment-ulid}` (always cleaned up in `finally`);
    2. `git clone --depth 1 -b {branch}` using a server-side token (config/env; payload URLs
       are never trusted);
    3. build resolution: `deploy/build.sh` in the app repo → `Dockerfile.dist` → `Dockerfile`;
       tag `{app}:latest` **and** `{app}:{sha}` (SHA tags = cheap rollback later, the
       server repo notes.md TODO);
    4. optional `docker/nginx.Dockerfile` → `{app}-web:latest` (php-fpm convention);
    5. `docker compose -f {composePath} up -d` — default path
       `/srv/server-config/apps/{app}/compose.yml` (the opt-in convention stays **in git**,
       not in this app's DB);
    6. app name = repo name with `.` → `-`, overridable per project.
  - **InlineScriptStep / ScriptFileStep** — run the stored script / the referenced file via
    the workflow's target executor.
- **Queue**: `QUEUE_CONNECTION=database` (migrations already exist), dedicated worker
  service. `ProcessDeployment` job iterates steps sequentially, stops on first non-zero exit,
  gets try/catch + `failed()` handler that stamps `failed_at`. Timeout raised (builds can
  exceed the current 300s) and made configurable.

## Productionizing the app itself

- **Production image**: single **FrankenPHP** container serving the app (matches the server
  repo's `apps/example-laravel` convention) — replaces the fpm + external-nginx dev-only
  shape for production. Dev compose can stay fpm+nginx or move to FrankenPHP too, whatever is
  less churn.
- **Compose shape** (shipped in the repo for standalone users, mirrored in the server repo):
  - `app` — web UI + webhook receiver;
  - `worker` — same image, `php artisan queue:work` (the only service that strictly needs
    the Docker socket);
  - no separate scheduler container: the image-availability reconcile job (see Rollback
    section) runs via `php artisan schedule:work` inside the worker service (or as a
    self-rescheduling queued job) — keeps the compose at two services.
- **Database**: default standalone install to **SQLite on a volume** (zero-dependency for
  outside users); on Alex's server use the shared MariaDB over the `internal` network
  (`create-app-db.sh deploy`). Drop the MySQL 5.7 dev container +
  `Server::defaultStringLength(191)` workaround; dev DB becomes MariaDB (or SQLite).
- **Mounts on the server** (same trick the adnanh webhook container uses — host paths
  mirrored 1:1 so `docker compose -f /srv/server-config/...` resolves `env_file:` paths
  correctly through the socket):
  - `/var/run/docker.sock` (worker; root-equivalent — documented loudly),
  - `/srv/server-config:/srv/server-config:ro`,
  - `/srv/secrets:/srv/secrets:ro`.

## Server repo integration

- `tools/deploy/compose.yml`: `image: ghcr.io/{owner}/deploy:vX`, `app` + `worker`,
  networks `web` + `internal`, `env_file: /srv/secrets/deploy.env`, no host ports, tunnel
  route `deploy.{domain} → http://deploy:80` behind **Cloudflare Access**.
- `secrets.example/deploy.env.example` (APP_KEY, DB creds, git token).
- `scripts/up.sh`: pull + `compose up` (this is a *pulled infra image* like cloudflared or
  phpmyadmin — consistent with the repo's "no registry" decision, which is about *app*
  images built on-box).
- **Division of labor after migration**:
  - App repos' GitHub webhooks → the deploy app (HMAC per project). `deploy-app` hook is
    removed from `hooks.json.tmpl`.
  - `deploy-server-config` **stays on adnanh/webhook for now** — the thing that recreates
    the deploy app should be dumber than the deploy app. Absorbing it (via `script_file`
    step + the detached-helper self-recreate pattern from `up.sh`) is a later milestone.
  - adnanh/webhook remains documented in the server repo as the no-UI alternative for users
    who don't want the app.
- **Updating the deploy app itself**: bump the image tag in `tools/deploy/compose.yml`,
  push the server repo, `deploy-server-config` applies it. No self-update dance needed.

## CI / distribution

- Public GitHub repo. Actions: tests on PR; on version tag, build multi-arch
  (amd64 + arm64) production image → push to **public GHCR** (free storage/bandwidth).
- Versioned tags (`v1`, `v1.2.3`) + `latest`; server repo pins a version.

## UI work (Inertia/Vue, existing patterns)

- Deployment view: per-step accordion (status badge, duration, exit code, ANSI output),
  commit SHA linking to GitHub, keep the 3s `usePoll` auto-refresh (SSE/Reverb is a later
  nicety — polling already works and auto-starts/stops).
- Workflow editor: ordered step builder with type selector + per-type config forms.
- Server editor: local/ssh type, generated public key with copy button, "test connection".
- Project page: webhook setup helper (payload URL, secret, GitHub settings instructions).
- Later: read-only viewer for `/srv/backups/deploys/*.log` (adnanh logs) so infra applies
  get UI too.

## Security checklist

- HMAC (`hash_equals`) on all webhook traffic; per-project secrets, `encrypted` casts.
- Per-server ed25519 keys, encrypted at rest; shared `temp_id_rsa` removed.
- Clone tokens server-side only; never trust URLs from payloads.
- Docker socket confined to the worker; README documents that socket access is
  root-equivalent and recommends Cloudflare Access (or equivalent) in front of the UI.
- Registration disabled by default; first user via artisan command.

## Milestones

1. **M1 — Secure core** ✅ *(done 2026-07-07)*:
   POST+HMAC webhook, database queue + worker, Executor abstraction (Local/Ssh/Fake),
   per-server keys, `failed` state + error handling. Old behavior preserved via
   `inline_script`.
2. **M2 — Docker deploys**: WorkflowStep/DeploymentStep migrations + models,
   DockerDeployStep with `update-app.sh` conventions, per-step UI, step builder.
   Also: a copyable GitHub Actions workflow snippet on the project page (for repos
   that prefer an in-repo trigger file over a native webhook), and auto-run
   migrations on container start (entrypoint with `AUTO_MIGRATE`, `--isolated`
   so app/worker don't race) for seamless production updates.
   Pilot one real app end-to-end, then migrate all app repos' webhooks.
3. **M3 — Packaging**: FrankenPHP production image, SQLite default, GHCR Actions,
   `tools/deploy/` in the server repo, docs (standalone + server-repo installs), remove
   `deploy-app` from adnanh hooks.
4. **M4 — Polish** ✅ *(rollback + log viewer done 2026-07-08)*: rollback button
   (see below) and the adnanh log viewer (`DEPLOY_LEGACY_LOGS_PATH`) are done.
   **SSE log streaming: deliberately deferred** — FrankenPHP runs in classic mode,
   so each open SSE connection would pin a PHP process for its lifetime; the 3s
   Inertia polling (payload now bounded to the latest 25 deployments) is simpler
   and fine for a personal tool. Revisit only with Octane/worker mode or many
   concurrent viewers. **Absorbing `deploy-server-config` / retiring adnanh:
   deferred until the manager has run in production for a while** — the thing
   that recreates the deploy manager should stay dumber than the manager.
5. **M5 — Realtime** *(foundation + async actions done 2026-07-09)*: replace
   polling and synchronous container actions with **async jobs + Laravel
   Reverb** broadcasts. Reverb runs as its own container (`php artisan
   reverb:start` on the same image), so the FrankenPHP classic-mode constraint
   that killed SSE doesn't apply — websocket connections live in Reverb's
   event loop, not in PHP-FPM/Franken processes.
   - ✅ **Container actions are async**: the POST creates the
     `container_actions` row and returns immediately; `RunContainerAction`
     executes via **`dispatchAfterResponse()`** — deliberately NOT the queue
     worker, where a long image build would delay a restart by minutes, and
     the web process was going to be pinned for the same duration under the
     old synchronous flow anyway. Every transition
     (queued → running → ok/failed) is broadcast as `ContainerActionUpdated`
     on the private `team.{ulid}` channel; the UI disables the container's
     buttons while pending. Broadcast failures are swallowed — polling (still
     on) is the fallback, and installs without reverb (`BROADCAST_CONNECTION=null`)
     just never get the events. Echo config is shared at runtime via Inertia
     (`reverb` prop) so no VITE_ values are baked into the published image.
   - ⏳ **Log & deployment streaming**: follow `docker logs -f` / step output
     and broadcast chunks to replace the 3s log poll and shrink the dashboard
     poll. Needs a home that neither blocks the single queue worker nor pins
     a web process per viewer (candidate: reverb-adjacent artisan process or
     a dedicated lightweight container).
   - ⏳ **Deployment status realtime**: broadcast deployment/step transitions
     from the worker (cheap — events already exist as model updates) so the
     dashboard drops its 3s deployment poll.
   - ✅ **Infra**: `deploy-reverb` service in `tools/deploy/compose.yml` and a
     `reverb` service in the dev compose; tunnel route
     `ws.deploy.<domain> -> http://deploy-reverb:8080` **without** Cloudflare
     Access (a websocket handshake can't follow the Access redirect; private
     channels still authorize via `/broadcasting/auth` on the protected app);
     `REVERB_*` keys in `/srv/secrets/deploy.env`.

### Rollback vs. image pruning

The server repo's scheduler prunes unused images older than 7 days daily
(`scripts/prune-images.sh`), so SHA-tagged images from past deployments **will disappear**.
The app must not offer rollbacks to images that no longer exist:

- **Reconcile job**: a periodic queued job (worker has the socket) lists local images and
  stamps each deployment with image availability (`image_available_at` / cleared when
  gone). The UI only enables *instant* rollback (retag `{app}:{sha}` → `latest` +
  `compose up`) for deployments whose image is still present.
- **Verify at execution**: the rollback job re-checks `docker image inspect` right before
  retagging (the reconcile stamp can be stale — prune may have run since).
- **Degraded path**: for deployments whose image was pruned, offer **"Rollback (rebuild)"**
  — clone at the recorded `commit_sha` and run the normal build + deploy pipeline. Slower,
  but always available as long as the commit exists.
- **Optional server-repo tweak** (nice-to-have, not required): teach `prune-images.sh` to
  protect the last N SHA tags per app so the instant-rollback window is deliberate rather
  than incidental.

## Notes / risks

- The app currently sits on Laravel `^13` / PHP `^8.5` (bleeding edge). Fine to stay, but
  the GHCR image should build against a released PHP tag.
- Existing tests (PHPUnit attributes, `RefreshDatabase`) largely survive M1; the
  `ip == 'test'` bypass is replaced by `FakeExecutor`. New coverage needed for HMAC
  middleware, step state machine, and DockerDeployStep (with faked executor).
- Migrations should be written as **new** migrations on top of the existing chain (the app
  has a live install), with data backfill: existing `Workflow.actions` →
  one `inline_script` step; existing `Server` rows → `type: ssh`.

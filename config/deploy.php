<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds a single deployment may run before it is
    | aborted and marked as failed. Docker builds can take a while on a
    | cold cache, so this defaults generously.
    |
    */

    'timeout' => (int) env('DEPLOY_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Git
    |--------------------------------------------------------------------------
    |
    | Docker deploy steps clone the pushed repository from this base URL,
    | optionally authenticating with a token (required for private repos).
    | The clone URL is always built server-side from the project's repository
    | name — never from webhook payload URLs.
    |
    */

    'git_base' => rtrim(env('DEPLOY_GIT_BASE', 'https://github.com'), '/'),

    'git_token' => env('DEPLOY_GIT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Compose file convention
    |--------------------------------------------------------------------------
    |
    | Where a Docker deploy step looks for an app's compose file when the
    | workflow doesn't specify one. `{app}` is replaced with the app name
    | (the repository name with dots turned into dashes).
    |
    */

    'compose_file_pattern' => env('DEPLOY_COMPOSE_FILE', '/srv/server-config/apps/{app}/compose.yml'),

    /*
    |--------------------------------------------------------------------------
    | Failure notifications
    |--------------------------------------------------------------------------
    |
    | When set, a JSON POST is sent to this URL every time a deployment
    | fails. Works with ntfy, Slack/Discord webhooks, healthchecks.io, or
    | anything else that accepts a POST. Leave empty to disable.
    |
    */

    'notify_url' => env('DEPLOY_NOTIFY_URL'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Concluded deployments (and their step logs) older than this many days
    | are pruned. Pruning runs opportunistically after webhook deployments
    | (at most once a day) and via `php artisan deploy:prune`.
    |
    */

    'retention_days' => (int) env('DEPLOY_RETENTION_DAYS', 100),

    /*
    |--------------------------------------------------------------------------
    | Legacy deploy logs
    |--------------------------------------------------------------------------
    |
    | Directory of *.log files written by an external deploy hook (e.g. the
    | server repo's adnanh webhook to /srv/backups/deploys). When set (and
    | mounted read-only into the container), the UI offers a read-only
    | viewer. Leave empty to hide the feature.
    |
    */

    'legacy_logs_path' => env('DEPLOY_LEGACY_LOGS_PATH'),

];

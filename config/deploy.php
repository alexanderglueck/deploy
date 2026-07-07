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

];

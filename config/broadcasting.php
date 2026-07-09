<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. Set to "reverb" to get
    | realtime dashboard updates (requires a running reverb container);
    | with "null" everything degrades to Inertia polling.
    |
    | Supported: "reverb", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | The reverb connection carries two addresses: `options` is where THIS APP
    | delivers events (the reverb container, reachable on the compose
    | network), and `client` is where BROWSERS connect (the public websocket
    | hostname routed to the reverb container). The client block is shared
    | with the frontend at runtime via Inertia — nothing is baked into the
    | built assets, so one image works for every install.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
            'client' => [
                'host' => env('REVERB_CLIENT_HOST'),
                'port' => (int) env('REVERB_CLIENT_PORT', 443),
                'scheme' => env('REVERB_CLIENT_SCHEME', 'https'),
                // Optional path prefix (e.g. "/ws") for serving the websocket
                // on the app's own hostname via path routing instead of a
                // dedicated hostname. Must match REVERB_SERVER_PATH.
                'path' => env('REVERB_CLIENT_PATH', ''),
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];

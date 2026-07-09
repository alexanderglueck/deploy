import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// One lazily created Echo connection for the whole app. The connection
// details come from the `reverb` Inertia prop (shared at runtime by the
// backend — nothing is baked into the build), so installs without a reverb
// container simply get `null` here and the pages keep polling.
let echo = null;

export function echoClient(config) {
    if (echo) {
        return echo;
    }

    if (!config?.key || !config?.host) {
        return null;
    }

    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        wsPath: config.path || '',
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return echo;
}

<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Shows the legacy-logs nav item when the viewer is configured.
            'legacyLogsEnabled' => (bool) config('deploy.legacy_logs_path'),
            // Websocket connection details for Echo, shared at runtime so the
            // published image needs no baked-in VITE_ values. Null (no reverb
            // configured) means the frontend sticks to polling.
            'reverb' => $this->reverbClient(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reverbClient(): ?array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return null;
        }

        $connection = config('broadcasting.connections.reverb');

        if (! $connection['key'] || ! $connection['client']['host']) {
            return null;
        }

        return [
            'key' => $connection['key'],
            'host' => $connection['client']['host'],
            'port' => $connection['client']['port'],
            'scheme' => $connection['client']['scheme'],
            'path' => $connection['client']['path'],
        ];
    }
}

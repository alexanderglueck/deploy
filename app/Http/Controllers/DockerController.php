<?php

namespace App\Http\Controllers;

use App\Docker\DockerClient;
use App\Models\ContainerAction;
use App\Models\Server;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DockerController extends Controller
{
    /**
     * Docker dashboard: containers and images for the selected server. The
     * server switcher passes ?server=<ulid>; an unknown or missing ulid
     * falls back to the first server.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $servers = $team->servers;

        $selected = ($request->query('server')
            ? $servers->firstWhere('ulid', $request->query('server'))
            : null) ?? $servers->first();

        $containers = [];
        $images = [];
        $error = null;

        if ($selected) {
            try {
                $docker = DockerClient::forServer($selected);
                $containers = $docker->containers();
                $images = $docker->images();
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('Docker/Index', [
            'servers' => $servers,
            'server' => $selected,
            'containers' => $containers,
            'images' => $images,
            'actions' => $selected ? $this->recentActions($selected) : [],
            'error' => $error,
            // Optional: only computed when the stats toggle requests it —
            // `docker stats --no-stream` blocks ~1.5s for its sample.
            'stats' => Inertia::optional(fn () => $selected ? $this->stats($selected) : null),
        ]);
    }

    /**
     * The latest dashboard actions run against this server's containers.
     */
    private function recentActions(Server $server)
    {
        return ContainerAction::where('server_id', $server->id)
            ->with('user:id,name')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /**
     * Per-container resource usage plus daemon disk usage. Failures stay
     * inside the prop — the tables are already rendered at this point.
     *
     * @return array<string, mixed>
     */
    private function stats(Server $server): array
    {
        try {
            $docker = DockerClient::forServer($server);

            return [
                'containers' => $docker->stats(),
                'disk' => $docker->diskUsage(),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return ['containers' => [], 'disk' => [], 'error' => $e->getMessage()];
        }
    }
}

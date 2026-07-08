<?php

namespace App\Http\Controllers;

use App\Docker\DockerClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DockerController extends Controller
{
    /**
     * Docker dashboard: containers and images for the selected server. The
     * server switcher passes ?server=<ulid>; defaults to the first server.
     */
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $servers = $team->servers;

        $selected = $request->query('server')
            ? $servers->firstWhere('ulid', $request->query('server'))
            : $servers->first();

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
            'error' => $error,
        ]);
    }
}

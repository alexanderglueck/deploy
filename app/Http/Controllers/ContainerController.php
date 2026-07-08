<?php

namespace App\Http\Controllers;

use App\Docker\DockerClient;
use App\Models\Server;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ContainerController extends Controller
{
    public function show(Request $request, Server $server, string $name): Response
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        $container = null;
        $error = null;

        try {
            $container = DockerClient::forServer($server)->inspect($name);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Docker/Container', [
            'server' => $server,
            'name' => $name,
            'container' => $container,
            'error' => $error,
        ]);
    }
}

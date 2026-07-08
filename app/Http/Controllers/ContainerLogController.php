<?php

namespace App\Http\Controllers;

use App\Docker\DockerClient;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ContainerLogController extends Controller
{
    /**
     * A container's log tail, fetched on demand and polled by the detail
     * page (true streaming is deferred — see PLAN.md).
     */
    public function show(Request $request, Server $server, string $name): JsonResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        try {
            $logs = DockerClient::forServer($server)->logs($name, (int) $request->query('tail', 500));
        } catch (Throwable $e) {
            $logs = $e->getMessage();
        }

        return response()->json(['logs' => $logs]);
    }
}

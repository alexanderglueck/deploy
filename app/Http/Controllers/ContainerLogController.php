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
     * page (true streaming is deferred — see PLAN.md). Failures come back
     * under `error` so the UI doesn't render them as log content.
     */
    public function show(Request $request, Server $server, string $name): JsonResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        try {
            $logs = DockerClient::forServer($server)->logs(
                $name,
                tail: (int) $request->query('tail', 500),
                timestamps: $request->boolean('timestamps', true),
                since: $request->query('since'),
            );

            return response()->json(['logs' => $logs, 'error' => null]);
        } catch (Throwable $e) {
            return response()->json(['logs' => '', 'error' => $e->getMessage()]);
        }
    }
}

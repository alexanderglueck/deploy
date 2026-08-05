<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only deployment status for the management API, so a script that triggered
 * a deploy can poll for the outcome instead of watching the UI.
 */
class DeploymentController extends Controller
{
    public function show(Request $request, Deployment $deployment): JsonResponse
    {
        $teamIds = $request->user()->allTeams()->pluck('id')->all();
        abort_unless(in_array($deployment->project->team_id, $teamIds, true), 404);

        return response()->json([
            'data' => [
                'ulid' => $deployment->ulid,
                'project' => $deployment->project->name,
                'ref' => $deployment->ref,
                'repository' => $deployment->repository,
                'commit_sha' => $deployment->commit_sha,
                'triggered_by' => $deployment->triggered_by_name,
                'status' => $this->status($deployment),
                'received_at' => optional($deployment->received_at)->toIso8601String(),
                'processed_at' => optional($deployment->processed_at)->toIso8601String(),
                'deployed_at' => optional($deployment->deployed_at)->toIso8601String(),
                'canceled_at' => optional($deployment->canceled_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Derived rather than stored: the model already carries the timestamps that
     * define the outcome, and duplicating them into a status column would be one
     * more thing to keep in step.
     */
    private function status(Deployment $deployment): string
    {
        return match (true) {
            $deployment->canceled_at !== null => 'canceled',
            $deployment->deployed_at !== null => 'deployed',
            $deployment->processed_at !== null => 'failed',
            default => 'pending',
        };
    }
}

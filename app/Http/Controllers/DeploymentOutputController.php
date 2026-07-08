<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentOutputController extends Controller
{
    /**
     * The output of a finished deployment, fetched on demand when its
     * details are expanded — it is stripped from the recurring page payload.
     */
    public function show(Request $request, Project $project, Deployment $deployment): JsonResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $deployment->load(['steps', 'log']);

        return response()->json([
            'steps' => (object) $deployment->steps
                ->mapWithKeys(fn (DeploymentStep $step) => [$step->ulid => $step->output])
                ->all(),
            'log' => $deployment->log?->log,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDeployments;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Project;
use App\Support\StepType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeploymentRetryController extends Controller
{
    /**
     * Re-run a failed deployment as a new one. The original's snapshotted
     * steps are replayed, with docker builds pinned to the recorded commit —
     * a retry deploys what the failed run tried to deploy, not whatever the
     * branch points at by now.
     */
    public function store(Request $request, Project $project, Deployment $deployment): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        abort_unless($deployment->isFailed(), 422, 'Only failed deployments can be retried.');

        $retry = Deployment::create([
            'project_id' => $project->id,
            'event' => $deployment->event,
            'ref' => $deployment->ref,
            'default_branch' => $deployment->default_branch,
            'repository' => $deployment->repository,
            'commit_sha' => $deployment->commit_sha,
            'retry_of_id' => $deployment->id,
            'triggered_by' => $request->user()->id,
            'received_at' => Carbon::now(),
        ]);

        // Copy the snapshotted steps; a deployment that failed before its
        // steps were created (e.g. no matching workflow) retries through the
        // normal workflow snapshot instead.
        $deployment->steps->each(function (DeploymentStep $step) use ($retry, $deployment) {
            $config = $step->config ?? [];

            if ($step->type === StepType::DOCKER_DEPLOY && $deployment->commit_sha) {
                $config['checkout_sha'] = true;
            }

            $retry->steps()->create([
                'position' => $step->position,
                'type' => $step->type,
                'config' => $config,
                'status' => DeploymentStep::STATUS_PENDING,
            ]);
        });

        ProcessDeployments::dispatch($retry);

        return redirect()->route('project.show', $project);
    }
}

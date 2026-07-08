<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDeployments;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Project;
use App\Steps\DockerDeployScript;
use App\Support\StepType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeploymentRollbackController extends Controller
{
    /**
     * Roll back to a previous deployment: instantly (retag its still-present
     * SHA image) or, when the image was pruned, by rebuilding the recorded
     * commit from scratch.
     */
    public function store(Request $request, Project $project, Deployment $deployment): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        abort_unless($deployment->isDeployed(), 422, 'Only deployed deployments can be rolled back to.');

        $dockerStep = $deployment->steps()->where('type', StepType::DOCKER_DEPLOY)->first();

        abort_if(! $dockerStep || ! $deployment->commit_sha, 422, 'This deployment has nothing to roll back to.');

        $config = $dockerStep->config ?? [];

        $rollback = Deployment::create([
            'project_id' => $project->id,
            'event' => $deployment->event,
            'ref' => $deployment->ref,
            'repository' => $deployment->repository,
            'commit_sha' => $deployment->commit_sha,
            'rollback_of_id' => $deployment->id,
            'received_at' => Carbon::now(),
        ]);

        if ($deployment->hasAvailableImage()) {
            // Instant: retag the existing image. The script re-verifies the
            // image at execution time; the availability stamp can be stale.
            $rollback->steps()->create([
                'position' => 1,
                'type' => StepType::DOCKER_ROLLBACK,
                'config' => [
                    'app' => DockerDeployScript::appName($deployment, $config),
                    'sha' => $deployment->commit_sha,
                    'compose_file' => DockerDeployScript::composeFile($deployment, $config),
                ],
                'status' => DeploymentStep::STATUS_PENDING,
            ]);
        } else {
            // Rebuild: replay the original deployment's snapshotted steps,
            // pinning docker builds to the recorded commit.
            $deployment->steps->each(function (DeploymentStep $step) use ($rollback) {
                $config = $step->config ?? [];

                if ($step->type === StepType::DOCKER_DEPLOY) {
                    $config['checkout_sha'] = true;
                }

                $rollback->steps()->create([
                    'position' => $step->position,
                    'type' => $step->type,
                    'config' => $config,
                    'status' => DeploymentStep::STATUS_PENDING,
                ]);
            });
        }

        ProcessDeployments::dispatch($rollback);

        return redirect()->route('project.show', $project);
    }
}

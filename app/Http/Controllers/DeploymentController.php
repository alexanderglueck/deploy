<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDeployments;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\Team;
use App\Support\Event;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project->team);

        $data = [
            'project_id' => $project->id,
            'event' => Event::PUSH,
            'ref' => 'manual_deploy',
            'repository' => 'manual_deploy',
            'received_at' => Carbon::now(),
        ];

        // Cancel older pending deployments
        Deployment::query()
            ->where([
                'project_id' => $data['project_id'],
                'ref' => $data['ref'],
                'event' => $data['event'],
                'repository' => $data['repository'],
            ])
            ->whereNull('processed_at')
            ->whereNull('deployed_at')
            ->whereNull('canceled_at')
            ->update([
                'canceled_at' => Carbon::now(),
            ]);

        // Queue new pending deployment
        ProcessDeployments::dispatch(Deployment::create($data));

        return redirect()->route('project.show', [$project->team, $project]);
    }

    /**
     * Cancel a deployment that is still queued or running.
     *
     * For a pending deployment this also prevents the queued job from doing any
     * work: ProcessDeployments checks isCanceled() before it starts. A job that
     * is already mid-run can't be interrupted, but it will be marked canceled.
     */
    public function cancel(Request $request, Team $team, Project $project, Deployment $deployment): RedirectResponse
    {
        $this->authorize('view', $team);

        if ($deployment->isActive()) {
            $deployment->update([
                'canceled_at' => Carbon::now(),
            ]);
        }

        return redirect()->route('project.show', [$team, $project]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDeployments;
use App\Models\Deployment;
use App\Models\Project;
use App\Support\Event;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
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
}

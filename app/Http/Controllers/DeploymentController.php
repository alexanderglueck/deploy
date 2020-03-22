<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPendingDeployments;
use App\PendingDeployment;
use App\Project;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validatedRequest = $request->validate([
            'event' => 'required',
            'ref' => 'required',
            'repo' => 'required'
        ]);

        $data = [
            'project_id' => $project->id,
            'event' => $validatedRequest['event'],
            'ref' => $validatedRequest['ref'],
            'repository' => $validatedRequest['repo']
        ];

        // Delete older pending deployments
        PendingDeployment::query()
            ->where($data)
            ->whereNull('processed_at')
            ->delete();

        // Queue new pending deployment
        ProcessPendingDeployments::dispatch(PendingDeployment::create($data));

        return "OK";
    }
}

<?php

namespace App\Http\Controllers;

use App\Deployment;
use App\Event;
use App\Jobs\ProcessDeployments;
use App\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ApiDeploymentController extends Controller
{
    /**
     * @param Request $request
     * @param Project $project
     * @return string
     */
    public function store(Request $request, Project $project)
    {
        $validatedRequest = $request->validate([
            'event' => 'required',
            'ref' => 'required',
            'repo' => 'required'
        ]);

        $data = [
            'project_id' => $project->id,
            'event' => Event::getEvent($validatedRequest['event']),
            'ref' => $validatedRequest['ref'],
            'repository' => $validatedRequest['repo'],
            'received_at' => Carbon::now()
        ];

        // Cancel older pending deployments
        Deployment::query()
            ->where([
                'project_id' => $data['project_id'],
                'ref' => $data['ref'],
                'event' => $data['event'],
                'repository' => $data['repository']
            ])
            ->whereNull('processed_at')
            ->whereNull('deployed_at')
            ->whereNull('canceled_at')
            ->update([
                'canceled_at' => Carbon::now()
            ]);

        // Queue new pending deployment
        ProcessDeployments::dispatch(Deployment::create($data));

        return "OK";
    }
}

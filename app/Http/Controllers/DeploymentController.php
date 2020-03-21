<?php

namespace App\Http\Controllers;

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

        return PendingDeployment::create([
            'project_id' => $project->id,
            'event' => $validatedRequest['event'],
            'ref' => $validatedRequest['ref'],
            'repository' => $validatedRequest['repo']
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Project;
use App\Team;
use App\Workflow;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function show(Request $request, Team $team, Project $project, Workflow $workflow)
    {
        return view('workflow.show', [
            'team' => $team,
            'project' => $project,
            'workflow' => $workflow
        ]);
    }

    public function create(Request $request, Team $team, Project $project)
    {
        return view('workflow.create', [
            'team' => $team,
            'project' => $project,
            'servers' => $team->servers,
            'workflow' => new Workflow
        ]);
    }

    public function store(Request $request, Team $team, Project $project)
    {
        $validated = $request->validate([
            'event' => 'required',
            'actions' => 'required',
            'server_id' => 'required'
        ]);

        $project->workflows()->create($validated);

        return redirect()->route('project.show', [$team, $project]);
    }
}

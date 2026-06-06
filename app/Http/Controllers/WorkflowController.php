<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Team;
use App\Models\Workflow;
use App\Support\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function show(Request $request, Team $team, Project $project, Workflow $workflow): Response
    {
        $workflow->load('server');

        return Inertia::render('Workflow/Show', [
            'team' => $team,
            'project' => $project,
            'workflow' => $workflow,
            'eventLabel' => Event::label($workflow->event),
        ]);
    }

    public function create(Request $request, Team $team, Project $project): Response
    {
        return Inertia::render('Workflow/Create', [
            'team' => $team,
            'project' => $project,
            'servers' => $team->servers,
            'events' => Event::options(),
        ]);
    }

    public function store(Request $request, Team $team, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'event' => 'required',
            'actions' => 'required',
            'server_id' => 'required',
        ]);

        $project->workflows()->create($validated);

        return redirect()->route('project.show', [$team, $project]);
    }

    public function destroy(Request $request, Team $team, Project $project, Workflow $workflow): RedirectResponse
    {
        $workflow->delete();

        return redirect()->route('project.show', [$team, $project]);
    }

    public function edit(Request $request, Team $team, Project $project, Workflow $workflow): Response
    {
        return Inertia::render('Workflow/Edit', [
            'team' => $team,
            'project' => $project,
            'servers' => $team->servers,
            'workflow' => $workflow,
            'events' => Event::options(),
        ]);
    }

    public function update(Request $request, Team $team, Project $project, Workflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'event' => 'required',
            'actions' => 'required',
            'server_id' => 'required',
        ]);

        $workflow->update($validated);

        return redirect()->route('project.show', [$team, $project]);
    }
}

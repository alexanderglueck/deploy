<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Workflow;
use App\Support\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function show(Request $request, Project $project, Workflow $workflow): Response
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $workflow->load('server');

        return Inertia::render('Workflow/Show', [
            'project' => $project,
            'workflow' => $workflow,
            'eventLabel' => Event::label($workflow->event),
        ]);
    }

    public function create(Request $request, Project $project): Response
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        return Inertia::render('Workflow/Create', [
            'project' => $project,
            'servers' => $team->servers,
            'events' => Event::options(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $validated = $request->validate([
            'event' => 'required',
            'actions' => 'required',
            'server' => 'required',
        ]);

        $server = $team->servers()->where('ulid', $validated['server'])->firstOrFail();

        $project->workflows()->create([
            'event' => $validated['event'],
            'actions' => $validated['actions'],
            'server_id' => $server->id,
        ]);

        return redirect()->route('project.show', $project);
    }

    public function destroy(Request $request, Project $project, Workflow $workflow): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $workflow->delete();

        return redirect()->route('project.show', $project);
    }

    public function edit(Request $request, Project $project, Workflow $workflow): Response
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $workflow->load('server');

        return Inertia::render('Workflow/Edit', [
            'project' => $project,
            'servers' => $team->servers,
            'workflow' => $workflow,
            'events' => Event::options(),
        ]);
    }

    public function update(Request $request, Project $project, Workflow $workflow): RedirectResponse
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $validated = $request->validate([
            'event' => 'required',
            'actions' => 'required',
            'server' => 'required',
        ]);

        $server = $team->servers()->where('ulid', $validated['server'])->firstOrFail();

        $workflow->update([
            'event' => $validated['event'],
            'actions' => $validated['actions'],
            'server_id' => $server->id,
        ]);

        return redirect()->route('project.show', $project);
    }
}

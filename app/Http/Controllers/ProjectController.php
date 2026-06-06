<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        return Inertia::render('Project/Show', [
            'project' => $project,
            'workflows' => $project->workflows()->with('server')->get(),
            'deployments' => $project->deployments()->with('log')->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->currentTeam($request);

        return Inertia::render('Project/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $validated = $request->validate([
            'name' => 'required',
        ]);

        $team->projects()->create($validated);

        return redirect()->route('team.show');
    }
}

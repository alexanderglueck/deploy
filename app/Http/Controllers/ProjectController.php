<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Request $request, Team $team, Project $project): Response
    {
        return Inertia::render('Project/Show', [
            'team' => $team,
            'project' => $project,
            'workflows' => $project->workflows()->with('server')->get(),
            'deployments' => $project->deployments()->with('log')->get(),
        ]);
    }

    public function create(Request $request, Team $team): Response
    {
        return Inertia::render('Project/Create', [
            'team' => $team,
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required',
        ]);

        $team->projects()->create($validated);

        return redirect()->route('team.show', [$team]);
    }
}

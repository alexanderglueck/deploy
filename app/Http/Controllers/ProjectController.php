<?php

namespace App\Http\Controllers;

use App\Project;
use App\Team;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function show(Request $request, Team $team, Project $project)
    {
        return view('project.show', [
            'team' => $team,
            'project' => $project
        ]);
    }

    public function create(Request $request, Team $team)
    {
        return view('project.create', [
            'team' => $team,
            'project' => new Project
        ]);
    }

    public function store(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required'
        ]);

        $team->projects()->create($validated);

        return redirect()->route('team.show', [$team]);
    }
}

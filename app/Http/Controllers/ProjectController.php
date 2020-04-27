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
}

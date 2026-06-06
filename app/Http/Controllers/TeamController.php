<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function show(Request $request, Team $team): Response
    {
        $this->authorize('view', $team);

        return Inertia::render('Team/Show', [
            'team' => $team,
            'servers' => $team->servers,
            'projects' => $team->projects,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function show(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('Team/Show', [
            'team' => $team,
            'servers' => $team->servers,
            'projects' => $team->projects,
        ]);
    }
}

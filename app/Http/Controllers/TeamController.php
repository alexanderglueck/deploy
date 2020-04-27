<?php

namespace App\Http\Controllers;

use App\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function show(Request $request, Team $team)
    {
        return view('team.show', [
            'team' => $team,
            'servers' => $team->servers,
            'projects' => $team->projects
        ]);
    }
}

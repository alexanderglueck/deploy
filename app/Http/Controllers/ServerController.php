<?php

namespace App\Http\Controllers;

use App\Server;
use App\Team;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index(Request $request, Team $team)
    {
        return view('server.index', [
            'team' => $team,
            'servers' => $team->servers
        ]);
    }

    public function show(Request $request, Team $team, Server $server)
    {
        return view('server.show', [
            'team' => $team,
            'server' => $server
        ]);
    }
}

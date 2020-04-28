<?php

namespace App\Http\Controllers;

use App\Project;
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

    public function create(Request $request, Team $team)
    {
        return view('server.create', [
            'team' => $team,
            'server' => new Server
        ]);
    }

    public function store(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required',
            'user' => 'required',
            'ip' => 'required|ipv4',
            'port' => 'required|integer',
        ]);

        $server = $team->servers()->create($validated);

        return redirect()->route('server.show', [$team, $server]);
    }
}

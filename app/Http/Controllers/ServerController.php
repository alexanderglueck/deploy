<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function index(Request $request, Team $team): Response
    {
        return Inertia::render('Server/Index', [
            'team' => $team,
            'servers' => $team->servers,
        ]);
    }

    public function show(Request $request, Team $team, Server $server): Response
    {
        return Inertia::render('Server/Show', [
            'team' => $team,
            'server' => $server,
            'isSetUp' => $server->isSetUp(),
        ]);
    }

    public function create(Request $request, Team $team): Response
    {
        return Inertia::render('Server/Create', [
            'team' => $team,
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
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

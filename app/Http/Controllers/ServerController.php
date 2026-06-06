<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);

        return Inertia::render('Server/Index', [
            'team' => $team,
            'servers' => $team->servers,
        ]);
    }

    public function show(Request $request, Server $server): Response
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        return Inertia::render('Server/Show', [
            'server' => $server,
            'isSetUp' => $server->isSetUp(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->currentTeam($request);

        return Inertia::render('Server/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $validated = $request->validate([
            'name' => 'required',
            'user' => 'required',
            'ip' => 'required|ipv4',
            'port' => 'required|integer',
        ]);

        $server = $team->servers()->create($validated);

        return redirect()->route('server.show', $server);
    }
}

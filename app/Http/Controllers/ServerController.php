<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'type' => ['required', Rule::in([Server::TYPE_LOCAL, Server::TYPE_SSH])],
            'user' => 'required_if:type,'.Server::TYPE_SSH,
            'ip' => ['required_if:type,'.Server::TYPE_SSH, 'nullable', 'ipv4'],
            'port' => ['required_if:type,'.Server::TYPE_SSH, 'nullable', 'integer'],
        ]);

        if ($validated['type'] === Server::TYPE_LOCAL) {
            // Local servers execute on this host directly — nothing to set up.
            $validated = [
                'name' => $validated['name'],
                'type' => Server::TYPE_LOCAL,
                'setup_at' => Carbon::now(),
            ];
        }

        $server = $team->servers()->create($validated);

        return redirect()->route('server.show', $server);
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        $validated = $request->validate($server->isLocal() ? [
            'name' => 'required',
        ] : [
            'name' => 'required',
            'user' => 'required',
            'ip' => 'required|ipv4',
            'port' => 'required|integer',
        ]);

        $server->update($validated);

        return redirect()->route('server.show', $server)->banner('Server updated.');
    }

    public function destroy(Request $request, Server $server): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        // Deleting would cascade the workflows away silently; make the user
        // untangle them first.
        if ($server->workflows()->exists()) {
            return redirect()->route('server.show', $server)
                ->withErrors(['server' => 'This server is still used by workflows. Delete or repoint them first.']);
        }

        $server->delete();

        return redirect()->route('server.index')->banner('Server deleted.');
    }
}

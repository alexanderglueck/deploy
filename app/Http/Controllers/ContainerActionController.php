<?php

namespace App\Http\Controllers;

use App\Docker\DockerClient;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContainerActionController extends Controller
{
    private const PAST_TENSE = [
        'start' => 'Started',
        'stop' => 'Stopped',
        'restart' => 'Restarted',
    ];

    public function store(Request $request, Server $server, string $name): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        $validated = $request->validate([
            'action' => ['required', Rule::in(DockerClient::ACTIONS)],
        ]);

        $docker = DockerClient::forServer($server);

        // The name comes from the client; only act on a container the server
        // actually reports.
        $exists = collect($docker->containers())->contains(fn (array $c) => $c['name'] === $name);
        abort_unless($exists, 404);

        $result = $docker->action($validated['action'], $name);

        if ($result->failed()) {
            return back()->withErrors(['docker' => trim($result->output) ?: 'The action failed.']);
        }

        return back()->banner(self::PAST_TENSE[$validated['action']]." {$name}.");
    }
}

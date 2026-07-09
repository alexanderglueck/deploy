<?php

namespace App\Http\Controllers;

use App\Docker\DockerClient;
use App\Models\ContainerAction;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ContainerActionController extends Controller
{
    private const PAST_TENSE = [
        'start' => 'Started',
        'stop' => 'Stopped',
        'restart' => 'Restarted',
        'unpause' => 'Unpaused',
        'kill' => 'Killed',
    ];

    public function store(Request $request, Server $server, string $name): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        $validated = $request->validate([
            'action' => ['required', Rule::in(DockerClient::ACTIONS)],
        ]);
        $action = $validated['action'];

        // Stop/restart wait out the container's stop grace period, which can
        // exceed the interactive read timeout.
        $docker = DockerClient::forServer($server, timeout: DockerClient::ACTION_TIMEOUT);

        // The name comes from the client; only act on a container the server
        // actually reports. An unreachable daemon is a flash error, not a 500.
        try {
            $exists = collect($docker->containers())->contains(fn (array $c) => $c['name'] === $name);
        } catch (Throwable $e) {
            return back()->dangerBanner("Docker on {$server->name} is unreachable: ".$e->getMessage());
        }

        abort_unless($exists, 404);

        try {
            $result = $docker->action($action, $name);
        } catch (Throwable $e) {
            // Timeouts land here too — the docker CLI was killed, but the
            // daemon finishes the action on its own.
            $this->record($request, $server, $name, $action, false, $e->getMessage());

            return back()->dangerBanner(ucfirst($action)." {$name} failed: ".$e->getMessage());
        }

        $this->record($request, $server, $name, $action, $result->successful(), $result->output);

        if ($result->failed()) {
            return back()->dangerBanner(trim($result->output) ?: 'The action failed.');
        }

        return back()->banner(self::PAST_TENSE[$action]." {$name}.");
    }

    /**
     * Keep the audit trail: who ran what against which container, and how it
     * went. Feeds the "Recent actions" card.
     */
    private function record(Request $request, Server $server, string $name, string $action, bool $successful, string $output): void
    {
        ContainerAction::create([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'container' => $name,
            'action' => $action,
            'successful' => $successful,
            'output' => Str::limit(trim($output), 2000) ?: null,
        ]);
    }
}

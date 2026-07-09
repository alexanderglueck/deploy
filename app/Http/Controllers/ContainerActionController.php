<?php

namespace App\Http\Controllers;

use App\Docker\DockerClient;
use App\Events\ContainerActionUpdated;
use App\Jobs\RunContainerAction;
use App\Models\ContainerAction;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class ContainerActionController extends Controller
{
    public function store(Request $request, Server $server, string $name): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        $validated = $request->validate([
            'action' => ['required', Rule::in(DockerClient::ACTIONS)],
        ]);
        $action = $validated['action'];

        // The name comes from the client; only act on a container the server
        // actually reports. An unreachable daemon is a flash error, not a 500.
        try {
            $docker = DockerClient::forServer($server);
            $exists = collect($docker->containers())->contains(fn (array $c) => $c['name'] === $name);
        } catch (Throwable $e) {
            return back()->dangerBanner("Docker on {$server->name} is unreachable: ".$e->getMessage());
        }

        abort_unless($exists, 404);

        // The action itself runs after this response is sent (M5): the row
        // tracks queued → running → ok/failed, each transition broadcast to
        // the team channel — with polling as the no-websocket fallback.
        $record = ContainerAction::create([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'container' => $name,
            'action' => $action,
            'status' => 'queued',
        ]);

        try {
            ContainerActionUpdated::dispatch($record);
        } catch (Throwable) {
            // Reverb being down must not block the action.
        }

        RunContainerAction::dispatchAfterResponse($record);

        return back()->banner(ucfirst($action)." of {$name} queued.");
    }
}

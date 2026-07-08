<?php

namespace App\Http\Controllers;

use App\Execution\ExecutorFactory;
use App\Models\Server;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerConnectionTestController extends Controller
{
    /**
     * Try one key-authenticated connection to the server. On success the
     * server is marked as set up — the path for people who install the
     * public key manually instead of using the password flow. Nothing is
     * probed implicitly; this only runs on the button click.
     */
    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        if ($server->isLocal()) {
            return redirect()->route('server.show', $server);
        }

        try {
            $result = app(ExecutorFactory::class)->for($server, timeout: 15)->run('echo ok');
        } catch (Exception $e) {
            return redirect()->route('server.show', $server)
                ->withErrors(['connection' => $e->getMessage()]);
        }

        if ($result->failed()) {
            return redirect()->route('server.show', $server)
                ->withErrors(['connection' => 'The connection failed: '.trim($result->output)]);
        }

        if (! $server->setup_at) {
            $server->update(['setup_at' => Carbon::now()]);
        }

        return redirect()->route('server.show', $server)->banner('Connection successful.');
    }
}

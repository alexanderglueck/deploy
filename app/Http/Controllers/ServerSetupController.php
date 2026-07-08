<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerSetupController extends Controller
{
    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        $request->validate([
            'password' => 'required',
        ]);

        // Deliberately re-runnable: installation is idempotent, and a server
        // may have been marked set up while the key later stopped working.
        try {
            $server->copyPublicKey($request->input('password'));
        } catch (Exception $e) {
            return redirect()->route('server.show', $server)
                ->withErrors(['password' => $e->getMessage()]);
        }

        return redirect()->route('server.show', $server)
            ->banner('Public key installed and verified.');
    }
}

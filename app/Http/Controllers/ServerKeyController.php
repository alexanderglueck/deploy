<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerKeyController extends Controller
{
    /**
     * Rotate the server's keypair. The old public key stops being used
     * immediately; the new one must be installed before the next deployment.
     */
    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $server->team_id);

        abort_if($server->isLocal(), 422, 'Local servers have no keypair.');

        $server->rotateKeypair();

        return redirect()->route('server.show', $server)
            ->banner('New keypair generated. Install the new public key, then test the connection.');
    }
}

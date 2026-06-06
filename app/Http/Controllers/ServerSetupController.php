<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Team;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerSetupController extends Controller
{
    public function store(Request $request, Team $team, Server $server): RedirectResponse
    {
        $this->authorize('view', $team);

        $request->validate([
            'password' => 'required',
        ]);

        if ($server->isSetUp()) {
            return redirect()->route('server.show', [$team, $server]);
        }

        try {
            $server->copyPublicKey($request->input('password'));
        } catch (Exception $e) {
            return redirect()->route('server.show', [$team, $server])
                ->withErrors(['password' => $e->getMessage()]);
        }

        return redirect()->route('server.show', [$team, $server]);
    }
}

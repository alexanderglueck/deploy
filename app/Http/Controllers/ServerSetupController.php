<?php

namespace App\Http\Controllers;

use App\Server;
use App\Team;
use Exception;
use Illuminate\Http\Request;

class ServerSetupController extends Controller
{
    public function store(Request $request, Team $team, Server $server)
    {
        $request->validate([
            'password' => 'required'
        ]);

        if ($server->isSetUp()) {
            return redirect()->route('server.show', [$team, $server]);
        }

        try {
            $server->copyPublicKey($request->input('password'));
        } catch (Exception $e) {
            return redirect()->route('server.show', [$team, $server])
                ->withErrors($e->getMessage());
        }

        return redirect()->route('server.show', [$team, $server]);
    }
}

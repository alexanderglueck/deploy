<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * The team the authenticated user is currently acting within. Everything is
     * scoped to this team (Jetstream tracks one active team per session).
     */
    protected function currentTeam(Request $request): Team
    {
        return $request->user()->currentTeam ?? abort(403, 'No active team.');
    }

    /**
     * Ensure a resource belongs to the current team, otherwise 404 (so we never
     * confirm the existence of another team's resources).
     */
    protected function ensureOwnedByCurrentTeam(Request $request, int $teamId): Team
    {
        $team = $this->currentTeam($request);

        abort_unless($teamId === $team->id, 404);

        return $team;
    }
}

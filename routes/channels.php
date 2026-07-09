<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Everything realtime happens on one private channel per team, mirroring
| the app's authorization model (all resources are team-scoped).
|
*/

Broadcast::channel('team.{teamUlid}', function (User $user, string $teamUlid) {
    $team = Team::where('ulid', $teamUlid)->first();

    return $team !== null && $user->belongsToTeam($team);
});

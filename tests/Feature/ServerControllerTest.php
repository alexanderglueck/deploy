<?php

namespace Tests\Feature;

use App\Server;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_teams_servers_can_be_shown()
    {
        $user = factory(User::class)->create();

        $server = factory(Server::class)->create([
            'team_id' => $user->teams->first()->id
        ]);

        $this->actingAs($user)->get(route('server.index', [$user->teams->first()->id, $server->id]))
            ->assertSee($server->name)
            ->assertOk();
    }
}

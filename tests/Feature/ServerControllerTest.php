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
        $user = User::factory()->create();

        $server = Server::factory()->create([
            'team_id' => $user->teams->first()->id
        ]);

        $this->actingAs($user)->get(route('server.index', [$user->teams->first()->id, $server->id]))
            ->assertSee($server->name)
            ->assertOk();
    }

    /** @test */
    public function a_server_has_a_create_view()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('server.create', [$user->teams->first()->id]))
            ->assertSee("Create")
            ->assertOk();
    }

    /** @test */
    public function a_server_can_be_created()
    {
        $user = User::factory()->create();

        $teamId = $user->teams->first()->id;

        $server = Server::factory()->make([
            'team_id' => $user->teams->first()->id
        ]);

        $this->actingAs($user)->post(route('server.store', [$teamId]), $server->toArray());

        $this->assertDatabaseHas('servers', [
            'name' => $server->name,
            'user' => $server->user,
            'ip' => $server->ip,
            'port' => $server->port,
        ]);
    }
}

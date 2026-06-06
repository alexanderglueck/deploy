<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_teams_servers_can_be_shown()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $server = Server::factory()->create([
            'team_id' => $team->id,
        ]);

        $this->actingAs($user)->get(route('server.index', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Server/Index')
                ->has('servers', 1)
                ->where('servers.0.name', $server->name)
            );
    }

    #[Test]
    public function a_server_has_a_create_view()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $this->actingAs($user)->get(route('server.create', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Server/Create'));
    }

    #[Test]
    public function a_server_can_be_created()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $server = Server::factory()->make([
            'team_id' => $team->id,
        ]);

        $this->actingAs($user)->post(route('server.store', $team), $server->toArray())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('servers', [
            'name' => $server->name,
            'user' => $server->user,
            'ip' => $server->ip,
            'port' => $server->port,
        ]);
    }
}

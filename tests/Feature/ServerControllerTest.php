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
    public function the_current_teams_servers_can_be_shown()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->get(route('server.index'))
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

        $this->actingAs($user)->get(route('server.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Server/Create'));
    }

    #[Test]
    public function a_server_can_be_created()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->make();

        $this->actingAs($user)->post(route('server.store'), $server->toArray())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('servers', [
            'team_id' => $user->currentTeam->id,
            'name' => $server->name,
            'ip' => $server->ip,
        ]);
    }
}

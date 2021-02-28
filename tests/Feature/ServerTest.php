<?php

namespace Tests\Feature;

use App\Server;
use App\Team;
use App\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ServerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_server_belongs_to_a_team()
    {
        $team = Team::factory()->create();

        $server = Server::factory()->create([
            'team_id' => $team->id
        ]);

        $this->assertEquals($team->id, $server->team->id);
        $this->assertCount(1, $team->servers);
    }

    /** @test */
    public function a_server_has_many_workflows()
    {
        $server = Server::factory()->create();

        $workflow = Workflow::factory()->create([
            'server_id' => $server->id
        ]);

        $this->assertEquals($server->id, $workflow->server_id);
        $this->assertCount(1, $server->workflows);
    }
}

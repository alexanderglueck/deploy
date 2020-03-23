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
        $team = factory(Team::class)->create();

        $server = factory(Server::class)->create([
            'team_id' => $team->id
        ]);

        $this->assertEquals($team->id, $server->team->id);
        $this->assertCount(1, $team->servers);
    }

    /** @test */
    public function a_server_has_many_workflows()
    {
        $server = factory(Server::class)->create();

        $workflow = factory(Workflow::class)->create([
            'server_id' => $server->id
        ]);

        $this->assertEquals($server->id, $workflow->server_id);
        $this->assertCount(1, $server->workflows);
    }
}

<?php

namespace Tests\Feature;

use App\Server;
use App\Team;
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
}

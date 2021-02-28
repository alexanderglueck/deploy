<?php

namespace Tests\Feature;

use App\Project;
use App\Server;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_join_a_team()
    {
        $user = User::factory()->create();

        $team = Team::factory()->create();

        $team->addMember($user);

        $this->assertCount(1, $team->members);

        $this->assertCount(2, $user->teams);
    }

    /** @test */
    public function a_user_can_leave_a_team()
    {
        $user = User::factory()->create();

        $team = Team::factory()->create();

        $team->addMember($user);

        $this->assertCount(1, $team->members);

        $team->removeMember($user);

        $this->assertCount(0, $team->fresh()->members);

        $this->assertCount(1, $user->teams);
    }

    /** @test */
    public function a_team_has_members()
    {
        $user = User::factory()->create();

        $team = Team::factory()->create();

        $team->addMember($user);

        $this->assertCount(1, $team->members);
    }

    /** @test */
    public function a_team_has_projects()
    {
        $team = Team::factory()->create();

        $project = Project::factory()->create([
            'team_id' => $team->id
        ]);

        $this->assertCount(1, $team->projects);
        $this->assertEquals($project->id, $team->projects()->first()->id);
    }

    /** @test */
    public function a_team_has_servers()
    {
        $team = Team::factory()->create();

        $server = Server::factory()->create([
            'team_id' => $team->id
        ]);

        $this->assertCount(1, $team->servers);
        $this->assertEquals($server->id, $team->servers()->first()->id);
    }

}

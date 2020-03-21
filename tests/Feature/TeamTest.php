<?php

namespace Tests\Feature;

use App\Project;
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
        $user = factory(User::class)->create();

        $team = factory(Team::class)->create();

        $team->addMember($user);

        $this->assertCount(1, $team->members);
        $this->assertDatabaseHas('team_memberships', [
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }

    /** @test */
    public function a_user_can_leave_a_team()
    {
        $john = factory(User::class)->create();
        $jane = factory(User::class)->create();

        $team = factory(Team::class)->create();

        $team->addMember($john);
        $team->addMember($jane);

        $this->assertCount(2, $team->members);
        $this->assertDatabaseHas('team_memberships', [
            'user_id' => $jane->id,
            'team_id' => $team->id,
        ]);

        $team->removeMember($john);

        $this->assertCount(1, $team->fresh()->members);
        $this->assertDatabaseMissing('team_memberships', [
            'user_id' => $john->id,
            'team_id' => $team->id,
        ]);
        $this->assertDatabaseHas('team_memberships', [
            'user_id' => $jane->id,
            'team_id' => $team->id,
        ]);

        $this->assertCount(2, $jane->fresh()->teams);
        $this->assertCount(1, $john->teams);
    }

    /** @test */
    public function a_team_has_members()
    {
        $user = factory(User::class)->create();

        $team = factory(Team::class)->create();

        $team->addMember($user);

        $this->assertCount(1, $team->members);
    }

    /** @test */
    public function a_team_has_projects()
    {
        $team = factory(Team::class)->create();

        $project = factory(Project::class)->create([
            'team_id' => $team->id
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'team_id' => $team->id
        ]);

        $this->assertCount(1, $team->projects);
        $this->assertEquals($project->id, $team->projects()->first()->id);
    }
}

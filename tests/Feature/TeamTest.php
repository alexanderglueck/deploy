<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_join_a_team()
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();

        $team = $owner->ownedTeams()->first();

        $team->users()->attach($member, ['role' => 'admin']);

        $this->assertCount(1, $team->fresh()->users);
        $this->assertCount(1, $member->fresh()->allTeams());
    }

    #[Test]
    public function a_user_can_leave_a_team()
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();

        $team = $owner->ownedTeams()->first();

        $team->users()->attach($member, ['role' => 'admin']);
        $this->assertCount(1, $team->fresh()->users);

        $team->users()->detach($member);

        $this->assertCount(0, $team->fresh()->users);
        $this->assertCount(0, $member->fresh()->allTeams());
    }

    #[Test]
    public function a_team_has_members()
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();

        $team = $owner->ownedTeams()->first();
        $team->users()->attach($member, ['role' => 'admin']);

        $this->assertCount(1, $team->fresh()->users);
    }

    #[Test]
    public function a_team_has_projects()
    {
        $team = Team::factory()->create();

        $project = Project::factory()->create([
            'team_id' => $team->id,
        ]);

        $this->assertCount(1, $team->projects);
        $this->assertEquals($project->id, $team->projects()->first()->id);
    }

    #[Test]
    public function a_team_has_servers()
    {
        $team = Team::factory()->create();

        $server = Server::factory()->create([
            'team_id' => $team->id,
        ]);

        $this->assertCount(1, $team->servers);
        $this->assertEquals($server->id, $team->servers()->first()->id);
    }
}

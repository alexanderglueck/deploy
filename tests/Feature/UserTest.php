<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_belongs_to_many_teams()
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->assertCount(1, $user->allTeams());

        Team::factory()->count(2)->create()->each(function (Team $team) use ($user) {
            $team->users()->attach($user, ['role' => 'admin']);
        });

        $this->assertCount(3, $user->fresh()->allTeams());
    }

    #[Test]
    public function a_user_is_assigned_a_personal_team()
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->assertCount(1, $user->ownedTeams);
        $this->assertTrue($user->ownedTeams()->first()->personal_team);
    }
}

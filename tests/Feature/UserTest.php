<?php

namespace Tests\Feature;

use App\Project;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_belongs_to_many_teams()
    {
        $teams = Team::factory()->count(2)->create();

        $user = User::factory()->create();

        $this->assertCount(1, $user->teams);

        $teams->each->addMember($user);

        $this->assertCount(3, $user->fresh()->teams);
    }

    /** @test */
    public function a_user_is_automatically_asigned_a_team_on_creation()
    {
        $user = User::factory()->create();

        $this->assertCount(1, $user->teams);

        $this->assertEquals($user->name, $user->teams()->first()->name);
    }
}

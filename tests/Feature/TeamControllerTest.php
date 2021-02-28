<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_team_can_be_shown()
    {
        $user = User::factory()->create();

        $team = $user->teams()->first();

        $this->actingAs($user)->get(route('team.show', $team))
            ->assertSee($team->name)
            ->assertOk();
    }
}

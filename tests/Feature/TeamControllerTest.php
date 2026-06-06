<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_current_team_can_be_shown()
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('team.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Show')
                ->where('team.name', $user->currentTeam->name)
            );
    }
}

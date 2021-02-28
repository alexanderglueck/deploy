<?php

namespace Tests\Feature;

use App\Project;
use App\Team;
use App\TeamMembership;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TeamMembershipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_membership_belongs_to_a_team()
    {
        $membership = TeamMembership::factory()->create();

        $this->assertNotNull($membership->team);
    }

    /** @test */
    public function a_membership_belongs_to_a_user()
    {
        $membership = TeamMembership::factory()->create();

        $this->assertNotNull($membership->user);
    }
}

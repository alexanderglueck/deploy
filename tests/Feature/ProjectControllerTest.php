<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_project_can_be_shown()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $project = Project::factory()->create([
            'team_id' => $team->id,
        ]);

        $this->actingAs($user)->get(route('project.show', [$team, $project]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Project/Show')
                ->where('project.name', $project->name)
            );
    }

    #[Test]
    public function a_project_has_a_create_view()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $this->actingAs($user)->get(route('project.create', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Project/Create'));
    }

    #[Test]
    public function a_project_can_be_created()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();

        $project = Project::factory()->make([
            'team_id' => $team->id,
        ]);

        $this->actingAs($user)->post(route('project.store', $team), $project->toArray())
            ->assertRedirect(route('team.show', $team));

        $this->assertDatabaseHas('projects', [
            'name' => $project->name,
        ]);
    }
}

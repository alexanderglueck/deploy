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
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->get(route('project.show', $project))
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

        $this->actingAs($user)->get(route('project.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Project/Create'));
    }

    #[Test]
    public function a_project_can_be_created()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->make();

        $this->actingAs($user)->post(route('project.store'), ['name' => $project->name])
            ->assertRedirect(route('team.show'));

        $this->assertDatabaseHas('projects', [
            'team_id' => $user->currentTeam->id,
            'name' => $project->name,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Deployment;
use App\Project;
use App\User;
use App\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_project_can_be_shown()
    {
        $user = factory(User::class)->create();

        $project = factory(Project::class)->create([
            'team_id' => $user->teams->first()->id
        ]);

        $this->actingAs($user)->get(route('project.show', [$user->teams->first()->id, $project]))
            ->assertSee($project->name)
            ->assertOk();
    }

    /** @test */
    public function a_project_has_a_create_view()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user)->get(route('project.create', [$user->teams->first()->id]))
            ->assertSee("Create")
            ->assertOk();
    }

    /** @test */
    public function a_project_can_be_created()
    {
        $user = factory(User::class)->create();

        $teamId = $user->teams->first()->id;

        $project = factory(Project::class)->make([
            'team_id' => $user->teams->first()->id
        ]);

        $this->actingAs($user)->post(route('project.store', [$teamId]), $project->toArray())
            ->assertRedirect(route('team.show', [$teamId]));

        $this->assertDatabaseHas('projects', [
            'name' => $project->name
        ]);
    }
}

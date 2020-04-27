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
}

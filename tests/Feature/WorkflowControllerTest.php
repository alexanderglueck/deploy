<?php

namespace Tests\Feature;

use App\Project;
use App\User;
use App\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_workflow_has_a_create_view()
    {
        $user = factory(User::class)->create();

        $teamId = $user->teams->first()->id;

        $project = factory(Project::class)->create([
            'team_id' => $teamId
        ]);

        $this->actingAs($user)->get(route('workflow.create', [$teamId, $project]))
            ->assertSee("Create")
            ->assertOk();
    }

    /** @test */
    public function a_workflow_can_be_created()
    {
        $user = factory(User::class)->create();

        $teamId = $user->teams->first()->id;

        $project = factory(Project::class)->create([
            'team_id' => $teamId
        ]);

        $workflow = factory(Workflow::class)->make([
            'project_id' => $project->id
        ]);

        $this->actingAs($user)->post(route('workflow.store', [$teamId, $project]), $workflow->toArray())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('project.show', [$teamId, $project]));

        $this->assertDatabaseHas('workflows', [
            'actions' => $workflow->actions,
            'server_id' => $workflow->server_id,
            'project_id' => $project->id,
        ]);
    }

    /** @test */
    public function a_workflow_can_be_shown()
    {
        $user = factory(User::class)->create();

        $teamId = $user->teams->first()->id;

        $project = factory(Project::class)->create([
            'team_id' => $teamId
        ]);

        $workflow = factory(Workflow::class)->create([
            'project_id' => $project->id
        ]);

        $this->actingAs($user)->get(route('workflow.show', [$teamId, $project->id, $workflow]))
            ->assertSee($workflow->actions);
    }

    /** @test */
    public function a_workflow_can_be_deleted()
    {
        $user = factory(User::class)->create();

        $teamId = $user->teams->first()->id;

        $project = factory(Project::class)->create([
            'team_id' => $teamId
        ]);

        $workflow = factory(Workflow::class)->create([
            'project_id' => $project->id
        ]);

        $this->actingAs($user)->delete(route('workflow.destroy', [$teamId, $project->id, $workflow]))
            ->assertRedirect(route('project.show', [$teamId, $project->id]));

        $this->assertDatabaseMissing('workflows', [
            'id' => $workflow->id
        ]);
    }
}

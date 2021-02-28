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
        $user = User::factory()->create();

        $teamId = $user->teams->first()->id;

        $project = Project::factory()->create([
            'team_id' => $teamId
        ]);

        $this->actingAs($user)->get(route('workflow.create', [$teamId, $project]))
            ->assertSee("Create")
            ->assertOk();
    }

    /** @test */
    public function a_workflow_can_be_created()
    {
        $user = User::factory()->create();

        $teamId = $user->teams->first()->id;

        $project = Project::factory()->create([
            'team_id' => $teamId
        ]);

        $workflow = Workflow::factory()->make([
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
        $user = User::factory()->create();

        $teamId = $user->teams->first()->id;

        $project = Project::factory()->create([
            'team_id' => $teamId
        ]);

        $workflow = Workflow::factory()->create([
            'project_id' => $project->id
        ]);

        $this->actingAs($user)->get(route('workflow.show', [$teamId, $project->id, $workflow]))
            ->assertSee($workflow->actions);
    }

    /** @test */
    public function a_workflow_can_be_deleted()
    {
        $user = User::factory()->create();

        $teamId = $user->teams->first()->id;

        $project = Project::factory()->create([
            'team_id' => $teamId
        ]);

        $workflow = Workflow::factory()->create([
            'project_id' => $project->id
        ]);

        $this->actingAs($user)->delete(route('workflow.destroy', [$teamId, $project->id, $workflow]))
            ->assertRedirect(route('project.show', [$teamId, $project->id]));

        $this->assertDatabaseMissing('workflows', [
            'id' => $workflow->id
        ]);
    }

    /** @test */
    public function a_workflow_can_be_edited()
    {
        $user = User::factory()->create();

        $teamId = $user->teams->first()->id;

        $project = Project::factory()->create([
            'team_id' => $teamId
        ]);

        $workflow = Workflow::factory()->create([
            'project_id' => $project->id
        ]);

        $this->actingAs($user)->get(route('workflow.edit', [$teamId, $project->id, $workflow]))
            ->assertSee($workflow->actions)
            ->assertSee('Edit workflow');
    }

    /** @test */
    public function a_workflow_can_be_updated()
    {
        $user = User::factory()->create();

        $teamId = $user->teams->first()->id;

        $project = Project::factory()->create([
            'team_id' => $teamId
        ]);

        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'actions' => 'old'
        ]);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'actions' => 'old'
        ]);

        $newAction = 'someting';

        $this->actingAs($user)->put(route('workflow.update', [$teamId, $project->id, $workflow]),
            array_merge($workflow->toArray(), ['actions' => $newAction])
        )
            ->assertRedirect(route('project.show', [$teamId, $project->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'actions' => $newAction
        ]);
    }
}

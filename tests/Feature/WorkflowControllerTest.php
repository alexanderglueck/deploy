<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function teamProject(User $user): Project
    {
        $team = $user->ownedTeams()->first();

        return Project::factory()->create(['team_id' => $team->id]);
    }

    #[Test]
    public function a_workflow_has_a_create_view()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->teamProject($user);

        $this->actingAs($user)->get(route('workflow.create', [$project->team, $project]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Workflow/Create'));
    }

    #[Test]
    public function a_workflow_can_be_created()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->teamProject($user);
        $server = Server::factory()->create(['team_id' => $project->team_id]);

        $workflow = Workflow::factory()->make([
            'project_id' => $project->id,
            'server_id' => $server->id,
        ]);

        $this->actingAs($user)->post(route('workflow.store', [$project->team, $project]), $workflow->toArray())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('project.show', [$project->team, $project]));

        $this->assertDatabaseHas('workflows', [
            'actions' => $workflow->actions,
            'server_id' => $server->id,
            'project_id' => $project->id,
        ]);
    }

    #[Test]
    public function a_workflow_can_be_shown()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->teamProject($user);

        $workflow = Workflow::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)->get(route('workflow.show', [$project->team, $project, $workflow]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workflow/Show')
                ->where('workflow.actions', $workflow->actions)
            );
    }

    #[Test]
    public function a_workflow_can_be_deleted()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->teamProject($user);

        $workflow = Workflow::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)->delete(route('workflow.destroy', [$project->team, $project, $workflow]))
            ->assertRedirect(route('project.show', [$project->team, $project]));

        $this->assertDatabaseMissing('workflows', [
            'id' => $workflow->id,
        ]);
    }

    #[Test]
    public function a_workflow_can_be_edited()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->teamProject($user);

        $workflow = Workflow::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)->get(route('workflow.edit', [$project->team, $project, $workflow]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Workflow/Edit')
                ->where('workflow.actions', $workflow->actions)
            );
    }

    #[Test]
    public function a_workflow_can_be_updated()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->teamProject($user);

        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'actions' => 'old',
        ]);

        $newAction = 'something';

        $this->actingAs($user)->put(
            route('workflow.update', [$project->team, $project, $workflow]),
            array_merge($workflow->toArray(), ['actions' => $newAction])
        )
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('project.show', [$project->team, $project]));

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'actions' => $newAction,
        ]);
    }
}

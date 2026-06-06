<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function project(User $user): Project
    {
        return Project::factory()->create(['team_id' => $user->currentTeam->id]);
    }

    #[Test]
    public function a_workflow_has_a_create_view()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);

        $this->actingAs($user)->get(route('workflow.create', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Workflow/Create'));
    }

    #[Test]
    public function a_workflow_can_be_created()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->post(route('workflow.store', $project), [
            'event' => Event::PUSH,
            'actions' => 'echo hi',
            'server' => $server->ulid,
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('project.show', $project));

        $this->assertDatabaseHas('workflows', [
            'project_id' => $project->id,
            'server_id' => $server->id,
            'actions' => 'echo hi',
        ]);
    }

    #[Test]
    public function a_workflow_can_be_shown()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);
        $workflow = Workflow::factory()->create(['project_id' => $project->id, 'server_id' => $server->id]);

        $this->actingAs($user)->get(route('workflow.show', [$project, $workflow]))
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
        $project = $this->project($user);
        $workflow = Workflow::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)->delete(route('workflow.destroy', [$project, $workflow]))
            ->assertRedirect(route('project.show', $project));

        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
    }

    #[Test]
    public function a_workflow_can_be_edited()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);
        $workflow = Workflow::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)->get(route('workflow.edit', [$project, $workflow]))
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
        $project = $this->project($user);
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);
        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
            'actions' => 'old',
        ]);

        $this->actingAs($user)->put(route('workflow.update', [$project, $workflow]), [
            'event' => Event::PUSH,
            'actions' => 'something',
            'server' => $server->ulid,
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('project.show', $project));

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'actions' => 'something',
        ]);
    }
}

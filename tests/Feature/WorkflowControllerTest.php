<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Event;
use App\Support\StepType;
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
    public function a_workflow_can_be_created_with_steps()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->post(route('workflow.store', $project), [
            'event' => Event::PUSH,
            'server' => $server->ulid,
            'steps' => [
                ['type' => StepType::DOCKER_DEPLOY, 'config' => ['target' => 'production']],
                ['type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'echo hi']],
            ],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('project.show', $project));

        $workflow = $project->workflows()->first();
        $this->assertNotNull($workflow);
        $this->assertCount(2, $workflow->steps);
        $this->assertSame(StepType::DOCKER_DEPLOY, $workflow->steps[0]->type);
        $this->assertSame(['target' => 'production'], $workflow->steps[0]->config);
        $this->assertSame(['script' => 'echo hi'], $workflow->steps[1]->config);
    }

    #[Test]
    public function a_workflow_requires_at_least_one_step()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->post(route('workflow.store', $project), [
            'event' => Event::PUSH,
            'server' => $server->ulid,
            'steps' => [],
        ])->assertSessionHasErrors(['steps']);
    }

    #[Test]
    public function a_script_step_requires_a_script()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->post(route('workflow.store', $project), [
            'event' => Event::PUSH,
            'server' => $server->ulid,
            'steps' => [
                ['type' => StepType::INLINE_SCRIPT, 'config' => []],
            ],
        ])->assertSessionHasErrors(['steps.0.config.script']);
    }

    #[Test]
    public function unknown_config_keys_are_stripped()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->post(route('workflow.store', $project), [
            'event' => Event::PUSH,
            'server' => $server->ulid,
            'steps' => [
                ['type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'echo hi', 'path' => '/nope']],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['script' => 'echo hi'], $project->workflows()->first()->steps[0]->config);
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
    public function updating_a_workflow_replaces_its_steps()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = $this->project($user);
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);
        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
            'actions' => 'old',
        ]);
        $workflow->steps()->create([
            'position' => 1,
            'type' => StepType::INLINE_SCRIPT,
            'config' => ['script' => 'old'],
        ]);

        $this->actingAs($user)->put(route('workflow.update', [$project, $workflow]), [
            'event' => Event::PUSH,
            'server' => $server->ulid,
            'steps' => [
                ['type' => StepType::SCRIPT_FILE, 'config' => ['path' => '/srv/deploy.sh']],
            ],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('project.show', $project));

        $workflow->refresh();
        // The legacy script is cleared; steps are the source of truth.
        $this->assertNull($workflow->actions);
        $this->assertCount(1, $workflow->steps);
        $this->assertSame(StepType::SCRIPT_FILE, $workflow->steps[0]->type);
        $this->assertSame(['path' => '/srv/deploy.sh'], $workflow->steps[0]->config);
    }
}

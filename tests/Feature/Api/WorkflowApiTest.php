<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Event;
use App\Support\StepType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($this->user);
        $this->server = Server::factory()->local()->create(['team_id' => $this->user->currentTeam->id]);
        $this->project = Project::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'repository' => 'jondoe/deploy',
            'default_branch' => 'master',
        ]);
    }

    /**
     * A minimal valid create payload. docker_deploy needs no config -- the app
     * name comes from the repository and the compose path from the configured
     * pattern -- which is exactly how the migrated apps are set up.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'server' => $this->server->ulid,
            'steps' => [['type' => StepType::DOCKER_DEPLOY]],
        ];
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/projects/{$this->project->ulid}/workflows".$suffix;
    }

    #[Test]
    public function it_creates_a_default_branch_workflow()
    {
        $this->postJson($this->url(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.event', Event::PUSH)
            ->assertJsonPath('data.branch', null)
            // null is meaningful, so it is reported explicitly rather than as absence.
            ->assertJsonPath('data.branch_mode', 'default-branch')
            ->assertJsonPath('data.server', $this->server->ulid)
            ->assertJsonPath('data.steps.0.type', StepType::DOCKER_DEPLOY)
            ->assertJsonPath('data.steps.0.position', 1);

        $this->assertSame(1, $this->project->workflows()->count());
    }

    #[Test]
    public function a_created_workflow_makes_a_push_deploy_instead_of_being_ignored()
    {
        // The whole point: without a workflow the trigger is accepted and dropped.
        $payload = ['event' => 'push', 'ref' => 'refs/heads/master', 'repo' => 'jondoe/deploy'];
        $headers = ['X-Deploy-Secret' => $this->project->webhook_secret];

        $this->postJson("/api/deploy/{$this->project->deploy_endpoint}", $payload, $headers)
            ->assertOk()
            ->assertSee('IGNORED');

        $this->postJson($this->url(), $this->payload())->assertCreated();

        $this->postJson("/api/deploy/{$this->project->deploy_endpoint}", $payload, $headers)
            ->assertOk()
            ->assertSee('OK');
    }

    #[Test]
    public function it_accepts_any_branch_and_exact_branch_modes()
    {
        $this->postJson($this->url(), $this->payload(['branch' => '*']))
            ->assertCreated()->assertJsonPath('data.branch_mode', 'any');

        $this->postJson($this->url(), $this->payload(['branch' => 'develop']))
            ->assertCreated()->assertJsonPath('data.branch_mode', 'exact');
    }

    #[Test]
    public function it_rejects_an_unsupported_event()
    {
        $this->postJson($this->url(), $this->payload(['event' => 'not-an-event']))
            ->assertStatus(422);
    }

    #[Test]
    public function it_refuses_to_create_a_workflow_that_cannot_deploy()
    {
        // A workflow with no steps accepts every trigger and then fails with
        // "The workflow has no steps" -- indistinguishable from success in CI.
        $this->postJson($this->url(), ['server' => $this->server->ulid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('steps');

        $this->assertSame(0, $this->project->workflows()->count());
    }

    #[Test]
    public function it_validates_step_types_and_their_required_config()
    {
        $this->postJson($this->url(), $this->payload(['steps' => [['type' => 'rm_minus_rf']]]))
            ->assertStatus(422)->assertJsonValidationErrors('steps.0.type');

        $this->postJson($this->url(), $this->payload(['steps' => [['type' => StepType::INLINE_SCRIPT]]]))
            ->assertStatus(422)->assertJsonValidationErrors('steps.0.config.script');

        $this->postJson($this->url(), $this->payload(['steps' => [['type' => StepType::SCRIPT_FILE]]]))
            ->assertStatus(422)->assertJsonValidationErrors('steps.0.config.path');
    }

    #[Test]
    public function it_hides_servers_from_other_teams()
    {
        $foreign = Server::factory()->local()->create([
            'team_id' => User::factory()->withPersonalTeam()->create()->currentTeam->id,
        ]);

        // 404, not 422: the token should not learn that this server exists.
        $this->postJson($this->url(), $this->payload(['server' => $foreign->ulid]))
            ->assertNotFound();
    }

    #[Test]
    public function it_lists_shows_and_deletes_workflows()
    {
        $ulid = $this->postJson($this->url(), $this->payload())->json('data.ulid');

        $this->getJson($this->url())
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.steps.0.type', StepType::DOCKER_DEPLOY);

        $this->getJson($this->url("/{$ulid}"))
            ->assertOk()->assertJsonPath('data.ulid', $ulid);

        $this->deleteJson($this->url("/{$ulid}"))->assertOk();
        $this->assertSame(0, $this->project->workflows()->count());
    }

    #[Test]
    public function it_amends_a_workflow_in_place()
    {
        $ulid = $this->postJson($this->url(), $this->payload())->json('data.ulid');
        $other = Server::factory()->local()->create(['team_id' => $this->user->currentTeam->id]);

        $this->patchJson($this->url("/{$ulid}"), [
            'branch' => 'develop',
            'server' => $other->ulid,
        ])
            ->assertOk()
            ->assertJsonPath('data.branch', 'develop')
            ->assertJsonPath('data.branch_mode', 'exact')
            ->assertJsonPath('data.server', $other->ulid)
            // Untouched by a workflow-only patch.
            ->assertJsonPath('data.steps.0.type', StepType::DOCKER_DEPLOY);
    }

    #[Test]
    public function patching_a_null_branch_restores_default_branch_mode()
    {
        $ulid = $this->postJson($this->url(), $this->payload(['branch' => 'develop']))->json('data.ulid');

        // null is a mode, not an omission -- it has to survive the round trip.
        $this->patchJson($this->url("/{$ulid}"), ['branch' => null])
            ->assertOk()
            ->assertJsonPath('data.branch', null)
            ->assertJsonPath('data.branch_mode', 'default-branch');
    }

    #[Test]
    public function patching_steps_replaces_them_and_retires_a_legacy_script()
    {
        // A pre-steps workflow: its script lives in `actions`, and
        // ProcessDeployments wraps it as a single inline step.
        $workflow = Workflow::factory()->create([
            'project_id' => $this->project->id,
            'server_id' => $this->server->id,
            'actions' => 'echo legacy',
        ]);

        $this->patchJson($this->url("/{$workflow->ulid}"), [
            'steps' => [
                ['type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'echo one']],
                ['type' => StepType::DOCKER_DEPLOY, 'config' => ['app' => 'contacts']],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data.steps')
            ->assertJsonPath('data.steps.1.position', 2)
            ->assertJsonPath('data.steps.1.config.app', 'contacts')
            // Otherwise the workflow would carry two definitions of itself.
            ->assertJsonPath('data.actions', null);

        $this->assertNull($workflow->fresh()->actions);
    }

    #[Test]
    public function it_hides_another_teams_workflow()
    {
        $foreignProject = Project::factory()->create([
            'team_id' => User::factory()->withPersonalTeam()->create()->currentTeam->id,
        ]);
        $foreign = Workflow::factory()->create([
            'project_id' => $foreignProject->id,
            'server_id' => $this->server->id,
        ]);

        $this->getJson($this->url("/{$foreign->ulid}"))->assertNotFound();
        $this->patchJson($this->url("/{$foreign->ulid}"), ['branch' => '*'])->assertNotFound();
        $this->deleteJson($this->url("/{$foreign->ulid}"))->assertNotFound();

        // The rejected patch left it alone.
        $this->assertNull($foreign->fresh()->branch);
    }
}

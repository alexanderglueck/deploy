<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use App\Support\StepType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The steps of a workflow over the management API.
 *
 * These exist because the nine migrated apps were registered with workflows but
 * no steps: every push was accepted, produced a deployment row, and failed in
 * milliseconds with "The workflow has no steps". Fixing that from a terminal
 * needed a way to write steps without deleting and recreating the workflow.
 */
class WorkflowStepApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($this->user);

        $server = Server::factory()->local()->create(['team_id' => $this->user->currentTeam->id]);
        $this->project = Project::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'repository' => 'jondoe/contacts',
            'default_branch' => 'master',
        ]);
        $this->workflow = Workflow::factory()->create([
            'project_id' => $this->project->id,
            'server_id' => $server->id,
            'actions' => null,
        ]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/projects/{$this->project->ulid}/workflows/{$this->workflow->ulid}/steps".$suffix;
    }

    #[Test]
    public function it_replaces_the_step_list()
    {
        $this->putJson($this->url(), [
            'steps' => [
                ['type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'echo before']],
                ['type' => StepType::DOCKER_DEPLOY],
            ],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', StepType::INLINE_SCRIPT)
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.1.type', StepType::DOCKER_DEPLOY)
            ->assertJsonPath('data.1.position', 2)
            // No config is valid for docker_deploy: the app name derives from
            // the repository, the compose path from the configured pattern.
            ->assertJsonPath('data.1.config', []);

        // PUT, not PATCH: the second call is the whole list, positions restart.
        $this->putJson($this->url(), ['steps' => [['type' => StepType::DOCKER_DEPLOY]]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.position', 1);

        $this->assertSame(1, $this->workflow->steps()->count());
    }

    #[Test]
    public function it_lists_steps_in_order()
    {
        $this->putJson($this->url(), [
            'steps' => [
                ['type' => StepType::SCRIPT_FILE, 'config' => ['path' => '/srv/bin/pre.sh', 'args' => '--verbose']],
                ['type' => StepType::DOCKER_DEPLOY, 'config' => ['app' => 'contacts', 'target' => 'production']],
            ],
        ])->assertOk();

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.0.config.path', '/srv/bin/pre.sh')
            ->assertJsonPath('data.0.config.args', '--verbose')
            ->assertJsonPath('data.1.config.app', 'contacts')
            ->assertJsonPath('data.1.config.target', 'production');
    }

    #[Test]
    public function it_appends_a_step_after_the_existing_ones()
    {
        $this->putJson($this->url(), ['steps' => [['type' => StepType::DOCKER_DEPLOY]]])->assertOk();

        $this->postJson($this->url(), [
            'steps' => [['type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'echo after']]],
        ])
            ->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.position', 2)
            ->assertJsonPath('data.0.config.script', 'echo after');

        $this->assertSame(
            [StepType::DOCKER_DEPLOY, StepType::INLINE_SCRIPT],
            $this->workflow->steps()->pluck('type')->all()
        );
    }

    #[Test]
    public function it_deletes_a_single_step()
    {
        $this->putJson($this->url(), [
            'steps' => [
                ['type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'echo one']],
                ['type' => StepType::DOCKER_DEPLOY],
            ],
        ])->assertOk();

        $first = $this->workflow->steps()->first();

        $this->deleteJson($this->url("/{$first->ulid}"))->assertOk();

        $this->assertSame([StepType::DOCKER_DEPLOY], $this->workflow->steps()->pluck('type')->all());
    }

    #[Test]
    public function it_drops_config_keys_the_step_type_does_not_use()
    {
        $this->putJson($this->url(), [
            'steps' => [[
                'type' => StepType::INLINE_SCRIPT,
                // `path` belongs to script_file; `checkout_sha` is set by the
                // rollback path, never by a caller.
                'config' => ['script' => 'echo one', 'path' => '/srv/bin/pre.sh', 'checkout_sha' => true],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.config', ['script' => 'echo one']);
    }

    #[Test]
    public function it_rejects_an_empty_step_list()
    {
        $this->putJson($this->url(), ['steps' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('steps');
    }

    #[Test]
    public function it_shape_checks_paths_that_reach_generated_scripts()
    {
        $this->putJson($this->url(), [
            'steps' => [[
                'type' => StepType::DOCKER_DEPLOY,
                'config' => ['compose_file' => '/srv/apps/x/compose.yml; rm -rf /'],
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('steps.0.config.compose_file');
    }

    #[Test]
    public function the_steps_it_writes_are_what_a_deployment_runs()
    {
        $this->putJson($this->url(), [
            'steps' => [['type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'echo deployed']]],
        ])->assertOk();

        $deployment = $this->postJson("/api/v1/projects/{$this->project->ulid}/deploy")
            ->assertStatus(202)->json('data.ulid');

        // The queue is sync in tests, so the deployment has already run.
        $this->getJson("/api/v1/deployments/{$deployment}")
            ->assertOk()
            // Before the steps existed this was 'failed' with "no steps".
            ->assertJsonPath('data.status', 'deployed');
    }

    #[Test]
    public function it_hides_steps_of_another_teams_workflow()
    {
        $foreignTeam = User::factory()->withPersonalTeam()->create()->currentTeam;
        $foreignProject = Project::factory()->create(['team_id' => $foreignTeam->id]);
        $foreign = Workflow::factory()->create([
            'project_id' => $foreignProject->id,
            'server_id' => Server::factory()->local()->create(['team_id' => $foreignTeam->id])->id,
        ]);

        $url = "/api/v1/projects/{$foreignProject->ulid}/workflows/{$foreign->ulid}/steps";

        $this->getJson($url)->assertNotFound();
        $this->putJson($url, ['steps' => [['type' => StepType::DOCKER_DEPLOY]]])->assertNotFound();

        $this->assertSame(0, $foreign->steps()->count());
    }

    #[Test]
    public function it_refuses_a_workflow_that_belongs_to_another_project()
    {
        // Same team, wrong parent: the nesting has to be enforced, not assumed.
        $other = Project::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->getJson("/api/v1/projects/{$other->ulid}/workflows/{$this->workflow->ulid}/steps")
            ->assertNotFound();
    }
}

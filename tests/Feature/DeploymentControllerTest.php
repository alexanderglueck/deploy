<?php

namespace Tests\Feature;

use App\Execution\ExecutorFactory;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeploymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a project with a workflow so triggered deployments can run.
     */
    private function projectWithWorkflow(array $projectAttributes = []): Project
    {
        $server = Server::factory()->local()->create();
        $project = Project::factory()->create(['team_id' => $server->team_id, ...$projectAttributes]);
        Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
        ]);

        return $project;
    }

    private function githubPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'ref' => 'refs/heads/master',
            'repository' => ['full_name' => 'jondoe/deploy'],
            'head_commit' => ['id' => 'abcdef1234567890'],
        ], $overrides);
    }

    /**
     * POST a GitHub-style webhook, signed with the project's secret unless a
     * different one is given.
     */
    private function postGithubWebhook(Project $project, array $payload, ?string $secret = null)
    {
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), $secret ?? $project->webhook_secret);

        return $this->postJson(route('api.deployment.store', $project->deploy_endpoint), $payload, [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => $signature,
        ]);
    }

    #[Test]
    public function a_signed_github_push_creates_a_deployment()
    {
        ExecutorFactory::fake();

        $project = $this->projectWithWorkflow();

        $this->postGithubWebhook($project, $this->githubPayload())
            ->assertOk();

        $this->assertCount(1, $project->fresh()->deployments);

        $deployment = $project->fresh()->deployments->first();
        $this->assertSame('refs/heads/master', $deployment->ref);
        $this->assertSame('jondoe/deploy', $deployment->repository);
        $this->assertSame('abcdef1234567890', $deployment->commit_sha);
        $this->assertSame('deployed', $deployment->status);
    }

    #[Test]
    public function an_invalid_signature_is_rejected()
    {
        $project = $this->projectWithWorkflow();

        $this->postGithubWebhook($project, $this->githubPayload(), 'wrong-secret')
            ->assertForbidden();

        $this->assertCount(0, $project->fresh()->deployments);
    }

    #[Test]
    public function a_missing_signature_is_rejected()
    {
        $project = $this->projectWithWorkflow();

        $this->postJson(route('api.deployment.store', $project->deploy_endpoint), $this->githubPayload(), [
            'X-GitHub-Event' => 'push',
        ])->assertForbidden();

        $this->assertCount(0, $project->fresh()->deployments);
    }

    #[Test]
    public function a_github_ping_is_acknowledged_without_deploying()
    {
        $project = $this->projectWithWorkflow();

        $payload = ['zen' => 'Design for failure.'];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), $project->webhook_secret);

        $this->postJson(route('api.deployment.store', $project->deploy_endpoint), $payload, [
            'X-GitHub-Event' => 'ping',
            'X-Hub-Signature-256' => $signature,
        ])->assertOk()->assertSee('PONG');

        $this->assertCount(0, $project->fresh()->deployments);
    }

    #[Test]
    public function a_webhook_for_another_repository_is_rejected()
    {
        $project = $this->projectWithWorkflow(['repository' => 'jondoe/deploy']);

        $this->postGithubWebhook($project, $this->githubPayload([
            'repository' => ['full_name' => 'someone-else/other-repo'],
        ]))->assertUnprocessable();

        $this->assertCount(0, $project->fresh()->deployments);
    }

    #[Test]
    public function a_generic_trigger_can_authenticate_with_the_secret_header()
    {
        ExecutorFactory::fake();

        $project = $this->projectWithWorkflow();

        $this->postJson(route('api.deployment.store', $project->deploy_endpoint), [
            'event' => 'push',
            'ref' => 'refs/heads/master',
            'repo' => 'jondoe/deploy',
        ], [
            'X-Deploy-Secret' => $project->webhook_secret,
        ])->assertOk();

        $this->assertCount(1, $project->fresh()->deployments);
    }

    #[Test]
    public function a_generic_trigger_with_a_wrong_secret_is_rejected()
    {
        $project = $this->projectWithWorkflow();

        $this->postJson(route('api.deployment.store', $project->deploy_endpoint), [
            'event' => 'push',
            'ref' => 'refs/heads/master',
            'repo' => 'jondoe/deploy',
        ], [
            'X-Deploy-Secret' => 'wrong-secret',
        ])->assertForbidden();

        $this->assertCount(0, $project->fresh()->deployments);
    }

    #[Test]
    public function a_generic_trigger_without_required_fields_is_rejected()
    {
        $project = $this->projectWithWorkflow();

        $this->postJson(route('api.deployment.store', $project->deploy_endpoint), [], [
            'X-Deploy-Secret' => $project->webhook_secret,
        ])->assertUnprocessable();

        $this->assertCount(0, $project->fresh()->deployments);
    }

    #[Test]
    public function a_manual_deploy_uses_the_configured_branch()
    {
        ExecutorFactory::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->local()->create(['team_id' => $user->currentTeam->id]);
        $project = Project::factory()->create([
            'team_id' => $user->currentTeam->id,
            'repository' => 'jondoe/deploy',
            'default_branch' => 'develop',
        ]);
        Workflow::factory()->create(['project_id' => $project->id, 'server_id' => $server->id]);

        $this->actingAs($user)->post(route('deployment.store', $project->deploy_endpoint));

        $deployment = $project->fresh()->deployments->first();
        $this->assertSame('refs/heads/develop', $deployment->ref);
        $this->assertSame('jondoe/deploy', $deployment->repository);
    }

    #[Test]
    public function a_member_can_cancel_a_pending_deployment()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);
        $deployment = Deployment::factory()->create(['project_id' => $project->id, 'received_at' => now()]);

        $this->assertSame('pending', $deployment->status);

        $this->actingAs($user)
            ->post(route('deployment.cancel', [$project, $deployment]))
            ->assertRedirect(route('project.show', $project));

        $this->assertSame('canceled', $deployment->fresh()->status);
    }

    #[Test]
    public function a_finished_deployment_cannot_be_canceled()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);
        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
            'processed_at' => now(),
            'deployed_at' => now(),
        ]);

        $this->actingAs($user)->post(route('deployment.cancel', [$project, $deployment]));

        $this->assertNull($deployment->fresh()->canceled_at);
    }

    #[Test]
    public function a_user_cannot_cancel_another_teams_deployment()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $owner->currentTeam->id]);
        $deployment = Deployment::factory()->create(['project_id' => $project->id, 'received_at' => now()]);

        $this->actingAs($outsider)
            ->post(route('deployment.cancel', [$project, $deployment]))
            ->assertNotFound();

        $this->assertNull($deployment->fresh()->canceled_at);
    }
}

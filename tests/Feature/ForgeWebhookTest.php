<?php

namespace Tests\Feature;

use App\Execution\ExecutorFactory;
use App\Models\Project;
use App\Models\Server;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ForgeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        ExecutorFactory::fake();

        $server = Server::factory()->local()->create();
        $this->project = Project::factory()->create(['team_id' => $server->team_id]);
        Workflow::factory()->create([
            'project_id' => $this->project->id,
            'server_id' => $server->id,
            'branch' => Workflow::BRANCH_ANY,
        ]);
    }

    #[Test]
    public function a_gitlab_push_hook_deploys()
    {
        $payload = [
            'ref' => 'refs/heads/main',
            'checkout_sha' => 'abc123def',
            'project' => ['path_with_namespace' => 'jondoe/deploy', 'default_branch' => 'main'],
        ];

        $this->postJson(route('api.deployment.store', $this->project->deploy_endpoint), $payload, [
            'X-Gitlab-Event' => 'Push Hook',
            'X-Gitlab-Token' => $this->project->webhook_secret,
        ])->assertOk()->assertSee('OK');

        $deployment = $this->project->fresh()->deployments->first();
        $this->assertSame('jondoe/deploy', $deployment->repository);
        $this->assertSame('abc123def', $deployment->commit_sha);
        $this->assertSame('main', $deployment->default_branch);
    }

    #[Test]
    public function a_wrong_gitlab_token_is_rejected()
    {
        $this->postJson(route('api.deployment.store', $this->project->deploy_endpoint), [
            'ref' => 'refs/heads/main',
            'project' => ['path_with_namespace' => 'jondoe/deploy'],
        ], [
            'X-Gitlab-Event' => 'Push Hook',
            'X-Gitlab-Token' => 'wrong',
        ])->assertForbidden();
    }

    #[Test]
    public function a_gitea_signature_is_accepted()
    {
        // Gitea payloads are GitHub-compatible; the signature is bare hex.
        $payload = [
            'ref' => 'refs/heads/main',
            'repository' => ['full_name' => 'jondoe/deploy', 'default_branch' => 'main'],
            'after' => 'abc123def',
        ];

        $this->postJson(route('api.deployment.store', $this->project->deploy_endpoint), $payload, [
            'X-GitHub-Event' => 'push',
            'X-Gitea-Signature' => hash_hmac('sha256', json_encode($payload), $this->project->webhook_secret),
        ])->assertOk()->assertSee('OK');

        $this->assertCount(1, $this->project->fresh()->deployments);
    }
}

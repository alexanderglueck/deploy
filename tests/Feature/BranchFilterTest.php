<?php

namespace Tests\Feature;

use App\Execution\ExecutorFactory;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BranchFilterTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $server = Server::factory()->local()->create();
        $this->project = Project::factory()->create([
            'team_id' => $server->team_id,
            'repository' => 'jondoe/deploy',
        ]);
        Workflow::factory()->create([
            'project_id' => $this->project->id,
            'server_id' => $server->id,
            'branch' => null, // default branch only
        ]);
    }

    private function push(string $ref, string $defaultBranch = 'main')
    {
        $payload = [
            'ref' => $ref,
            'repository' => ['full_name' => 'jondoe/deploy', 'default_branch' => $defaultBranch],
            'head_commit' => ['id' => 'abc123'],
        ];
        $signature = 'sha256='.hash_hmac('sha256', json_encode($payload), $this->project->webhook_secret);

        return $this->postJson(route('api.deployment.store', $this->project->deploy_endpoint), $payload, [
            'X-GitHub-Event' => 'push',
            'X-Hub-Signature-256' => $signature,
        ]);
    }

    #[Test]
    public function a_push_to_the_default_branch_deploys()
    {
        ExecutorFactory::fake();

        $this->push('refs/heads/main')->assertOk()->assertSee('OK');

        $this->assertCount(1, $this->project->fresh()->deployments);
    }

    #[Test]
    public function a_push_to_a_feature_branch_is_ignored()
    {
        $this->push('refs/heads/feature/wip')->assertOk()->assertSee('IGNORED');

        // No deployment row at all — feature pushes don't pile up as failures.
        $this->assertCount(0, $this->project->fresh()->deployments);
    }

    #[Test]
    public function an_explicit_branch_workflow_matches_only_that_branch()
    {
        ExecutorFactory::fake();
        $this->project->workflows()->first()->update(['branch' => 'staging']);

        $this->push('refs/heads/main')->assertSee('IGNORED');
        $this->push('refs/heads/staging')->assertSee('OK');

        $this->assertCount(1, $this->project->fresh()->deployments);
        $this->assertSame('refs/heads/staging', $this->project->fresh()->deployments->first()->ref);
    }

    #[Test]
    public function a_wildcard_workflow_matches_every_branch()
    {
        ExecutorFactory::fake();
        $this->project->workflows()->first()->update(['branch' => Workflow::BRANCH_ANY]);

        $this->push('refs/heads/anything-goes')->assertSee('OK');

        $this->assertCount(1, $this->project->fresh()->deployments);
    }

    #[Test]
    public function a_generic_trigger_without_default_branch_info_still_deploys()
    {
        ExecutorFactory::fake();

        // Legacy callers don't know the default branch; don't break them.
        $this->postJson(route('api.deployment.store', $this->project->deploy_endpoint), [
            'event' => 'push',
            'ref' => 'refs/heads/master',
            'repo' => 'jondoe/deploy',
        ], [
            'X-Deploy-Secret' => $this->project->webhook_secret,
        ])->assertSee('OK');

        $this->assertCount(1, $this->project->fresh()->deployments);
    }

    #[Test]
    public function a_manual_deploy_matches_the_default_branch_workflow()
    {
        ExecutorFactory::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $this->project->update(['team_id' => $user->currentTeam->id]);
        $this->project->workflows()->first()->server->update(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->post(route('deployment.store', $this->project->deploy_endpoint));

        $deployment = $this->project->fresh()->deployments->first();
        $this->assertSame('deployed', $deployment->status);
        $this->assertSame($user->name, $deployment->triggered_by_name);
    }
}

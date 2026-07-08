<?php

namespace Tests\Feature;

use App\Execution\ExecutorFactory;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use App\Support\StepType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeploymentRollbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->local()->create(['team_id' => $this->user->currentTeam->id]);
        $this->project = Project::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'repository' => 'jondoe/my.app',
        ]);
        Workflow::factory()->create([
            'project_id' => $this->project->id,
            'server_id' => $server->id,
        ]);
    }

    private function deployedDeployment(array $attributes = []): Deployment
    {
        $deployment = Deployment::factory()->create([
            'project_id' => $this->project->id,
            'repository' => 'jondoe/my.app',
            'commit_sha' => 'abc123def456',
            'image' => 'my-app:abc123def456',
            'image_available_at' => now(),
            'image_checked_at' => now(),
            'processed_at' => now(),
            'deployed_at' => now(),
            ...$attributes,
        ]);

        $deployment->steps()->create([
            'position' => 1,
            'type' => StepType::DOCKER_DEPLOY,
            'config' => ['target' => 'production'],
            'status' => DeploymentStep::STATUS_SUCCEEDED,
        ]);

        return $deployment;
    }

    #[Test]
    public function an_available_image_rolls_back_instantly()
    {
        ExecutorFactory::fake();
        $deployment = $this->deployedDeployment();

        $this->actingAs($this->user)
            ->post(route('deployment.rollback', [$this->project, $deployment]))
            ->assertRedirect(route('project.show', $this->project));

        $rollback = Deployment::query()->where('rollback_of_id', $deployment->id)->first();
        $this->assertTrue($rollback->is_rollback);
        $this->assertSame('abc123def456', $rollback->commit_sha);
        $this->assertCount(1, $rollback->steps);
        $this->assertSame(StepType::DOCKER_ROLLBACK, $rollback->steps[0]->type);
        $this->assertSame([
            'app' => 'my-app',
            'sha' => 'abc123def456',
            'compose_file' => '/srv/server-config/apps/my-app/compose.yml',
        ], $rollback->steps[0]->config);
        $this->assertSame('deployed', $rollback->fresh()->status);
    }

    #[Test]
    public function a_pruned_image_rolls_back_by_rebuilding()
    {
        ExecutorFactory::fake();
        $deployment = $this->deployedDeployment(['image_available_at' => null]);

        $this->actingAs($this->user)
            ->post(route('deployment.rollback', [$this->project, $deployment]));

        $rollback = Deployment::query()->where('rollback_of_id', $deployment->id)->first();
        $this->assertCount(1, $rollback->steps);
        $this->assertSame(StepType::DOCKER_DEPLOY, $rollback->steps[0]->type);
        $this->assertTrue($rollback->steps[0]->config['checkout_sha']);
        $this->assertSame('production', $rollback->steps[0]->config['target']);
    }

    #[Test]
    public function the_rollback_replays_the_snapshotted_steps_not_the_workflow()
    {
        Queue::fake();
        $deployment = $this->deployedDeployment(['image_available_at' => null]);
        $deployment->steps()->create([
            'position' => 2,
            'type' => StepType::INLINE_SCRIPT,
            'config' => ['script' => 'echo migrated'],
            'status' => DeploymentStep::STATUS_SUCCEEDED,
        ]);

        $this->actingAs($this->user)
            ->post(route('deployment.rollback', [$this->project, $deployment]));

        $rollback = Deployment::query()->where('rollback_of_id', $deployment->id)->first();
        $this->assertCount(2, $rollback->steps);
        $this->assertSame(StepType::INLINE_SCRIPT, $rollback->steps[1]->type);
        $this->assertArrayNotHasKey('checkout_sha', $rollback->steps[1]->config);
    }

    #[Test]
    public function only_deployed_deployments_can_be_rolled_back_to()
    {
        $deployment = $this->deployedDeployment(['deployed_at' => null, 'failed_at' => now()]);

        $this->actingAs($this->user)
            ->post(route('deployment.rollback', [$this->project, $deployment]))
            ->assertUnprocessable();
    }

    #[Test]
    public function a_deployment_without_a_docker_step_cannot_be_rolled_back_to()
    {
        $deployment = Deployment::factory()->create([
            'project_id' => $this->project->id,
            'commit_sha' => 'abc123def456',
            'processed_at' => now(),
            'deployed_at' => now(),
        ]);
        $deployment->steps()->create([
            'position' => 1,
            'type' => StepType::INLINE_SCRIPT,
            'config' => ['script' => 'echo hi'],
            'status' => DeploymentStep::STATUS_SUCCEEDED,
        ]);

        $this->actingAs($this->user)
            ->post(route('deployment.rollback', [$this->project, $deployment]))
            ->assertUnprocessable();
    }

    #[Test]
    public function a_user_cannot_roll_back_another_teams_deployment()
    {
        $deployment = $this->deployedDeployment();
        $outsider = User::factory()->withPersonalTeam()->create();

        $this->actingAs($outsider)
            ->post(route('deployment.rollback', [$this->project, $deployment]))
            ->assertNotFound();
    }
}

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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeploymentRetryControllerTest extends TestCase
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
            'repository' => 'jondoe/deploy',
        ]);
        Workflow::factory()->create([
            'project_id' => $this->project->id,
            'server_id' => $server->id,
        ]);
    }

    #[Test]
    public function a_failed_deployment_can_be_retried_at_the_same_commit()
    {
        ExecutorFactory::fake();

        $failed = Deployment::factory()->create([
            'project_id' => $this->project->id,
            'repository' => 'jondoe/deploy',
            'commit_sha' => 'abc123',
            'processed_at' => now(),
            'failed_at' => now(),
        ]);
        $failed->steps()->create([
            'position' => 1,
            'type' => StepType::DOCKER_DEPLOY,
            'config' => ['target' => 'production'],
            'status' => DeploymentStep::STATUS_FAILED,
        ]);

        $this->actingAs($this->user)
            ->post(route('deployment.retry', [$this->project, $failed]))
            ->assertRedirect(route('project.show', $this->project));

        $retry = Deployment::query()->where('retry_of_id', $failed->id)->first();
        $this->assertTrue($retry->is_retry);
        $this->assertSame('abc123', $retry->commit_sha);
        $this->assertSame($this->user->name, $retry->triggered_by_name);
        // Docker steps are pinned to the recorded commit, not the branch head.
        $this->assertTrue($retry->steps[0]->config['checkout_sha']);
        $this->assertSame('production', $retry->steps[0]->config['target']);
        $this->assertSame('deployed', $retry->fresh()->status);
    }

    #[Test]
    public function a_successful_deployment_cannot_be_retried()
    {
        $deployment = Deployment::factory()->create([
            'project_id' => $this->project->id,
            'processed_at' => now(),
            'deployed_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->post(route('deployment.retry', [$this->project, $deployment]))
            ->assertUnprocessable();
    }

    #[Test]
    public function a_user_cannot_retry_another_teams_deployment()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $deployment = Deployment::factory()->create([
            'project_id' => $this->project->id,
            'processed_at' => now(),
            'failed_at' => now(),
        ]);

        $this->actingAs($outsider)
            ->post(route('deployment.retry', [$this->project, $deployment]))
            ->assertNotFound();
    }
}

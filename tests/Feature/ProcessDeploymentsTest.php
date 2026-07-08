<?php

namespace Tests\Feature;

use App\Execution\ExecutionResult;
use App\Execution\Executor;
use App\Execution\ExecutorFactory;
use App\Jobs\ProcessDeployments;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Project;
use App\Models\Server;
use App\Models\Workflow;
use App\Support\StepType;
use Closure;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessDeploymentsTest extends TestCase
{
    use RefreshDatabase;

    private function deployment(string $actions = "echo one\r\necho two"): Deployment
    {
        $server = Server::factory()->local()->create();
        $project = Project::factory()->create(['team_id' => $server->team_id]);
        Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
            'actions' => $actions,
        ]);

        return Deployment::factory()->create([
            'project_id' => $project->id,
            'actions' => null,
            'received_at' => now(),
        ]);
    }

    #[Test]
    public function a_successful_run_marks_the_deployment_deployed()
    {
        $executor = ExecutorFactory::fake(exitCode: 0, output: "one\ntwo\n");

        $deployment = $this->deployment();

        ProcessDeployments::dispatchSync($deployment);

        $deployment->refresh();
        $this->assertSame('deployed', $deployment->status);
        $this->assertNotNull($deployment->processed_at);

        // The legacy actions script became a single snapshotted step.
        $this->assertSame("echo one\r\necho two", $deployment->actions);
        $this->assertCount(1, $deployment->steps);

        $step = $deployment->steps->first();
        $this->assertSame(DeploymentStep::STATUS_SUCCEEDED, $step->status);
        $this->assertSame(0, $step->exit_code);
        $this->assertStringContainsString("one\ntwo", $step->output);
        $this->assertNotNull($step->started_at);
        $this->assertNotNull($step->finished_at);

        // Line endings are normalized before execution.
        $this->assertSame(["echo one\necho two"], $executor->scripts);
    }

    #[Test]
    public function a_successful_docker_step_stamps_the_sha_image()
    {
        ExecutorFactory::fake();

        $server = Server::factory()->local()->create();
        $project = Project::factory()->create(['team_id' => $server->team_id]);
        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
            'actions' => null,
        ]);
        $workflow->steps()->create(['position' => 1, 'type' => StepType::DOCKER_DEPLOY, 'config' => []]);

        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
            'repository' => 'jondoe/my.app',
            'commit_sha' => 'abc123',
            'actions' => null,
            'received_at' => now(),
        ]);

        ProcessDeployments::dispatchSync($deployment);

        $deployment->refresh();
        $this->assertSame('deployed', $deployment->status);
        $this->assertSame('my-app:abc123', $deployment->image);
        $this->assertNotNull($deployment->image_available_at);
    }

    #[Test]
    public function pre_created_steps_are_used_instead_of_the_workflow_snapshot()
    {
        $executor = ExecutorFactory::fake();

        $deployment = $this->deployment(); // workflow carries legacy actions
        $deployment->steps()->create([
            'position' => 1,
            'type' => StepType::INLINE_SCRIPT,
            'config' => ['script' => 'echo rollback'],
            'status' => DeploymentStep::STATUS_PENDING,
        ]);

        ProcessDeployments::dispatchSync($deployment);

        $deployment->refresh();
        $this->assertSame('deployed', $deployment->status);
        $this->assertCount(1, $deployment->steps);
        $this->assertSame(['echo rollback'], $executor->scripts);
    }

    #[Test]
    public function a_failing_step_skips_the_remaining_steps()
    {
        ExecutorFactory::fake(exitCode: 1, output: 'boom');

        $server = Server::factory()->local()->create();
        $project = Project::factory()->create(['team_id' => $server->team_id]);
        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
            'actions' => null,
        ]);
        $workflow->steps()->create(['position' => 1, 'type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'exit 1']]);
        $workflow->steps()->create(['position' => 2, 'type' => StepType::INLINE_SCRIPT, 'config' => ['script' => 'echo never']]);

        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
            'actions' => null,
            'received_at' => now(),
        ]);

        ProcessDeployments::dispatchSync($deployment);

        $deployment->refresh();
        $this->assertSame('failed', $deployment->status);
        $this->assertCount(2, $deployment->steps);
        $this->assertSame(DeploymentStep::STATUS_FAILED, $deployment->steps[0]->status);
        $this->assertSame(DeploymentStep::STATUS_SKIPPED, $deployment->steps[1]->status);
        $this->assertStringContainsString('Step 1 exited with code 1', $deployment->log->log);
    }

    #[Test]
    public function a_non_zero_exit_code_marks_the_deployment_failed()
    {
        ExecutorFactory::fake(exitCode: 1, output: 'boom');

        $deployment = $this->deployment();

        ProcessDeployments::dispatchSync($deployment);

        $deployment->refresh();
        $this->assertSame('failed', $deployment->status);
        $this->assertStringContainsString('exited with code 1', $deployment->log->log);
    }

    #[Test]
    public function a_missing_workflow_marks_the_deployment_failed()
    {
        $executor = ExecutorFactory::fake();

        $project = Project::factory()->create();
        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
            'received_at' => now(),
        ]);

        ProcessDeployments::dispatchSync($deployment);

        $deployment->refresh();
        $this->assertSame('failed', $deployment->status);
        $this->assertStringContainsString('No workflow matches', $deployment->log->log);
        $this->assertSame([], $executor->scripts);
    }

    #[Test]
    public function a_canceled_deployment_is_not_executed()
    {
        $executor = ExecutorFactory::fake();

        $deployment = $this->deployment();
        $deployment->update(['canceled_at' => now()]);

        ProcessDeployments::dispatchSync($deployment);

        $deployment->refresh();
        $this->assertSame('canceled', $deployment->status);
        $this->assertNull($deployment->processed_at);
        $this->assertSame([], $executor->scripts);
    }

    #[Test]
    public function an_executor_exception_marks_the_deployment_failed()
    {
        app()->instance(ExecutorFactory::class, new class extends ExecutorFactory
        {
            public function for(Server $server, ?int $timeout = null): Executor
            {
                return new class implements Executor
                {
                    public function run(string $script, ?Closure $onOutput = null): ExecutionResult
                    {
                        throw new Exception('Could not connect to server.');
                    }
                };
            }
        });

        $deployment = $this->deployment();

        ProcessDeployments::dispatchSync($deployment);

        $deployment->refresh();
        $this->assertSame('failed', $deployment->status);
        $this->assertStringContainsString('Could not connect to server.', $deployment->log->log);
    }
}

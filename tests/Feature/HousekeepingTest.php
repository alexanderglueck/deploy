<?php

namespace Tests\Feature;

use App\Execution\ExecutorFactory;
use App\Jobs\ProcessDeployments;
use App\Jobs\PruneDeployments;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function old_concluded_deployments_are_pruned_but_running_ones_survive()
    {
        config(['deploy.retention_days' => 100]);

        $project = Project::factory()->create();

        $oldFinished = Deployment::factory()->create([
            'project_id' => $project->id,
            'deployed_at' => now()->subDays(150),
            'created_at' => now()->subDays(150),
        ]);
        $oldRunning = Deployment::factory()->create([
            'project_id' => $project->id,
            'processed_at' => now()->subDays(150),
            'created_at' => now()->subDays(150),
        ]);
        $recent = Deployment::factory()->create([
            'project_id' => $project->id,
            'deployed_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
        ]);

        $deleted = PruneDeployments::prune();

        $this->assertSame(1, $deleted);
        $this->assertNull($oldFinished->fresh());
        $this->assertNotNull($oldRunning->fresh());
        $this->assertNotNull($recent->fresh());
    }

    #[Test]
    public function a_failed_deployment_notifies_the_configured_url()
    {
        config(['deploy.notify_url' => 'https://ntfy.example.com/deploys']);
        Http::fake();
        ExecutorFactory::fake(exitCode: 1, output: 'boom');

        $server = Server::factory()->local()->create();
        $project = Project::factory()->create(['team_id' => $server->team_id]);
        Workflow::factory()->create(['project_id' => $project->id, 'server_id' => $server->id]);

        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
            'received_at' => now(),
        ]);

        ProcessDeployments::dispatchSync($deployment);

        $this->assertSame('failed', $deployment->fresh()->status);

        Http::assertSent(function ($request) use ($project) {
            return $request->url() === 'https://ntfy.example.com/deploys'
                && $request['status'] === 'failed'
                && $request['project'] === $project->name;
        });
    }

    #[Test]
    public function successful_deployments_do_not_notify()
    {
        config(['deploy.notify_url' => 'https://ntfy.example.com/deploys']);
        Http::fake();
        ExecutorFactory::fake();

        $server = Server::factory()->local()->create();
        $project = Project::factory()->create(['team_id' => $server->team_id]);
        Workflow::factory()->create(['project_id' => $project->id, 'server_id' => $server->id]);

        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
            'received_at' => now(),
        ]);

        ProcessDeployments::dispatchSync($deployment);

        $this->assertSame('deployed', $deployment->fresh()->status);
        Http::assertNothingSent();
    }

    #[Test]
    public function deploy_user_creates_a_user_with_a_personal_team()
    {
        $this->artisan('deploy:user', [
            '--name' => 'Alex',
            '--email' => 'alex@example.com',
            '--password' => 'super-secret-password',
        ])->assertSuccessful();

        $user = User::query()->where('email', 'alex@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->currentTeam);
    }

    #[Test]
    public function deploy_user_rejects_duplicate_emails()
    {
        User::factory()->create(['email' => 'alex@example.com']);

        $this->artisan('deploy:user', [
            '--name' => 'Alex',
            '--email' => 'alex@example.com',
            '--password' => 'super-secret-password',
        ])->assertFailed();
    }

    #[Test]
    public function deployment_output_is_lazy_for_finished_deployments()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);
        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
            'processed_at' => now(),
            'deployed_at' => now(),
        ]);
        $deployment->steps()->create([
            'position' => 1,
            'type' => 'inline_script',
            'config' => ['script' => 'echo hi'],
            'status' => 'succeeded',
            'output' => 'hi there',
        ]);

        // Page payload: no output for the finished deployment.
        $response = $this->actingAs($user)->get(route('project.show', $project));
        $page = $response->viewData('page');
        $serialized = $page['props']['deployments'][0];
        $this->assertArrayNotHasKey('output', $serialized['steps'][0]);
        $this->assertArrayNotHasKey('log', $serialized);

        // Dedicated endpoint delivers it on demand.
        $this->actingAs($user)
            ->get(route('deployment.output', [$project, $deployment]))
            ->assertOk()
            ->assertJsonPath('steps.'.$deployment->steps->first()->ulid, 'hi there');
    }
}

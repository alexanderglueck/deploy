<?php

namespace Tests\Feature;

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

    #[Test]
    public function a_correct_call_creates_a_deployment()
    {
        $server = Server::factory()->create(['ip' => 'test']);
        $project = Project::factory()->create(['team_id' => $server->team_id]);
        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
        ]);
        $deployment = Deployment::factory()->make(['project_id' => $project->id]);

        $this->assertCount(0, $project->deployments);

        $this->get(route('api.deployment.store', $project->deploy_endpoint).'?'.http_build_query([
            'event' => $deployment->event,
            'ref' => $deployment->ref,
            'repo' => $deployment->repository,
        ]))->assertSessionMissing('errors');

        $this->assertCount(1, $project->fresh()->deployments);
    }

    #[Test]
    public function a_wrong_call_does_not_create_a_deployment()
    {
        $project = Project::factory()->create();

        $this->get(route('api.deployment.store', $project->deploy_endpoint))
            ->assertSessionHas('errors');

        $this->assertCount(0, $project->deployments);
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

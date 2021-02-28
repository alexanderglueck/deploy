<?php

namespace Tests\Feature;

use App\Deployment;
use App\Project;
use App\Server;
use App\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_correct_call_creates_a_deployment()
    {
        $server = Server::factory()->create([
            'ip' => 'test'
        ]);

        $project = Project::factory()->create([
            'team_id' => $server->team_id
        ]);

        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id
        ]);

        $deployment = Deployment::factory()->make([
            'project_id' => $project->id
        ]);

        $this->assertCount(0, $project->deployments);

        $this->get(route('api.deployment.store', $project->deploy_endpoint) . '?' . http_build_query([
                'event' => $deployment->event,
                'ref' => $deployment->ref,
                'repo' => $deployment->repository,
            ]))->assertSessionMissing('errors');

        $this->assertCount(1, $project->fresh()->deployments);
    }

    /** @test */
    public function a_wrong_call_does_not_create_a_deployment()
    {
        $project = Project::factory()->create();

        $this->get(route('api.deployment.store', $project->deploy_endpoint))
            ->assertSessionHas('errors');

        $this->assertCount(0, $project->deployments);
    }
}

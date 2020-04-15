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
        $server = factory(Server::class)->create([
            'ip' => 'test'
        ]);

        $project = factory(Project::class)->create([
            'team_id' => $server->team_id
        ]);

        $workflow = factory(Workflow::class)->create([
            'project_id' => $project->id,
            'server_id' => $server->id
        ]);

        $deployment = factory(Deployment::class)->make([
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
        $project = factory(Project::class)->create();

        $this->get(route('api.deployment.store', $project->deploy_endpoint))
            ->assertSessionHas('errors');

        $this->assertCount(0, $project->deployments);
    }
}

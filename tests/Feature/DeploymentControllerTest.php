<?php

namespace Tests\Feature;

use App\PendingDeployment;
use App\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_correct_call_creates_a_pending_deployment()
    {
        $project = factory(Project::class)->create();

        $pendingDeployment = factory(PendingDeployment::class)->make([
            'project_id' => $project->id
        ]);

        $this->assertCount(0, $project->pendingDeployments);

        $this->get(route('api.deployment.store', $project->deploy_endpoint) . '?' . http_build_query([
                'event' => $pendingDeployment->event,
                'ref' => $pendingDeployment->ref,
                'repo' => $pendingDeployment->repository,
            ]))
            ->assertSessionMissing('errors');

        $this->assertCount(1, $project->deployments);
    }

    /** @test */
    public function a_wrong_call_does_not_create_a_pending_deployment()
    {
        $project = factory(Project::class)->create();

        $this->get(route('api.deployment.store', $project->deploy_endpoint))
            ->assertSessionHas('errors');

        $this->assertCount(0, $project->pendingDeployments);
    }
}

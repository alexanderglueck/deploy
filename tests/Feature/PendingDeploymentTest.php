<?php

namespace Tests\Feature;

use App\PendingDeployment;
use App\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingDeploymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_pending_deployment_belongs_to_a_project()
    {
        $project = factory(Project::class)->create();

        $pendingDeployment = factory(PendingDeployment::class)->create([
            'project_id' => $project->id
        ]);

        $this->assertNotNull($pendingDeployment->project);
        $this->assertEquals($project->id, $pendingDeployment->project->id);
    }
}

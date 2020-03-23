<?php

namespace Tests\Feature;

use App\Deployment;
use App\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_pending_deployment_belongs_to_a_project()
    {
        $project = factory(Project::class)->create();

        $deployment = factory(Deployment::class)->create([
            'project_id' => $project->id
        ]);

        $this->assertNotNull($deployment->project);
        $this->assertEquals($project->id, $deployment->project->id);
    }
}

<?php

namespace Tests\Feature;

use App\Deployment;
use App\Log;
use App\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_deployment_belongs_to_a_project()
    {
        $project = Project::factory()->create();

        $deployment = Deployment::factory()->create([
            'project_id' => $project->id
        ]);

        $this->assertNotNull($deployment->project);
        $this->assertEquals($project->id, $deployment->project->id);
    }

    /** @test */
    public function a_deployment_has_one_log()
    {
        $deployment = Deployment::factory()->create();

        $this->assertNotNull($deployment->log);
        $this->assertInstanceOf(Log::class, $deployment->log);
    }
}

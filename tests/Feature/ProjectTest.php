<?php

namespace Tests\Feature;

use App\PendingDeployment;
use App\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_project_belongs_to_a_team()
    {
        $project = factory(Project::class)->create();

        $this->assertNotNull($project->team);
    }

    /** @test */
    public function a_project_has_many_pending_deployments()
    {
        $project = factory(Project::class)->create();

        $pendingDeployments = factory(PendingDeployment::class, 2)->create([
            'project_id' => $project->id
        ]);

        $this->assertCount(2, $project->pendingDeployments);
        $this->assertEquals($project->id, $pendingDeployments->first->project->id);
    }
}

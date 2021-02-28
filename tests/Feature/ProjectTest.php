<?php

namespace Tests\Feature;

use App\Deployment;
use App\Project;
use App\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_project_belongs_to_a_team()
    {
        $project = Project::factory()->create();

        $this->assertNotNull($project->team);
    }

    /** @test */
    public function a_project_has_many_deployments()
    {
        $project = Project::factory()->create();

        $deployments = Deployment::factory()->count(2)->create([
            'project_id' => $project->id
        ]);

        $this->assertCount(2, $project->deployments);
        $this->assertEquals($project->id, $deployments->first->project->id);
    }

    /** @test */
    public function a_project_has_many_workflows()
    {
        $project = Project::factory()->create();

        $workflow = Workflow::factory([
            'project_id' => $project->id
        ])->project()->create();

        $this->assertCount(1, $project->workflows);

        $this->assertEquals($project->id, $workflow->project->id);

        $this->assertEquals($project->team_id, $workflow->server->team_id);
        $this->assertEquals($project->team_id, $workflow->project->team_id);
    }
}

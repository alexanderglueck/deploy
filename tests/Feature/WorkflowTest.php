<?php

namespace Tests\Feature;

use App\Project;
use App\Server;
use App\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_workflow_belongs_to_a_server()
    {
        $server = factory(Server::class)->create();

        $workflow = factory(Workflow::class)->create([
            'server_id' => $server->id
        ]);

        $this->assertEquals($server->id, $workflow->server_id);
        $this->assertCount(1, $server->workflows);
    }

    /** @test */
    public function a_workflow_belongs_to_a_project()
    {
        $project = factory(Project::class)->create();

        $workflow = factory(Workflow::class)->create([
            'project_id' => $project->id
        ]);

        $this->assertEquals($project->id, $workflow->project_id);
        $this->assertCount(1, $project->workflows);
    }
}

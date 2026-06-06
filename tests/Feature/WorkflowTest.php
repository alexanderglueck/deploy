<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Server;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_workflow_belongs_to_a_server()
    {
        $server = Server::factory()->create();

        $workflow = Workflow::factory()->create([
            'server_id' => $server->id,
        ]);

        $this->assertEquals($server->id, $workflow->server_id);
        $this->assertCount(1, $server->workflows);
    }

    #[Test]
    public function a_workflow_belongs_to_a_project()
    {
        $project = Project::factory()->create();

        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
        ]);

        $this->assertEquals($project->id, $workflow->project_id);
        $this->assertCount(1, $project->workflows);
    }
}

<?php

namespace Tests\Feature;

use App\Execution\ExecutorFactory;
use App\Jobs\ReconcileImageAvailability;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\Server;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconcileImageAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stamps_which_images_still_exist()
    {
        $server = Server::factory()->local()->create();
        $project = Project::factory()->create(['team_id' => $server->team_id]);
        Workflow::factory()->create(['project_id' => $project->id, 'server_id' => $server->id]);

        $kept = Deployment::factory()->create([
            'project_id' => $project->id,
            'image' => 'my-app:aaa111',
            'image_available_at' => null,
        ]);
        $pruned = Deployment::factory()->create([
            'project_id' => $project->id,
            'image' => 'my-app:bbb222',
            'image_available_at' => now(),
        ]);

        $executor = ExecutorFactory::fake(output: "OK my-app:aaa111\nMISS my-app:bbb222\n");

        (new ReconcileImageAvailability($project))->handle();

        $this->assertNotNull($kept->fresh()->image_available_at);
        $this->assertNull($pruned->fresh()->image_available_at);
        $this->assertNotNull($kept->fresh()->image_checked_at);
        $this->assertNotNull($pruned->fresh()->image_checked_at);

        // One executor run checks all images.
        $this->assertCount(1, $executor->scripts);
        $this->assertStringContainsString("'my-app:aaa111'", $executor->scripts[0]);
        $this->assertStringContainsString("'my-app:bbb222'", $executor->scripts[0]);
    }

    #[Test]
    public function a_failed_check_changes_nothing()
    {
        $server = Server::factory()->local()->create();
        $project = Project::factory()->create(['team_id' => $server->team_id]);
        Workflow::factory()->create(['project_id' => $project->id, 'server_id' => $server->id]);

        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
            'image' => 'my-app:aaa111',
            'image_available_at' => now(),
        ]);

        ExecutorFactory::fake(exitCode: 1);

        (new ReconcileImageAvailability($project))->handle();

        $this->assertNotNull($deployment->fresh()->image_available_at);
        $this->assertNull($deployment->fresh()->image_checked_at);
    }
}

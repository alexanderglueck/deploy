<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Log;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_deployment_belongs_to_a_project()
    {
        $project = Project::factory()->create();

        $deployment = Deployment::factory()->create([
            'project_id' => $project->id,
        ]);

        $this->assertNotNull($deployment->project);
        $this->assertEquals($project->id, $deployment->project->id);
    }

    #[Test]
    public function a_deployment_has_one_log()
    {
        $deployment = Deployment::factory()->create();

        $this->assertNotNull($deployment->log);
        $this->assertInstanceOf(Log::class, $deployment->log);
    }

    #[Test]
    public function it_exposes_a_lifecycle_status()
    {
        $pending = Deployment::factory()->create([
            'received_at' => now(),
        ]);
        $this->assertSame('pending', $pending->status);
        $this->assertTrue($pending->isActive());

        $deploying = Deployment::factory()->create([
            'received_at' => now(),
            'processed_at' => now(),
        ]);
        $this->assertSame('deploying', $deploying->status);
        $this->assertTrue($deploying->isActive());

        $deployed = Deployment::factory()->create([
            'processed_at' => now(),
            'deployed_at' => now(),
        ]);
        $this->assertSame('deployed', $deployed->status);
        $this->assertFalse($deployed->isActive());

        $canceled = Deployment::factory()->create([
            'canceled_at' => now(),
        ]);
        $this->assertSame('canceled', $canceled->status);
        $this->assertFalse($canceled->isActive());

        // The status accessor is serialized for the front-end.
        $this->assertArrayHasKey('status', $deployed->toArray());
    }
}

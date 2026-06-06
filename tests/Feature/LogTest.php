<?php

namespace Tests\Feature;

use App\Models\Deployment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_log_belongs_to_a_deployment()
    {
        $deployment = Deployment::factory()->create();

        $log = $deployment->log;

        $this->assertEquals($log->id, $deployment->log->id);
    }
}

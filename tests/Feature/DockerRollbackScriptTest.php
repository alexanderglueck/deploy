<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Steps\DockerRollbackScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DockerRollbackScriptTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_verifies_retags_and_restarts()
    {
        $script = DockerRollbackScript::generate(Deployment::factory()->create(), [
            'app' => 'my-app',
            'sha' => 'abc123',
            'compose_file' => '/srv/server-config/apps/my-app/compose.yml',
        ]);

        // Re-verifies the image before touching anything.
        $this->assertStringContainsString("if ! docker image inspect 'my-app:abc123'", $script);
        $this->assertStringContainsString('no longer available', $script);

        $this->assertStringContainsString("docker tag 'my-app:abc123' 'my-app:latest'", $script);
        // The companion web image is only retagged when it exists.
        $this->assertStringContainsString("if docker image inspect 'my-app-web:abc123'", $script);
        $this->assertStringContainsString("docker tag 'my-app-web:abc123' 'my-app-web:latest'", $script);

        // The path is bound to a shell variable (set from the single-quoted
        // literal) and referenced by name, so it is never re-evaluated.
        $this->assertStringContainsString("COMPOSE_FILE='/srv/server-config/apps/my-app/compose.yml'", $script);
        $this->assertStringContainsString('docker compose -f "$COMPOSE_FILE" up -d', $script);
    }

    #[Test]
    public function a_hostile_compose_file_is_neither_substituted_nor_breaks_out()
    {
        $script = DockerRollbackScript::generate(Deployment::factory()->create(), [
            'app' => 'my-app',
            'sha' => 'abc123',
            'compose_file' => '$(id > /tmp/pwned)',
        ]);

        // Single-quoted by escapeshellarg on the assignment...
        $this->assertStringContainsString("COMPOSE_FILE='\$(id > /tmp/pwned)'", $script);
        // ...and never interpolated raw into a double-quoted line, which is
        // where bash would run the command substitution.
        $this->assertStringNotContainsString('via $(id', $script);
    }
}

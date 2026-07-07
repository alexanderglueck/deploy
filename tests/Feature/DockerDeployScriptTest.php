<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Steps\DockerDeployScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DockerDeployScriptTest extends TestCase
{
    use RefreshDatabase;

    private function deployment(array $attributes = []): Deployment
    {
        return Deployment::factory()->create([
            'repository' => 'jondoe/my.app',
            'ref' => 'refs/heads/main',
            'commit_sha' => 'abc123def456',
            ...$attributes,
        ]);
    }

    #[Test]
    public function it_follows_the_conventions()
    {
        $script = DockerDeployScript::generate($this->deployment(), []);

        // App name: repository name with dots turned into dashes.
        $this->assertStringContainsString("-t 'my-app':latest", $script);
        // Commit SHA tag for rollbacks.
        $this->assertStringContainsString("-t 'my-app:abc123def456'", $script);
        // Branch from the push ref.
        $this->assertStringContainsString("--branch 'main'", $script);
        // Server-side clone URL.
        $this->assertStringContainsString("'https://github.com/jondoe/my.app.git'", $script);
        // Compose path convention.
        $this->assertStringContainsString("'/srv/server-config/apps/my-app/compose.yml'", $script);
        // Build fallback order.
        $this->assertStringContainsString('deploy/build.sh', $script);
        $this->assertStringContainsString('Dockerfile.dist', $script);
        // Companion web image convention.
        $this->assertStringContainsString('docker/nginx.Dockerfile', $script);
        $this->assertStringContainsString("-t 'my-app'-web:latest", $script);
        // Temp dir hygiene.
        $this->assertStringContainsString('mktemp -d', $script);
        $this->assertStringContainsString('trap cleanup EXIT', $script);
    }

    #[Test]
    public function it_uses_a_token_when_configured()
    {
        config(['deploy.git_token' => 'token123']);

        $script = DockerDeployScript::generate($this->deployment(), []);

        $this->assertStringContainsString('https://x-access-token:token123@github.com/jondoe/my.app.git', $script);
    }

    #[Test]
    public function config_overrides_beat_conventions()
    {
        $script = DockerDeployScript::generate($this->deployment(), [
            'app' => 'custom',
            'compose_file' => '/somewhere/compose.yml',
            'target' => 'production',
        ]);

        $this->assertStringContainsString("-t 'custom':latest", $script);
        $this->assertStringContainsString("--target 'production'", $script);
        $this->assertStringContainsString("'/somewhere/compose.yml'", $script);
    }

    #[Test]
    public function a_manual_deploy_clones_the_default_branch_without_sha_tags()
    {
        $deployment = $this->deployment([
            'ref' => 'manual_deploy',
            'commit_sha' => null,
        ]);

        $script = DockerDeployScript::generate($deployment, []);

        $this->assertStringNotContainsString('--branch', $script);
        $this->assertStringContainsString("-t 'my-app':latest .", $script);
    }
}

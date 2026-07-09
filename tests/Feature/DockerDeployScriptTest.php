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
    public function a_rebuild_rollback_fetches_the_exact_commit()
    {
        $script = DockerDeployScript::generate($this->deployment(), ['checkout_sha' => true]);

        $this->assertStringNotContainsString('git clone', $script);
        $this->assertStringContainsString("git -C \"\$BUILD_DIR\" fetch -q --depth 1 origin 'abc123def456'", $script);
        $this->assertStringContainsString('checkout -q --detach FETCH_HEAD', $script);
        // Still tags the rebuilt image with the commit SHA.
        $this->assertStringContainsString("-t 'my-app:abc123def456'", $script);
    }

    #[Test]
    public function a_hostile_compose_file_is_neither_substituted_nor_breaks_out()
    {
        // WorkflowController validates the shape, but the generator must be
        // safe on its own — it also runs against snapshotted config on retry
        // and rollback.
        $script = DockerDeployScript::generate($this->deployment(), [
            'compose_file' => '$(id > /tmp/pwned)',
        ]);

        // Single-quoted by escapeshellarg on the assignment...
        $this->assertStringContainsString("COMPOSE_FILE='\$(id > /tmp/pwned)'", $script);
        $this->assertStringContainsString('docker compose -f "$COMPOSE_FILE" up -d', $script);
        // ...and never interpolated raw into a double-quoted line, which is
        // where bash would run the command substitution.
        $this->assertStringNotContainsString('via $(id', $script);
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

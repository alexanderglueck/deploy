<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Project;
use App\Steps\StepScriptFactory;
use App\Support\SecretMasker;
use App\Support\StepType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Project variables reaching the things that run.
 */
class ProjectVariableTest extends TestCase
{
    use RefreshDatabase;

    private function deploymentWith(array $variables): Deployment
    {
        $project = Project::factory()->create(['repository' => 'jondoe/my.app']);

        foreach ($variables as $key => $attributes) {
            $project->variables()->create(['key' => $key] + $attributes);
        }

        return Deployment::factory()->create([
            'project_id' => $project->id,
            'repository' => 'jondoe/my.app',
            'ref' => 'refs/heads/main',
            'commit_sha' => 'abc123def456',
        ]);
    }

    private function step(Deployment $deployment, string $type, array $config = []): DeploymentStep
    {
        $step = $deployment->steps()->create([
            'position' => 1,
            'type' => $type,
            'config' => $config,
            'status' => DeploymentStep::STATUS_PENDING,
        ]);

        return $step->fresh();
    }

    #[Test]
    public function variables_are_exported_for_every_step_type()
    {
        $deployment = $this->deploymentWith(['API_TOKEN' => ['value' => 's3cret-value']]);

        foreach ([StepType::DOCKER_DEPLOY, StepType::INLINE_SCRIPT, StepType::SCRIPT_FILE] as $type) {
            $config = match ($type) {
                StepType::INLINE_SCRIPT => ['script' => 'echo hi'],
                StepType::SCRIPT_FILE => ['path' => '/srv/bin/x.sh'],
                default => [],
            };

            $script = StepScriptFactory::scriptFor($this->step($deployment->fresh(), $type, $config));

            $this->assertStringContainsString("export API_TOKEN='s3cret-value'", $script, $type);
            $deployment->steps()->delete();
        }
    }

    #[Test]
    public function a_value_with_shell_metacharacters_cannot_break_out()
    {
        $deployment = $this->deploymentWith(['EVIL' => ['value' => "x'; rm -rf /; echo '"]]);

        $script = StepScriptFactory::scriptFor($this->step($deployment, StepType::INLINE_SCRIPT, ['script' => 'echo hi']));

        // Quoted as a single argument, so the injected command is inert text.
        $this->assertStringContainsString("export EVIL='x'\\''; rm -rf /; echo '\\''", $script);
        $this->assertStringNotContainsString("\nrm -rf /", $script);
    }

    #[Test]
    public function only_build_arg_variables_reach_docker_build()
    {
        $deployment = $this->deploymentWith([
            'VITE_PUSHER_APP_KEY' => ['value' => 'pk_live_abc', 'build_arg' => true],
            'DB_PASSWORD' => ['value' => 'not-for-the-image', 'build_arg' => false],
        ]);

        $script = StepScriptFactory::scriptFor($this->step($deployment, StepType::DOCKER_DEPLOY));

        // Referenced as a shell variable, so the value is not written into the
        // script -- and it is one argument, not two.
        $this->assertStringContainsString('--build-arg \'VITE_PUSHER_APP_KEY=\'"$VITE_PUSHER_APP_KEY"', $script);
        $this->assertStringNotContainsString('--build-arg \'DB_PASSWORD=\'', $script);
        // Both are still exported for the build script and compose to use.
        $this->assertStringContainsString('export DB_PASSWORD=', $script);
    }

    #[Test]
    public function the_masker_replaces_values_in_output()
    {
        $deployment = $this->deploymentWith([
            'SECRET' => ['value' => 'super-secret-token', 'masked' => true],
            'LONGER' => ['value' => 'super-secret-token-with-more', 'masked' => true],
            'PUBLIC_URL' => ['value' => 'https://example.com', 'masked' => false],
            'TINY' => ['value' => 'ab', 'masked' => true],
        ]);

        $masker = SecretMasker::for($deployment->project->variables);

        // The longer value wins, so no unique remainder is left behind.
        $this->assertSame(
            'using [masked] and [masked] ok',
            $masker->mask('using super-secret-token-with-more and super-secret-token ok')
        );
        // Unmasked variables stay readable, which is the point of the flag.
        $this->assertSame('at https://example.com', $masker->mask('at https://example.com'));
        // Too short to mask without shredding unrelated output.
        $this->assertSame('ab cab', $masker->mask('ab cab'));
    }

    #[Test]
    public function values_are_encrypted_at_rest_with_the_app_key()
    {
        $project = Project::factory()->create();
        $project->variables()->create(['key' => 'SECRET', 'value' => 'plaintext-value']);

        $raw = DB::table('project_variables')->where('key', 'SECRET')->value('value');

        // Nothing readable in the column...
        $this->assertStringNotContainsString('plaintext-value', $raw);
        // ...and it is Laravel's encrypter, i.e. tied to APP_KEY, not some
        // home-grown obfuscation.
        $this->assertSame('plaintext-value', Crypt::decryptString($raw));
        // The model still hands back the plaintext.
        $this->assertSame('plaintext-value', $project->variables()->sole()->value);
    }

    #[Test]
    public function a_project_without_variables_produces_an_unchanged_script()
    {
        $deployment = $this->deploymentWith([]);

        $script = StepScriptFactory::scriptFor($this->step($deployment, StepType::INLINE_SCRIPT, ['script' => 'echo hi']));

        $this->assertSame('echo hi', $script);
    }
}

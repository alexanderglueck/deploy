<?php

namespace Tests\Feature\Api;

use App\Execution\ExecutorFactory;
use App\Models\Deployment;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsToken(): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function projectWithWorkflow(User $user, array $attributes = []): Project
    {
        $server = Server::factory()->local()->create(['team_id' => $user->currentTeam->id]);
        $project = Project::factory()->create([
            'team_id' => $user->currentTeam->id,
            'repository' => 'jondoe/deploy',
            'default_branch' => 'master',
            ...$attributes,
        ]);
        Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
        ]);

        return $project;
    }

    #[Test]
    public function it_requires_a_token()
    {
        $this->getJson('/api/v1/projects')->assertUnauthorized();
    }

    #[Test]
    public function it_creates_a_project_and_returns_the_secret_once()
    {
        $this->actingAsToken();

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'notes',
            'repository' => 'gdev-projects/notes',
            'default_branch' => 'master',
        ])->assertCreated();

        $data = $response->json('data');

        $this->assertSame('notes', $data['name']);
        $this->assertNotEmpty($data['deploy_endpoint']);
        // The secret is only ever disclosed here, so a caller can configure the
        // git host's webhook without a second round trip.
        $this->assertNotEmpty($data['webhook_secret']);
        $this->assertStringContainsString("/api/deploy/{$data['deploy_endpoint']}", $data['deploy_url']);

        $this->assertDatabaseHas('projects', ['name' => 'notes', 'repository' => 'gdev-projects/notes']);
    }

    #[Test]
    public function it_never_leaks_the_secret_on_reads()
    {
        $user = $this->actingAsToken();
        $project = $this->projectWithWorkflow($user);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonMissingPath('data.0.webhook_secret');

        $this->getJson("/api/v1/projects/{$project->ulid}")
            ->assertOk()
            ->assertJsonMissingPath('data.webhook_secret');
    }

    #[Test]
    public function it_rejects_a_malformed_repository()
    {
        $this->actingAsToken();

        $this->postJson('/api/v1/projects', [
            'name' => 'bad',
            'repository' => 'not a repo name',
        ])->assertJsonValidationErrorFor('repository');
    }

    #[Test]
    public function it_hides_projects_belonging_to_another_team()
    {
        $this->actingAsToken();
        $other = User::factory()->withPersonalTeam()->create();
        $foreign = Project::factory()->create(['team_id' => $other->currentTeam->id]);

        $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(0, 'data');
        // 404 rather than 403: a token should not learn that the project exists.
        $this->getJson("/api/v1/projects/{$foreign->ulid}")->assertNotFound();
        $this->postJson("/api/v1/projects/{$foreign->ulid}/deploy")->assertNotFound();
    }

    #[Test]
    public function it_updates_a_project()
    {
        $user = $this->actingAsToken();
        $project = $this->projectWithWorkflow($user);

        $this->patchJson("/api/v1/projects/{$project->ulid}", ['default_branch' => 'main'])
            ->assertOk()
            ->assertJsonPath('data.default_branch', 'main');
    }

    #[Test]
    public function it_registers_a_project_hosted_on_another_git_host()
    {
        $this->actingAsToken();

        // The one-call registration a GitLab-hosted project needs: without the
        // host it would be cloned from the installation default and fail.
        $response = $this->postJson('/api/v1/projects', [
            'name' => 'notes',
            'repository' => 'gdev-projects/notes',
            'git_base' => 'https://gitlab.com',
            'git_token_user' => 'oauth2',
            'git_token' => 'glpat-secret',
        ])
            ->assertCreated()
            ->assertJsonPath('data.git_base', 'https://gitlab.com')
            ->assertJsonPath('data.git_token_user', 'oauth2')
            ->assertJsonPath('data.has_git_token', true);

        // Write-only, exactly like webhook_secret.
        $this->assertArrayNotHasKey('git_token', $response->json('data'));
        $this->assertStringNotContainsString('glpat-secret', $response->getContent());

        $project = Project::query()->where('ulid', $response->json('data.ulid'))->sole();
        $this->assertSame('glpat-secret', $project->git_token);
    }

    #[Test]
    public function omitting_the_git_token_keeps_it_and_null_clears_it()
    {
        $user = $this->actingAsToken();
        $project = $this->projectWithWorkflow($user, ['git_token' => 'glpat-secret']);

        // A patch about something else must not disarm the clone credentials.
        $this->patchJson("/api/v1/projects/{$project->ulid}", ['default_branch' => 'main'])
            ->assertOk()
            ->assertJsonPath('data.has_git_token', true);
        $this->assertSame('glpat-secret', $project->fresh()->git_token);

        $this->patchJson("/api/v1/projects/{$project->ulid}", ['git_token' => null])
            ->assertOk()
            ->assertJsonPath('data.has_git_token', false);
        $this->assertNull($project->fresh()->git_token);
    }

    #[Test]
    public function it_rejects_a_git_host_that_is_more_than_a_host()
    {
        $this->actingAsToken();

        foreach (['https://user:pw@gitlab.com', 'gitlab.com', 'https://gitlab.com/;id', 'file:///etc'] as $base) {
            $this->postJson('/api/v1/projects', ['name' => 'x', 'git_base' => $base])
                ->assertStatus(422)
                ->assertJsonValidationErrors('git_base');
        }
    }

    #[Test]
    public function it_triggers_a_deployment_on_the_default_branch()
    {
        ExecutorFactory::fake();

        $user = $this->actingAsToken();
        $project = $this->projectWithWorkflow($user);

        $response = $this->postJson("/api/v1/projects/{$project->ulid}/deploy")
            ->assertAccepted();

        $this->assertDatabaseHas('deployments', [
            'project_id' => $project->id,
            'ref' => 'refs/heads/master',
            'repository' => 'jondoe/deploy',
            // triggered_by is a users FK, so an API deploy is attributed to the
            // token's owner exactly like a manual one from the UI.
            'triggered_by' => $user->id,
        ]);

        $ulid = $response->json('data.ulid');
        $this->getJson("/api/v1/deployments/{$ulid}")
            ->assertOk()
            ->assertJsonPath('data.triggered_by', $user->name)
            ->assertJsonPath('data.ref', 'refs/heads/master');
    }

    #[Test]
    public function it_refuses_to_deploy_a_ref_no_workflow_wants()
    {
        ExecutorFactory::fake();

        $user = $this->actingAsToken();
        $project = $this->projectWithWorkflow($user);

        // The webhook path silently ignores these; here a human is waiting, so
        // saying so is more useful than a 200 that does nothing.
        $this->postJson("/api/v1/projects/{$project->ulid}/deploy", [
            'ref' => 'refs/heads/some-feature-branch',
        ])->assertStatus(422);

        $this->assertDatabaseCount('deployments', 0);
    }

    #[Test]
    public function it_refuses_to_deploy_a_project_without_a_repository()
    {
        $user = $this->actingAsToken();
        $project = $this->projectWithWorkflow($user, ['repository' => null]);

        $this->postJson("/api/v1/projects/{$project->ulid}/deploy")->assertStatus(422);
    }

    #[Test]
    public function it_supersedes_an_earlier_pending_deployment()
    {
        // The queue is synchronous in tests, so without faking it the first
        // deployment would already be processed and there would be nothing
        // pending left to supersede.
        Queue::fake();

        $user = $this->actingAsToken();
        $project = $this->projectWithWorkflow($user);

        $this->postJson("/api/v1/projects/{$project->ulid}/deploy")->assertAccepted();
        $this->postJson("/api/v1/projects/{$project->ulid}/deploy")->assertAccepted();

        // Same behaviour as two pushes in a row: the older queued work is
        // canceled instead of deploying a stale commit afterwards.
        $this->assertSame(1, Deployment::whereNotNull('canceled_at')->count());
    }

    #[Test]
    public function deploy_url_uses_the_configured_app_url_not_the_request_host()
    {
        // This API is normally called in-network, so the request host is something
        // like http://deploy. The returned URL is pasted straight into a git host's
        // webhook config, so it has to be the public one.
        config(['app.url' => 'https://deployer.example.com']);

        $this->actingAsToken();

        $this->postJson('/api/v1/projects', ['name' => 'x', 'repository' => 'jondoe/x'])
            ->assertCreated()
            ->assertJsonPath('data.deploy_url', fn ($url) => str_starts_with($url, 'https://deployer.example.com/api/deploy/'));
    }
}

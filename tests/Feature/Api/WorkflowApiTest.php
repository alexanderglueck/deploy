<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Support\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($this->user);
        $this->server = Server::factory()->local()->create(['team_id' => $this->user->currentTeam->id]);
        $this->project = Project::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'repository' => 'jondoe/deploy',
            'default_branch' => 'master',
        ]);
    }

    #[Test]
    public function it_creates_a_default_branch_workflow()
    {
        $this->postJson("/api/v1/projects/{$this->project->ulid}/workflows", [
            'server' => $this->server->ulid,
        ])
            ->assertCreated()
            ->assertJsonPath('data.event', Event::PUSH)
            ->assertJsonPath('data.branch', null)
            // null is meaningful, so it is reported explicitly rather than as absence.
            ->assertJsonPath('data.branch_mode', 'default-branch')
            ->assertJsonPath('data.server', $this->server->ulid);

        $this->assertSame(1, $this->project->workflows()->count());
    }

    #[Test]
    public function a_created_workflow_makes_a_push_deploy_instead_of_being_ignored()
    {
        // The whole point: without a workflow the trigger is accepted and dropped.
        $payload = ['event' => 'push', 'ref' => 'refs/heads/master', 'repo' => 'jondoe/deploy'];
        $headers = ['X-Deploy-Secret' => $this->project->webhook_secret];

        $this->postJson("/api/deploy/{$this->project->deploy_endpoint}", $payload, $headers)
            ->assertOk()
            ->assertSee('IGNORED');

        $this->postJson("/api/v1/projects/{$this->project->ulid}/workflows", [
            'server' => $this->server->ulid,
        ])->assertCreated();

        $this->postJson("/api/deploy/{$this->project->deploy_endpoint}", $payload, $headers)
            ->assertOk()
            ->assertSee('OK');
    }

    #[Test]
    public function it_accepts_any_branch_and_exact_branch_modes()
    {
        $this->postJson("/api/v1/projects/{$this->project->ulid}/workflows",
            ['server' => $this->server->ulid, 'branch' => '*'])
            ->assertCreated()->assertJsonPath('data.branch_mode', 'any');

        $this->postJson("/api/v1/projects/{$this->project->ulid}/workflows",
            ['server' => $this->server->ulid, 'branch' => 'develop'])
            ->assertCreated()->assertJsonPath('data.branch_mode', 'exact');
    }

    #[Test]
    public function it_rejects_an_unsupported_event()
    {
        $this->postJson("/api/v1/projects/{$this->project->ulid}/workflows",
            ['server' => $this->server->ulid, 'event' => 'not-an-event'])
            ->assertStatus(422);
    }

    #[Test]
    public function it_hides_servers_from_other_teams()
    {
        $foreign = Server::factory()->local()->create([
            'team_id' => User::factory()->withPersonalTeam()->create()->currentTeam->id,
        ]);

        // 404, not 422: the token should not learn that this server exists.
        $this->postJson("/api/v1/projects/{$this->project->ulid}/workflows", ['server' => $foreign->ulid])
            ->assertNotFound();
    }

    #[Test]
    public function it_lists_and_deletes_workflows()
    {
        $ulid = $this->postJson("/api/v1/projects/{$this->project->ulid}/workflows",
            ['server' => $this->server->ulid])->json('data.ulid');

        $this->getJson("/api/v1/projects/{$this->project->ulid}/workflows")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/projects/{$this->project->ulid}/workflows/{$ulid}")->assertOk();
        $this->assertSame(0, $this->project->workflows()->count());
    }
}

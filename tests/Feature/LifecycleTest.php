<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withPersonalTeam()->create();
    }

    #[Test]
    public function a_server_can_be_updated()
    {
        $server = Server::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->actingAs($this->user)->put(route('server.update', $server), [
            'name' => 'Renamed',
            'user' => 'deploy',
            'ip' => '10.0.0.9',
            'port' => 2222,
        ])->assertSessionHasNoErrors();

        $server->refresh();
        $this->assertSame('Renamed', $server->name);
        $this->assertSame('10.0.0.9', $server->ip);
        $this->assertSame(2222, (int) $server->port);
    }

    #[Test]
    public function a_server_with_workflows_cannot_be_deleted()
    {
        $server = Server::factory()->create(['team_id' => $this->user->currentTeam->id]);
        Workflow::factory()->create(['server_id' => $server->id]);

        $this->actingAs($this->user)->delete(route('server.destroy', $server))
            ->assertSessionHasErrors(['server']);

        $this->assertNotNull($server->fresh());
    }

    #[Test]
    public function an_unused_server_can_be_deleted()
    {
        $server = Server::factory()->create(['team_id' => $this->user->currentTeam->id]);

        $this->actingAs($this->user)->delete(route('server.destroy', $server))
            ->assertRedirect(route('server.index'));

        $this->assertNull($server->fresh());
    }

    #[Test]
    public function rotating_a_keypair_replaces_it_and_requires_setup_again()
    {
        $server = Server::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $oldKey = $server->public_key;

        $this->actingAs($this->user)->post(route('server.key.store', $server))
            ->assertRedirect(route('server.show', $server));

        $server->refresh();
        $this->assertNotSame($oldKey, $server->public_key);
        $this->assertNull($server->setup_at);
    }

    #[Test]
    public function a_local_server_has_no_keypair_to_rotate()
    {
        $server = Server::factory()->local()->create(['team_id' => $this->user->currentTeam->id]);

        $this->actingAs($this->user)->post(route('server.key.store', $server))
            ->assertUnprocessable();
    }

    #[Test]
    public function a_project_can_be_deleted_with_its_history()
    {
        $project = Project::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $workflow = Workflow::factory()->create(['project_id' => $project->id]);

        $this->actingAs($this->user)->delete(route('project.destroy', $project))
            ->assertRedirect(route('dashboard'));

        $this->assertNull($project->fresh());
        $this->assertNull($workflow->fresh());
    }

    #[Test]
    public function the_webhook_secret_can_be_rotated()
    {
        $project = Project::factory()->create(['team_id' => $this->user->currentTeam->id]);
        $oldSecret = $project->webhook_secret;

        $this->actingAs($this->user)->post(route('project.webhook-secret.store', $project))
            ->assertRedirect(route('project.show', $project));

        $this->assertNotSame($oldSecret, $project->fresh()->webhook_secret);
    }

    #[Test]
    public function lifecycle_actions_are_team_scoped()
    {
        $project = Project::factory()->create();
        $server = Server::factory()->create();

        $this->actingAs($this->user)->delete(route('project.destroy', $project))->assertNotFound();
        $this->actingAs($this->user)->delete(route('server.destroy', $server))->assertNotFound();
        $this->actingAs($this->user)->post(route('server.key.store', $server))->assertNotFound();
        $this->actingAs($this->user)->post(route('project.webhook-secret.store', $project))->assertNotFound();
    }
}

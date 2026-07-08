<?php

namespace Tests\Feature;

use App\Execution\ExecutionResult;
use App\Execution\ExecutorFactory;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DockerControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake Docker: route each CLI command to canned output.
     */
    private function fakeDocker(array $containers = [], array $images = []): void
    {
        ExecutorFactory::fakeUsing(function (string $script) use ($containers, $images) {
            if (str_contains($script, 'docker ps')) {
                return collect($containers)->map(fn ($c) => json_encode($c))->implode("\n");
            }
            if (str_contains($script, 'docker images')) {
                return collect($images)->map(fn ($i) => json_encode($i))->implode("\n");
            }
            if (str_contains($script, 'docker inspect')) {
                return json_encode(['Name' => '/web', 'Config' => ['Image' => 'nginx'], 'State' => ['Status' => 'running', 'Running' => true, 'ExitCode' => 0], 'RestartCount' => 0]);
            }

            return ''; // control actions succeed
        });
    }

    #[Test]
    public function the_dashboard_lists_containers_and_images()
    {
        $this->fakeDocker(
            containers: [['ID' => 'abc', 'Names' => 'web', 'Image' => 'nginx:latest', 'State' => 'running', 'Status' => 'Up 3 hours', 'Ports' => '']],
            images: [['ID' => 'sha256:abc', 'Repository' => 'nginx', 'Tag' => 'latest', 'Size' => '187MB', 'CreatedSince' => '3 days ago']],
        );

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->local()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->get(route('docker.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Docker/Index')
                ->where('server.ulid', $server->ulid)
                ->has('containers', 1)
                ->where('containers.0.name', 'web')
                ->has('images', 1)
                ->where('error', null)
            );
    }

    #[Test]
    public function a_daemon_error_becomes_an_error_prop_not_a_500()
    {
        ExecutorFactory::fakeUsing(fn () => new ExecutionResult(1, 'Cannot connect to the Docker daemon'));

        $user = User::factory()->withPersonalTeam()->create();
        Server::factory()->local()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->get(route('docker.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('error', 'Cannot connect to the Docker daemon'));
    }

    #[Test]
    public function the_dashboard_is_empty_without_servers()
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('docker.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('server', null)->has('servers', 0));
    }

    #[Test]
    public function a_container_action_runs_when_the_container_exists()
    {
        $this->fakeDocker(containers: [['ID' => 'abc', 'Names' => 'web', 'Image' => 'nginx', 'State' => 'running', 'Status' => 'Up']]);

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->from(route('docker.index'))
            ->post(route('container.action', [$server, 'web']), ['action' => 'restart'])
            ->assertRedirect(route('docker.index'))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function an_action_on_an_unknown_container_is_rejected()
    {
        $this->fakeDocker(containers: [['ID' => 'abc', 'Names' => 'web', 'State' => 'running', 'Image' => 'x', 'Status' => 'Up']]);

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->post(route('container.action', [$server, 'ghost']), ['action' => 'restart'])
            ->assertNotFound();
    }

    #[Test]
    public function an_invalid_action_is_rejected()
    {
        $this->fakeDocker();

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->post(route('container.action', [$server, 'web']), ['action' => 'exec'])
            ->assertSessionHasErrors('action');
    }

    #[Test]
    public function container_logs_are_returned_as_json()
    {
        ExecutorFactory::fakeUsing(fn (string $script) => str_contains($script, 'docker logs') ? "line one\nline two" : '');

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->get(route('container.logs', [$server, 'web']))
            ->assertOk()
            ->assertJson(['logs' => "line one\nline two"]);
    }

    #[Test]
    public function docker_endpoints_are_team_scoped()
    {
        $this->fakeDocker();

        $user = User::factory()->withPersonalTeam()->create();
        $otherServer = Server::factory()->create();

        $this->actingAs($user)->get(route('container.show', [$otherServer, 'web']))->assertNotFound();
        $this->actingAs($user)->get(route('container.logs', [$otherServer, 'web']))->assertNotFound();
        $this->actingAs($user)->post(route('container.action', [$otherServer, 'web']), ['action' => 'stop'])->assertNotFound();
    }
}

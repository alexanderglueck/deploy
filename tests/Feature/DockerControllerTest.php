<?php

namespace Tests\Feature;

use App\Execution\ExecutionResult;
use App\Execution\ExecutorFactory;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ContainerAction;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DockerControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake Docker: route each CLI command to canned output.
     */
    private function fakeDocker(array $containers = [], array $images = [], array $stats = [], array $disk = []): void
    {
        ExecutorFactory::fakeUsing(function (string $script) use ($containers, $images, $stats, $disk) {
            if (str_contains($script, 'docker ps')) {
                return collect($containers)->map(fn ($c) => json_encode($c))->implode("\n");
            }
            if (str_contains($script, 'docker images')) {
                return collect($images)->map(fn ($i) => json_encode($i))->implode("\n");
            }
            if (str_contains($script, 'docker stats')) {
                return collect($stats)->map(fn ($s) => json_encode($s))->implode("\n");
            }
            if (str_contains($script, 'docker system df')) {
                return collect($disk)->map(fn ($d) => json_encode($d))->implode("\n");
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
                ->missing('stats')
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
    public function an_unknown_server_ulid_falls_back_to_the_first_server()
    {
        $this->fakeDocker();

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->local()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->get(route('docker.index', ['server' => '01hzzzzzzzzzzzzzzzzzzzzzzz']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('server.ulid', $server->ulid));
    }

    #[Test]
    public function stats_load_as_a_partial_reload()
    {
        $this->fakeDocker(
            containers: [['ID' => 'abc', 'Names' => 'web', 'Image' => 'nginx', 'State' => 'running', 'Status' => 'Up']],
            stats: [['Name' => 'web', 'CPUPerc' => '1.00%', 'MemUsage' => '50MiB / 1GiB', 'MemPerc' => '5.00%']],
            disk: [['Type' => 'Images', 'TotalCount' => 3, 'Size' => '1GB', 'Reclaimable' => '500MB']],
        );

        $user = User::factory()->withPersonalTeam()->create();
        Server::factory()->local()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->get(route('docker.index'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) (new HandleInertiaRequests)->version(request()),
                'X-Inertia-Partial-Component' => 'Docker/Index',
                'X-Inertia-Partial-Data' => 'stats',
            ])
            ->assertOk()
            ->assertJsonPath('props.stats.containers.web.cpu', '1.00%')
            ->assertJsonPath('props.stats.disk.0.size', '1GB')
            ->assertJsonPath('props.stats.error', null);
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

        $this->assertDatabaseHas('container_actions', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'container' => 'web',
            'action' => 'restart',
            'successful' => true,
        ]);
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
    public function an_action_when_the_daemon_is_down_flashes_an_error_instead_of_a_500()
    {
        ExecutorFactory::fakeUsing(fn () => new ExecutionResult(1, 'Cannot connect to the Docker daemon'));

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->from(route('docker.index'))
            ->post(route('container.action', [$server, 'web']), ['action' => 'restart'])
            ->assertRedirect(route('docker.index'))
            ->assertSessionHas('flash.bannerStyle', 'danger');
    }

    #[Test]
    public function an_executor_exception_during_an_action_flashes_and_is_recorded()
    {
        // A timed-out local process raises rather than returning an exit code.
        ExecutorFactory::fakeUsing(function (string $script) {
            if (str_contains($script, 'docker ps')) {
                return json_encode(['ID' => 'abc', 'Names' => 'web', 'Image' => 'x', 'State' => 'running', 'Status' => 'Up']);
            }

            throw new RuntimeException('The process exceeded the timeout of 60 seconds.');
        });

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->from(route('docker.index'))
            ->post(route('container.action', [$server, 'web']), ['action' => 'stop'])
            ->assertRedirect(route('docker.index'))
            ->assertSessionHas('flash.bannerStyle', 'danger');

        $this->assertDatabaseHas('container_actions', [
            'container' => 'web',
            'action' => 'stop',
            'successful' => false,
        ]);
    }

    #[Test]
    public function recent_actions_are_listed_on_the_dashboard()
    {
        $this->fakeDocker();

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->local()->create(['team_id' => $user->currentTeam->id]);

        ContainerAction::create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'container' => 'web',
            'action' => 'restart',
            'successful' => true,
        ]);

        $this->actingAs($user)->get(route('docker.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('actions', 1)
                ->where('actions.0.container', 'web')
                ->where('actions.0.user_name', $user->name)
            );
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
            ->assertJson(['logs' => "line one\nline two", 'error' => null]);
    }

    #[Test]
    public function a_log_failure_is_returned_as_an_error_not_content()
    {
        ExecutorFactory::fakeUsing(fn (string $script) => str_contains($script, 'docker logs')
            ? new ExecutionResult(1, 'Error: No such container: web')
            : '');

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->get(route('container.logs', [$server, 'web']))
            ->assertOk()
            ->assertJson(['logs' => '', 'error' => 'Error: No such container: web']);
    }

    #[Test]
    public function log_filters_reach_the_docker_command()
    {
        $scripts = [];
        ExecutorFactory::fakeUsing(function (string $script) use (&$scripts) {
            $scripts[] = $script;

            return '';
        });

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->get(route('container.logs', [$server, 'web']).'?tail=200&since=1h&timestamps=0')
            ->assertOk();

        $command = collect($scripts)->first(fn ($s) => str_contains($s, 'docker logs'));

        $this->assertStringContainsString('--tail 200', $command);
        $this->assertStringContainsString('--since 1h', $command);
        $this->assertStringNotContainsString('--timestamps', $command);
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

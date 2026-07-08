<?php

namespace Tests\Feature;

use App\Execution\ExecutorFactory;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerConnectionTestControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_successful_test_marks_the_server_as_set_up()
    {
        $executor = ExecutorFactory::fake(output: "ok\n");

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create([
            'team_id' => $user->currentTeam->id,
            'setup_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('server.test.store', $server))
            ->assertRedirect(route('server.show', $server))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($server->fresh()->setup_at);
        $this->assertSame(['echo ok'], $executor->scripts);
    }

    #[Test]
    public function a_failed_test_reports_the_error_and_does_not_mark_setup()
    {
        ExecutorFactory::fake(exitCode: 255, output: 'Connection refused');

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create([
            'team_id' => $user->currentTeam->id,
            'setup_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('server.test.store', $server))
            ->assertSessionHasErrors(['connection']);

        $this->assertNull($server->fresh()->setup_at);
    }

    #[Test]
    public function an_already_set_up_server_keeps_its_original_timestamp()
    {
        ExecutorFactory::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $setupAt = now()->subDays(3)->startOfSecond();
        $server = Server::factory()->create([
            'team_id' => $user->currentTeam->id,
            'setup_at' => $setupAt,
        ]);

        $this->actingAs($user)->post(route('server.test.store', $server));

        $this->assertEquals($setupAt, $server->fresh()->setup_at);
    }

    #[Test]
    public function a_local_server_needs_no_test()
    {
        $executor = ExecutorFactory::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->local()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)
            ->post(route('server.test.store', $server))
            ->assertRedirect(route('server.show', $server));

        $this->assertSame([], $executor->scripts);
    }

    #[Test]
    public function a_user_cannot_test_another_teams_server()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $server = Server::factory()->create();

        $this->actingAs($outsider)
            ->post(route('server.test.store', $server))
            ->assertNotFound();
    }
}

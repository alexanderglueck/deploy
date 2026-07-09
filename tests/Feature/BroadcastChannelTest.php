<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Give the auth endpoint a broadcaster that can sign responses. Channel
     * callbacks bind to the default driver at boot ("null" under phpunit),
     * so they must be re-registered on the reverb driver after the switch.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        require base_path('routes/channels.php');
    }

    #[Test]
    public function a_team_member_may_join_the_team_channel()
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-team.'.$user->currentTeam->ulid,
                'socket_id' => '123.456',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    #[Test]
    public function another_teams_channel_is_denied()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-team.'.$other->currentTeam->ulid,
                'socket_id' => '123.456',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function an_unknown_team_is_denied()
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-team.01hzzzzzzzzzzzzzzzzzzzzzzz',
                'socket_id' => '123.456',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function guests_are_denied()
    {
        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-team.whatever',
            'socket_id' => '123.456',
        ])->assertStatus(403);
    }
}

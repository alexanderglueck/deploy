<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\Team;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_server_belongs_to_a_team()
    {
        $team = Team::factory()->create();

        $server = Server::factory()->create([
            'team_id' => $team->id,
        ]);

        $this->assertEquals($team->id, $server->team->id);
        $this->assertCount(1, $team->servers);
    }

    #[Test]
    public function an_ssh_server_gets_its_own_keypair_on_creation()
    {
        $server = Server::factory()->create();

        $this->assertStringContainsString('OPENSSH PRIVATE KEY', $server->private_key);
        $this->assertStringStartsWith('ssh-ed25519 ', $server->public_key);

        // Each server gets a distinct key.
        $other = Server::factory()->create();
        $this->assertNotSame($server->private_key, $other->private_key);

        // The private key is never serialized.
        $this->assertArrayNotHasKey('private_key', $server->toArray());
    }

    #[Test]
    public function a_local_server_needs_no_keypair_or_setup()
    {
        $server = Server::factory()->local()->create(['setup_at' => null]);

        $this->assertNull($server->private_key);
        $this->assertTrue($server->isLocal());
        $this->assertTrue($server->isSetUp());
    }

    #[Test]
    public function a_server_has_many_workflows()
    {
        $server = Server::factory()->create();

        $workflow = Workflow::factory()->create([
            'server_id' => $server->id,
        ]);

        $this->assertEquals($server->id, $workflow->server_id);
        $this->assertCount(1, $server->workflows);
    }
}

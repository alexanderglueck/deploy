<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectVariableApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($this->user);
        $this->project = Project::factory()->create(['team_id' => $this->user->currentTeam->id]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/projects/{$this->project->ulid}/variables".$suffix;
    }

    #[Test]
    public function it_stores_variables_without_ever_returning_their_values()
    {
        $response = $this->putJson($this->url(), ['variables' => [
            ['key' => 'VITE_PUSHER_APP_KEY', 'value' => 'pk_live_abc', 'build_arg' => true, 'masked' => false],
            ['key' => 'DB_PASSWORD', 'value' => 'hunter2-and-then-some'],
        ]])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.key', 'DB_PASSWORD')
            ->assertJsonPath('data.0.has_value', true)
            // Masked by default; build args are not.
            ->assertJsonPath('data.0.masked', true)
            ->assertJsonPath('data.0.build_arg', false)
            ->assertJsonPath('data.1.key', 'VITE_PUSHER_APP_KEY')
            ->assertJsonPath('data.1.build_arg', true)
            ->assertJsonPath('data.1.masked', false);

        // The whole point of write-only: no response ever carries a value.
        $this->assertStringNotContainsString('hunter2', $response->getContent());
        $this->assertStringNotContainsString('pk_live_abc', $response->getContent());
        $this->assertStringNotContainsString('hunter2', $this->getJson($this->url())->getContent());

        $this->assertSame('hunter2-and-then-some', $this->project->variables()->where('key', 'DB_PASSWORD')->sole()->value);
    }

    #[Test]
    public function a_blank_value_keeps_the_stored_one()
    {
        $this->putJson($this->url(), ['variables' => [
            ['key' => 'DB_PASSWORD', 'value' => 'hunter2-and-then-some'],
        ]])->assertOk();

        // The editor never receives the value back, so re-submitting the row to
        // flip a flag must not blank it.
        $this->putJson($this->url(), ['variables' => [
            ['key' => 'DB_PASSWORD', 'value' => '', 'masked' => false],
        ]])->assertOk()->assertJsonPath('data.0.masked', false);

        $stored = $this->project->variables()->where('key', 'DB_PASSWORD')->sole();
        $this->assertSame('hunter2-and-then-some', $stored->value);
        $this->assertFalse($stored->masked);
    }

    #[Test]
    public function replacing_the_list_removes_what_is_absent()
    {
        $this->putJson($this->url(), ['variables' => [
            ['key' => 'ONE', 'value' => 'first-value'],
            ['key' => 'TWO', 'value' => 'second-value'],
        ]])->assertOk()->assertJsonCount(2, 'data');

        $this->putJson($this->url(), ['variables' => [['key' => 'TWO', 'value' => '']]])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'TWO');

        $this->assertSame(['TWO'], $this->project->variables()->pluck('key')->all());

        // An empty list clears them all -- 'present' not 'required'.
        $this->putJson($this->url(), ['variables' => []])->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_rejects_keys_that_are_not_shell_identifiers()
    {
        foreach (['9LIVES', 'has-dash', 'has space', 'has$dollar', ''] as $key) {
            $this->putJson($this->url(), ['variables' => [['key' => $key, 'value' => 'x']]])
                ->assertStatus(422)
                ->assertJsonValidationErrors('variables.0.key');
        }
    }

    #[Test]
    public function it_rejects_names_that_would_hijack_the_deployment()
    {
        foreach (['PATH', 'LD_PRELOAD', 'DOCKER_HOST', 'BASH_ENV', 'docker_host'] as $key) {
            $this->putJson($this->url(), ['variables' => [['key' => $key, 'value' => 'x']]])
                ->assertStatus(422)
                ->assertJsonValidationErrors('variables.0.key');
        }

        $this->assertSame(0, $this->project->variables()->count());
    }

    #[Test]
    public function it_rejects_duplicate_keys()
    {
        $this->putJson($this->url(), ['variables' => [
            ['key' => 'SAME', 'value' => 'one'],
            ['key' => 'SAME', 'value' => 'two'],
        ]])->assertStatus(422);
    }

    #[Test]
    public function it_deletes_a_single_variable()
    {
        $this->putJson($this->url(), ['variables' => [
            ['key' => 'KEEP', 'value' => 'keep-this-value'],
            ['key' => 'DROP', 'value' => 'drop-this-value'],
        ]])->assertOk();

        $drop = $this->project->variables()->where('key', 'DROP')->sole();

        $this->deleteJson($this->url("/{$drop->ulid}"))->assertOk();
        $this->assertSame(['KEEP'], $this->project->variables()->pluck('key')->all());
    }

    #[Test]
    public function it_hides_another_teams_project()
    {
        $foreign = Project::factory()->create([
            'team_id' => User::factory()->withPersonalTeam()->create()->currentTeam->id,
        ]);

        $this->getJson("/api/v1/projects/{$foreign->ulid}/variables")->assertNotFound();
        $this->putJson("/api/v1/projects/{$foreign->ulid}/variables", ['variables' => [
            ['key' => 'X', 'value' => 'y'],
        ]])->assertNotFound();

        $this->assertSame(0, $foreign->variables()->count());
    }
}

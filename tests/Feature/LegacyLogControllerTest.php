<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableWithLogs(): string
    {
        $dir = sys_get_temp_dir().'/legacy-logs-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/my-app.log', "deploy one\ndeploy two\n");
        file_put_contents($dir.'/secret.txt', 'not a log');
        config(['deploy.legacy_logs_path' => $dir]);

        return $dir;
    }

    #[Test]
    public function it_is_hidden_when_not_configured()
    {
        config(['deploy.legacy_logs_path' => null]);
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('legacy-log.index'))->assertNotFound();
    }

    #[Test]
    public function it_lists_only_log_files()
    {
        $this->enableWithLogs();
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('legacy-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LegacyLog/Index')
                ->has('logs', 1)
                ->where('logs.0.name', 'my-app.log')
            );
    }

    #[Test]
    public function it_shows_a_log()
    {
        $this->enableWithLogs();
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('legacy-log.show', 'my-app.log'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LegacyLog/Show')
                ->where('name', 'my-app.log')
                ->where('content', "deploy one\ndeploy two\n")
                ->where('truncated', false)
            );
    }

    #[Test]
    public function traversal_and_non_log_files_are_rejected()
    {
        $dir = $this->enableWithLogs();
        $user = User::factory()->withPersonalTeam()->create();

        // Route constraint rejects anything not shaped like a log filename.
        $this->actingAs($user)->get('/legacy-logs/secret.txt')->assertNotFound();
        $this->actingAs($user)->get('/legacy-logs/..%2F..%2Fetc%2Fpasswd')->assertNotFound();

        // Shaped like a log, but pointing outside the directory.
        file_put_contents(dirname($dir).'/outside.log', 'nope');
        symlink(dirname($dir).'/outside.log', $dir.'/link.log');
        $this->actingAs($user)->get(route('legacy-log.show', 'link.log'))->assertNotFound();

        $this->actingAs($user)->get(route('legacy-log.show', 'missing.log'))->assertNotFound();
    }

    #[Test]
    public function guests_cannot_view_logs()
    {
        $this->enableWithLogs();

        $this->get(route('legacy-log.index'))->assertRedirect('/login');
    }
}

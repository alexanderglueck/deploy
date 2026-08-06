<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_project_can_be_shown()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->get(route('project.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Project/Show')
                ->where('project.name', $project->name)
            );
    }

    #[Test]
    public function a_project_has_a_create_view()
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('project.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Project/Create'));
    }

    #[Test]
    public function a_project_can_be_created()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->make();

        $this->actingAs($user)->post(route('project.store'), ['name' => $project->name])
            ->assertRedirect(route('team.show'));

        $this->assertDatabaseHas('projects', [
            'team_id' => $user->currentTeam->id,
            'name' => $project->name,
        ]);
    }

    #[Test]
    public function a_project_can_be_updated()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->put(route('project.update', $project), [
            'name' => 'Renamed',
            'repository' => 'jondoe/deploy',
            'default_branch' => 'develop',
        ])->assertRedirect(route('project.show', $project));

        $project->refresh();
        $this->assertSame('Renamed', $project->name);
        $this->assertSame('jondoe/deploy', $project->repository);
        $this->assertSame('develop', $project->default_branch);
    }

    #[Test]
    public function a_git_source_can_be_configured_and_the_token_survives_unrelated_edits()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->put(route('project.update', $project), [
            'name' => $project->name,
            'git_base' => 'https://gitlab.com',
            'git_token_user' => 'oauth2',
            'git_token' => 'secret-token',
        ])->assertRedirect(route('project.show', $project));

        $project->refresh();
        $this->assertSame('https://gitlab.com', $project->git_base);
        $this->assertSame('secret-token', $project->git_token);

        // The form never receives the token back, so a blank field must not
        // silently wipe it -- every later edit would otherwise break cloning.
        $this->actingAs($user)->put(route('project.update', $project), [
            'name' => 'Renamed',
            'git_base' => 'https://gitlab.com',
            'git_token_user' => 'oauth2',
            'git_token' => '',
        ])->assertRedirect(route('project.show', $project));

        $this->assertSame('secret-token', $project->fresh()->git_token);

        // Removing one is explicit.
        $this->actingAs($user)->put(route('project.update', $project), [
            'name' => 'Renamed',
            'git_token' => '',
            'remove_git_token' => true,
        ])->assertRedirect(route('project.show', $project));

        $this->assertNull($project->fresh()->git_token);
    }

    #[Test]
    public function a_git_host_carrying_more_than_a_host_is_rejected()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);

        foreach (['https://user:pw@gitlab.com', 'https://gitlab.com?x=1', 'gitlab.com', 'https://gitlab.com/;id'] as $base) {
            $this->actingAs($user)->put(route('project.update', $project), [
                'name' => $project->name,
                'git_base' => $base,
            ])->assertSessionHasErrors(['git_base']);
        }
    }

    #[Test]
    public function a_shell_unsafe_branch_is_rejected()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->actingAs($user)->put(route('project.update', $project), [
            'name' => $project->name,
            'default_branch' => 'main; rm -rf /',
        ])->assertSessionHasErrors(['default_branch']);
    }

    #[Test]
    public function a_user_cannot_update_another_teams_project()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $project = Project::factory()->create();

        $this->actingAs($outsider)->put(route('project.update', $project), [
            'name' => 'Hijacked',
        ])->assertNotFound();
    }
}

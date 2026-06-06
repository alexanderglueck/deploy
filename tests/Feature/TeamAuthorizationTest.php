<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards against cross-tenant data exposure: a user must never be able to read
 * or modify another team's servers, projects, workflows or deployments by
 * manipulating IDs in the URL.
 */
class TeamAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithData(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        $project = Project::factory()->create(['team_id' => $team->id]);
        $server = Server::factory()->create(['team_id' => $team->id]);
        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
        ]);

        return compact('user', 'team', 'project', 'server', 'workflow');
    }

    #[Test]
    public function a_user_cannot_view_another_teams_overview()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->userWithData();

        $this->actingAs($outsider)
            ->get(route('team.show', $victim['team']))
            ->assertForbidden();
    }

    #[Test]
    public function a_user_cannot_list_another_teams_servers()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->userWithData();

        $this->actingAs($outsider)
            ->get(route('server.index', $victim['team']))
            ->assertForbidden();
    }

    #[Test]
    public function a_user_cannot_view_another_teams_server()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->userWithData();

        $this->actingAs($outsider)
            ->get(route('server.show', [$victim['team'], $victim['server']]))
            ->assertForbidden();
    }

    #[Test]
    public function a_user_cannot_view_another_teams_project()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->userWithData();

        $this->actingAs($outsider)
            ->get(route('project.show', [$victim['team'], $victim['project']]))
            ->assertForbidden();
    }

    #[Test]
    public function a_user_cannot_create_a_project_for_another_team()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->userWithData();

        $this->actingAs($outsider)
            ->post(route('project.store', $victim['team']), ['name' => 'Hijack'])
            ->assertForbidden();

        $this->assertDatabaseMissing('projects', ['name' => 'Hijack']);
    }

    #[Test]
    public function a_user_cannot_view_or_delete_another_teams_workflow()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->userWithData();

        $this->actingAs($outsider)
            ->get(route('workflow.show', [$victim['team'], $victim['project'], $victim['workflow']]))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->delete(route('workflow.destroy', [$victim['team'], $victim['project'], $victim['workflow']]))
            ->assertForbidden();

        $this->assertDatabaseHas('workflows', ['id' => $victim['workflow']->id]);
    }

    #[Test]
    public function a_user_cannot_deploy_another_teams_project()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->userWithData();

        $this->actingAs($outsider)
            ->post(route('deployment.store', $victim['project']->deploy_endpoint))
            ->assertForbidden();

        $this->assertDatabaseCount('deployments', 0);
    }

    #[Test]
    public function scoped_bindings_reject_a_project_from_another_team()
    {
        // The outsider owns their own team but references the victim's project
        // nested under their own team id — the scoped binding must 404.
        $outsider = User::factory()->withPersonalTeam()->create();
        $ownTeam = $outsider->ownedTeams()->first();
        $victim = $this->userWithData();

        $this->actingAs($outsider)
            ->get(route('project.show', [$ownTeam, $victim['project']]))
            ->assertNotFound();
    }

    #[Test]
    public function a_team_member_can_access_their_own_team()
    {
        $owner = $this->userWithData();

        $this->actingAs($owner['user'])
            ->get(route('team.show', $owner['team']))
            ->assertOk();

        $this->actingAs($owner['user'])
            ->get(route('project.show', [$owner['team'], $owner['project']]))
            ->assertOk();
    }
}

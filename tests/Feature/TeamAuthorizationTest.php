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
 * Everything is scoped to the user's current team. A user must never be able to
 * read or modify another team's resources by guessing ULIDs — the response must
 * be 404 (we never even confirm the resource exists).
 */
class TeamAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function victimData(): array
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $project = Project::factory()->create(['team_id' => $team->id]);
        $server = Server::factory()->create(['team_id' => $team->id]);
        $workflow = Workflow::factory()->create([
            'project_id' => $project->id,
            'server_id' => $server->id,
        ]);

        return compact('owner', 'team', 'project', 'server', 'workflow');
    }

    #[Test]
    public function a_user_cannot_view_another_teams_project()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->victimData();

        $this->actingAs($outsider)
            ->get(route('project.show', $victim['project']))
            ->assertNotFound();
    }

    #[Test]
    public function a_user_cannot_view_another_teams_server()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->victimData();

        $this->actingAs($outsider)
            ->get(route('server.show', $victim['server']))
            ->assertNotFound();
    }

    #[Test]
    public function a_user_cannot_view_or_delete_another_teams_workflow()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->victimData();

        $this->actingAs($outsider)
            ->get(route('workflow.show', [$victim['project'], $victim['workflow']]))
            ->assertNotFound();

        $this->actingAs($outsider)
            ->delete(route('workflow.destroy', [$victim['project'], $victim['workflow']]))
            ->assertNotFound();

        $this->assertDatabaseHas('workflows', ['id' => $victim['workflow']->id]);
    }

    #[Test]
    public function a_user_cannot_deploy_another_teams_project()
    {
        $outsider = User::factory()->withPersonalTeam()->create();
        $victim = $this->victimData();

        $this->actingAs($outsider)
            ->post(route('deployment.store', $victim['project']->deploy_endpoint))
            ->assertNotFound();

        $this->assertDatabaseCount('deployments', 0);
    }

    #[Test]
    public function scoped_bindings_reject_a_workflow_from_another_project()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $ownProject = Project::factory()->create(['team_id' => $user->currentTeam->id]);

        $victim = $this->victimData();

        // The victim's workflow nested under the user's own project must 404.
        $this->actingAs($user)
            ->get(route('workflow.show', [$ownProject, $victim['workflow']]))
            ->assertNotFound();
    }

    #[Test]
    public function a_member_can_access_their_own_teams_resources()
    {
        $owner = $this->victimData();

        $this->actingAs($owner['owner'])
            ->get(route('project.show', $owner['project']))
            ->assertOk();

        $this->actingAs($owner['owner'])
            ->get(route('server.show', $owner['server']))
            ->assertOk();
    }
}

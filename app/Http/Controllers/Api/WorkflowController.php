<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Server;
use App\Models\Workflow;
use App\Support\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workflows for a project.
 *
 * Without one, a project accepts every trigger and then drops it:
 * ApiDeploymentController answers IGNORED when no workflow matches the pushed
 * branch, so CI goes green while nothing deploys. Registering projects over the
 * API was therefore only half a pipeline until this existed.
 */
class WorkflowController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json([
            'data' => $project->workflows()->get()->map(fn (Workflow $w) => $this->present($w))->all(),
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            // Server is addressed by its public ULID, like everything else here.
            'server' => ['required', 'string'],
            'event' => ['sometimes', 'string'],
            // Three meanings, matching Workflow::matchesBranch:
            //   omitted/null -> the repository's default branch
            //   '*'          -> any branch
            //   'name'       -> exactly that branch
            'branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'actions' => ['sometimes', 'nullable', 'array'],
        ]);

        $server = Server::query()
            ->where('ulid', $validated['server'])
            ->whereIn('team_id', $this->teamIds($request))
            ->first();
        // 404 rather than a validation error: a token should not learn which
        // server ULIDs exist outside its teams.
        abort_if($server === null, 404, 'Unknown server.');

        $event = Event::getEvent($validated['event'] ?? 'push');
        abort_if($event === null, 422, 'Unsupported event ['.($validated['event'] ?? 'push').'].');

        $workflow = $project->workflows()->create([
            'server_id' => $server->id,
            'event' => $event,
            'branch' => $validated['branch'] ?? null,
            'actions' => $validated['actions'] ?? null,
        ]);

        return response()->json(['data' => $this->present($workflow)], 201);
    }

    public function destroy(Request $request, Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeProject($request, $project);
        abort_unless($workflow->project_id === $project->id, 404);

        $workflow->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array<int, int>
     */
    private function teamIds(Request $request): array
    {
        return $request->user()->allTeams()->pluck('id')->all();
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(in_array($project->team_id, $this->teamIds($request), true), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Workflow $workflow): array
    {
        return [
            'ulid' => $workflow->ulid,
            'event' => $workflow->event,
            // Spelled out because null is meaningful here, not merely absent.
            'branch' => $workflow->branch,
            'branch_mode' => match (true) {
                $workflow->branch === Workflow::BRANCH_ANY => 'any',
                $workflow->branch === null => 'default-branch',
                default => 'exact',
            },
            'server' => $workflow->server?->ulid,
            'actions' => $workflow->actions,
        ];
    }
}

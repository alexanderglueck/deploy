<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncWorkflowSteps;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Server;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Support\Event;
use App\Support\StepType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Workflows for a project, and the steps they run.
 *
 * Without a workflow, a project accepts every trigger and then drops it:
 * ApiDeploymentController answers IGNORED when no workflow matches the pushed
 * branch, so CI goes green while nothing deploys. Without steps it is worse --
 * the trigger is accepted, a deployment row appears, and it fails a few
 * milliseconds later with "The workflow has no steps". Both states are
 * indistinguishable from success in CI, which is why `steps` is required on
 * create here, exactly as the workflow editor requires it.
 *
 * The pre-steps `actions` script is presented (old workflows still have one)
 * but no longer accepted: it was validated as an array while the column is a
 * text script, so it could not round-trip. Send an inline_script step instead.
 */
class WorkflowController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json([
            'data' => $project->workflows()->with('steps')->get()
                ->map(fn (Workflow $w) => $this->present($w))->all(),
        ]);
    }

    public function store(Request $request, Project $project, SyncWorkflowSteps $syncSteps): JsonResponse
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
            'steps' => ['required', 'array', 'min:1'],
        ] + StepType::rules());

        $server = $this->resolveServer($request, $validated['server']);
        $event = $this->resolveEvent($validated['event'] ?? 'push');

        $workflow = $project->workflows()->create([
            'server_id' => $server->id,
            'event' => $event,
            'branch' => $validated['branch'] ?? null,
        ]);

        $syncSteps($workflow, $validated['steps']);

        return response()->json(['data' => $this->present($workflow)], 201);
    }

    public function show(Request $request, Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeWorkflow($request, $project, $workflow);

        return response()->json(['data' => $this->present($workflow)]);
    }

    /**
     * Amend a workflow in place.
     *
     * Everything is optional: without this, changing a branch filter or fixing
     * a step meant deleting the workflow and recreating it -- which loses the
     * workflow's identity for no reason. Passing `steps` replaces the whole
     * list, the same way the editor's save does.
     */
    public function update(Request $request, Project $project, Workflow $workflow, SyncWorkflowSteps $syncSteps): JsonResponse
    {
        $this->authorizeWorkflow($request, $project, $workflow);

        $validated = $request->validate([
            'server' => ['sometimes', 'string'],
            'event' => ['sometimes', 'string'],
            'branch' => ['sometimes', 'nullable', 'string', 'max:255'],
            'steps' => ['sometimes', 'array', 'min:1'],
        ] + StepType::rules());

        $changes = [];

        if (array_key_exists('server', $validated)) {
            $changes['server_id'] = $this->resolveServer($request, $validated['server'])->id;
        }

        if (array_key_exists('event', $validated)) {
            $changes['event'] = $this->resolveEvent($validated['event']);
        }

        // array_key_exists, not isset: null is the "default branch" mode.
        if (array_key_exists('branch', $validated)) {
            $changes['branch'] = $validated['branch'];
        }

        if ($changes !== []) {
            $workflow->update($changes);
        }

        if (array_key_exists('steps', $validated)) {
            $syncSteps($workflow, $validated['steps']);
        }

        return response()->json(['data' => $this->present($workflow->refresh())]);
    }

    public function destroy(Request $request, Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeWorkflow($request, $project, $workflow);

        $workflow->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function steps(Request $request, Project $project, Workflow $workflow): JsonResponse
    {
        $this->authorizeWorkflow($request, $project, $workflow);

        return response()->json([
            'data' => $workflow->steps->map(fn (WorkflowStep $s) => $this->presentStep($s))->all(),
        ]);
    }

    /**
     * Replace the step list wholesale (PUT semantics), which is also how the
     * editor saves: position is the order, so there is nothing to reconcile.
     */
    public function replaceSteps(Request $request, Project $project, Workflow $workflow, SyncWorkflowSteps $syncSteps): JsonResponse
    {
        $this->authorizeWorkflow($request, $project, $workflow);

        $validated = $request->validate([
            'steps' => ['required', 'array', 'min:1'],
        ] + StepType::rules());

        $syncSteps($workflow, $validated['steps']);

        return response()->json([
            'data' => $workflow->steps->map(fn (WorkflowStep $s) => $this->presentStep($s))->all(),
        ]);
    }

    /**
     * Append steps, so adding one to a long workflow doesn't mean resending
     * the whole list. Same body shape as the replace endpoint.
     */
    public function addSteps(Request $request, Project $project, Workflow $workflow, SyncWorkflowSteps $syncSteps): JsonResponse
    {
        $this->authorizeWorkflow($request, $project, $workflow);

        $validated = $request->validate([
            'steps' => ['required', 'array', 'min:1'],
        ] + StepType::rules());

        $existing = $workflow->steps()->count();

        $syncSteps->append($workflow, $validated['steps']);

        return response()->json([
            'data' => $workflow->steps->slice($existing)
                ->map(fn (WorkflowStep $s) => $this->presentStep($s))->values()->all(),
        ], 201);
    }

    public function destroyStep(Request $request, Project $project, Workflow $workflow, WorkflowStep $step): JsonResponse
    {
        $this->authorizeWorkflow($request, $project, $workflow);
        abort_unless($step->workflow_id === $workflow->id, 404);

        $step->delete();

        // Positions are left as they are: they only have to sort, and
        // ProcessDeployments renumbers its snapshot from 1 anyway.
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

    private function authorizeWorkflow(Request $request, Project $project, Workflow $workflow): void
    {
        $this->authorizeProject($request, $project);
        abort_unless($workflow->project_id === $project->id, 404);
    }

    private function resolveServer(Request $request, string $ulid): Server
    {
        $server = Server::query()
            ->where('ulid', $ulid)
            ->whereIn('team_id', $this->teamIds($request))
            ->first();

        // 404 rather than a validation error: a token should not learn which
        // server ULIDs exist outside its teams.
        abort_if($server === null, 404, 'Unknown server.');

        return $server;
    }

    private function resolveEvent(string $event): int
    {
        $resolved = Event::getEvent($event);
        abort_if($resolved === null, 422, 'Unsupported event ['.$event.'].');

        return $resolved;
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
            'steps' => $workflow->steps->map(fn (WorkflowStep $s) => $this->presentStep($s))->all(),
            // Read-only legacy: only pre-steps workflows still have one, and it
            // is retired the moment steps are written.
            'actions' => $workflow->actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentStep(WorkflowStep $step): array
    {
        return [
            'ulid' => $step->ulid,
            'position' => $step->position,
            'type' => $step->type,
            'config' => $step->config ?? [],
        ];
    }
}

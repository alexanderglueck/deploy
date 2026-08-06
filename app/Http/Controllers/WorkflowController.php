<?php

namespace App\Http\Controllers;

use App\Actions\SyncWorkflowSteps;
use App\Models\Project;
use App\Models\Workflow;
use App\Support\Event;
use App\Support\StepType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function show(Request $request, Project $project, Workflow $workflow): Response
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $workflow->load(['server', 'steps']);

        return Inertia::render('Workflow/Show', [
            'project' => $project,
            'workflow' => $workflow,
            'eventLabel' => Event::label($workflow->event),
            'stepTypes' => StepType::options(),
        ]);
    }

    public function create(Request $request, Project $project): Response
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        return Inertia::render('Workflow/Create', [
            'project' => $project,
            'servers' => $team->servers,
            'events' => Event::options(),
            'stepTypes' => StepType::options(),
        ]);
    }

    public function store(Request $request, Project $project, SyncWorkflowSteps $syncSteps): RedirectResponse
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $validated = $this->validateWorkflow($request);

        $server = $team->servers()->where('ulid', $validated['server'])->firstOrFail();

        DB::transaction(function () use ($project, $server, $validated, $syncSteps) {
            $workflow = $project->workflows()->create([
                'event' => $validated['event'],
                'branch' => $validated['branch'] ?? null,
                'server_id' => $server->id,
            ]);

            $syncSteps($workflow, $validated['steps']);
        });

        return redirect()->route('project.show', $project);
    }

    public function destroy(Request $request, Project $project, Workflow $workflow): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $workflow->delete();

        return redirect()->route('project.show', $project);
    }

    public function edit(Request $request, Project $project, Workflow $workflow): Response
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $workflow->load(['server', 'steps']);

        return Inertia::render('Workflow/Edit', [
            'project' => $project,
            'servers' => $team->servers,
            'workflow' => $workflow,
            'events' => Event::options(),
            'stepTypes' => StepType::options(),
        ]);
    }

    public function update(Request $request, Project $project, Workflow $workflow, SyncWorkflowSteps $syncSteps): RedirectResponse
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $validated = $this->validateWorkflow($request);

        $server = $team->servers()->where('ulid', $validated['server'])->firstOrFail();

        DB::transaction(function () use ($workflow, $server, $validated, $syncSteps) {
            $workflow->update([
                'event' => $validated['event'],
                'branch' => $validated['branch'] ?? null,
                'server_id' => $server->id,
            ]);

            // Replaces the steps, and retires any legacy `actions` script.
            $syncSteps($workflow, $validated['steps']);
        });

        return redirect()->route('project.show', $project);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWorkflow(Request $request): array
    {
        return $request->validate([
            'event' => 'required',
            'branch' => ['nullable', 'string', 'regex:#^(\*|[\w./-]+)$#'],
            'server' => 'required',
            // The editor always submits the full list, and a workflow with no
            // steps would fail at deploy time.
            'steps' => 'required|array|min:1',
        ] + StepType::rules());
    }
}

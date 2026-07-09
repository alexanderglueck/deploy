<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Workflow;
use App\Support\Event;
use App\Support\StepType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, Project $project): RedirectResponse
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $validated = $this->validateWorkflow($request);

        $server = $team->servers()->where('ulid', $validated['server'])->firstOrFail();

        DB::transaction(function () use ($project, $server, $validated) {
            $workflow = $project->workflows()->create([
                'event' => $validated['event'],
                'branch' => $validated['branch'] ?? null,
                'server_id' => $server->id,
            ]);

            $this->syncSteps($workflow, $validated['steps']);
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

    public function update(Request $request, Project $project, Workflow $workflow): RedirectResponse
    {
        $team = $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $validated = $this->validateWorkflow($request);

        $server = $team->servers()->where('ulid', $validated['server'])->firstOrFail();

        DB::transaction(function () use ($workflow, $server, $validated) {
            $workflow->update([
                'event' => $validated['event'],
                'branch' => $validated['branch'] ?? null,
                'server_id' => $server->id,
                // Steps are the source of truth now.
                'actions' => null,
            ]);

            $workflow->steps()->delete();
            $this->syncSteps($workflow, $validated['steps']);
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
            'steps' => 'required|array|min:1',
            'steps.*.type' => ['required', Rule::in(StepType::all())],
            'steps.*.config' => 'nullable|array',
            'steps.*.config.script' => 'required_if:steps.*.type,'.StepType::INLINE_SCRIPT.'|nullable|string',
            'steps.*.config.path' => 'required_if:steps.*.type,'.StepType::SCRIPT_FILE.'|nullable|string',
            'steps.*.config.args' => 'nullable|string',
            'steps.*.config.app' => ['nullable', 'string', 'regex:/^[\w-]+$/'],
            // A filesystem path; flows into generated deploy scripts, so it is
            // shape-checked like the other script-bound fields.
            'steps.*.config.compose_file' => ['nullable', 'string', 'regex:#^[\w./-]+$#'],
            'steps.*.config.target' => ['nullable', 'string', 'regex:/^[\w-]+$/'],
        ]);
    }

    /**
     * Persist steps in order, keeping only the config keys the type uses.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function syncSteps(Workflow $workflow, array $steps): void
    {
        $allowedKeys = [
            StepType::INLINE_SCRIPT => ['script'],
            StepType::SCRIPT_FILE => ['path', 'args'],
            StepType::DOCKER_DEPLOY => ['app', 'compose_file', 'target'],
        ];

        foreach (array_values($steps) as $index => $step) {
            $config = collect($step['config'] ?? [])
                ->only($allowedKeys[$step['type']])
                ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
                ->all();

            $workflow->steps()->create([
                'position' => $index + 1,
                'type' => $step['type'],
                'config' => $config,
            ]);
        }
    }
}

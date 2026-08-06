<?php

namespace App\Http\Controllers;

use App\Jobs\ReconcileImageAvailability;
use App\Models\Deployment;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        // Refresh which past images still exist (for the rollback buttons),
        // at most once per few minutes per project.
        if ($project->deployments()->whereNotNull('image')->exists()
            && Cache::add("reconcile-images:{$project->id}", true, 300)) {
            ReconcileImageAvailability::dispatch($project);
        }

        return Inertia::render('Project/Show', [
            'project' => $project,
            // The secret is hidden from serialization; the setup card needs it.
            'webhookSecret' => $project->webhook_secret,
            'workflows' => $project->workflows()->with(['server', 'steps'])->get(),
            // Bounded so the 3s polling payload stays small. Output is only
            // shipped inline for active deployments (their logs are live);
            // finished ones load it on demand via deployment.output.
            'deployments' => $project->deployments()
                ->with(['log', 'steps', 'triggeredBy'])
                ->limit(25)
                ->get()
                ->each(function (Deployment $deployment) {
                    if (! $deployment->isActive()) {
                        $deployment->steps->each->makeHidden('output');
                        $deployment->unsetRelation('log');
                    }
                }),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->currentTeam($request);

        return Inertia::render('Project/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        $team->projects()->create($this->validateProject($request));

        return redirect()->route('team.show');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        $validated = $this->validateProject($request);

        // The token is never sent back to the form, so a blank field means
        // "keep the stored one" -- otherwise every unrelated edit would wipe
        // it. Removing one is deliberate, via the checkbox.
        if (blank($validated['git_token'] ?? null)) {
            unset($validated['git_token']);
        }

        if ($request->boolean('remove_git_token')) {
            $validated['git_token'] = null;
        }

        $project->update($validated);

        return redirect()->route('project.show', $project);
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        // Workflows, deployments, steps and logs cascade away with it.
        $project->delete();

        return redirect()->route('dashboard')->banner('Project deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProject(Request $request): array
    {
        return $request->validate([
            'name' => 'required',
            'repository' => ['nullable', 'string', 'regex:#^[\w.-]+/[\w.-]+$#'],
            // Flows into generated clone commands, so the shape is strict.
            'default_branch' => ['nullable', 'string', 'regex:#^[\w./-]+$#'],
            // Git host override; scheme and host only, no credentials in it
            // (those are the two fields below) and no query or fragment.
            'git_base' => ['nullable', 'string', 'max:255', 'regex:#^https?://[\w.-]+(:\d+)?(/[\w.-]+)*$#'],
            'git_token_user' => ['nullable', 'string', 'max:255', 'regex:/^[\w.@-]+$/'],
            'git_token' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

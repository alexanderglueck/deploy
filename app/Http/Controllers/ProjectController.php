<?php

namespace App\Http\Controllers;

use App\Jobs\ReconcileImageAvailability;
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
            // Bounded so the 3s polling payload stays small.
            'deployments' => $project->deployments()->with(['log', 'steps'])->limit(25)->get(),
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

        $project->update($this->validateProject($request));

        return redirect()->route('project.show', $project);
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
        ]);
    }
}

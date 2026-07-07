<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        $this->ensureOwnedByCurrentTeam($request, $project->team_id);

        return Inertia::render('Project/Show', [
            'project' => $project,
            // The secret is hidden from serialization; the setup card needs it.
            'webhookSecret' => $project->webhook_secret,
            'workflows' => $project->workflows()->with(['server', 'steps'])->get(),
            'deployments' => $project->deployments()->with(['log', 'steps'])->get(),
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

<?php

namespace App\Http\Controllers\Api;

use App\Actions\TriggerDeployment;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Project;
use App\Support\Event;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Management API for projects -- the scriptable counterpart to the web UI, so a
 * fleet of projects can be registered from a terminal instead of clicked through
 * a browser (which sits behind Cloudflare Access).
 *
 * Deliberately does NOT touch anything outside this application's own data: the
 * question of which apps a particular server runs stays in that server's own
 * config repo, because the deploy manager is meant to be usable independently of
 * any one of them.
 *
 * Auth is a Sanctum token (Jetstream "API Tokens"). Everything is scoped to the
 * token owner's teams, so a token can only ever see its own projects.
 */
class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->whereIn('team_id', $this->teamIds($request))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $projects->map(fn (Project $p) => $this->present($p))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        abort_if($team === null, 422, 'The token owner has no current team.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Nullable on purpose: a project without a repository accepts a
            // webhook from any repository (see Project::matchesRepository).
            'repository' => ['nullable', 'string', 'max:255', 'regex:#^[\w.-]+/[\w.-]+$#'],
            'default_branch' => ['nullable', 'string', 'max:255', 'regex:#^[\w./-]+$#'],
        ]);

        $project = Project::create($validated + ['team_id' => $team->id]);

        // The secret is shown exactly once, on creation -- it is stored
        // encrypted and the model hides it from every other response.
        return response()->json([
            'data' => $this->present($project) + [
                'webhook_secret' => $project->webhook_secret,
            ],
        ], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json(['data' => $this->present($project)]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'repository' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:#^[\w.-]+/[\w.-]+$#'],
            'default_branch' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:#^[\w./-]+$#'],
        ]);

        $project->update($validated);

        return response()->json(['data' => $this->present($project->refresh())]);
    }

    /**
     * Trigger a deployment without a push.
     *
     * Synthesises the same shape a push webhook would produce, so the existing
     * workflows match it and nothing downstream needs to know the difference. A
     * ref that no workflow wants is reported back as 422 rather than being
     * silently dropped -- unlike the webhook path, a human is waiting for an
     * answer here.
     */
    public function deploy(Request $request, Project $project, TriggerDeployment $trigger): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate([
            'ref' => ['sometimes', 'string', 'max:255', 'regex:#^[\w./-]+$#'],
            'sha' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9a-f]{6,64}$/i'],
        ]);

        $branch = $project->default_branch ?: 'master';
        $ref = $validated['ref'] ?? "refs/heads/{$branch}";

        $repository = $project->repository;
        abort_if(
            $repository === null,
            422,
            'This project has no repository configured, so there is nothing to deploy. Set one first.'
        );

        $data = [
            'project_id' => $project->id,
            'event' => Event::PUSH,
            'ref' => $ref,
            'default_branch' => $branch,
            'repository' => $repository,
            'commit_sha' => $validated['sha'] ?? null,
            'received_at' => Carbon::now(),
            // FK to users, same as the web UI's manual triggers.
            'triggered_by' => $request->user()->id,
        ];

        abort_unless(
            $this->matchesAWorkflow($project, $data),
            422,
            "No workflow of this project matches [{$ref}]."
        );

        $deployment = $trigger($data);

        return response()->json([
            'data' => [
                'ulid' => $deployment->ulid,
                'ref' => $deployment->ref,
                'repository' => $deployment->repository,
                'status_url' => rtrim((string) config('app.url'), '/')
                    ."/api/v1/deployments/{$deployment->ulid}",
            ],
        ], 202);
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
     * @param  array<string, mixed>  $data
     */
    private function matchesAWorkflow(Project $project, array $data): bool
    {
        $probe = new Deployment($data);
        $probe->setRelation('project', $project);

        return $project->workflows()
            ->where('event', $data['event'])
            ->get()
            ->contains(fn ($workflow) => $workflow->matchesBranch($probe));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Project $project): array
    {
        return [
            'ulid' => $project->ulid,
            'name' => $project->name,
            'repository' => $project->repository,
            'default_branch' => $project->default_branch,
            'deploy_endpoint' => $project->deploy_endpoint,
            // The URL a git host's webhook should POST to. Built from the
            // CONFIGURED app URL, not url()/the request host: this API is normally
            // called in-network (the UI is behind Cloudflare Access), and url()
            // would then hand back http://deploy/... -- unusable as a webhook
            // target, in the one field a caller copies straight into CI.
            'deploy_url' => rtrim((string) config('app.url'), '/')
                ."/api/deploy/{$project->deploy_endpoint}",
            'created_at' => optional($project->created_at)->toIso8601String(),
        ];
    }
}

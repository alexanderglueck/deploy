<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncProjectVariables;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectVariable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Environment variables for a project, in the spirit of GitLab's CI/CD
 * variables: defined once here, exported into every step of every deployment
 * the project runs -- script steps, the app's own deploy/build.sh, and
 * `docker compose up` (which interpolates them into the compose file).
 *
 * Values are write-only. Responses carry keys and flags so a caller can see and
 * reconcile what a project defines, but never read a value back out; a variable
 * submitted with a blank value keeps the stored one, which is how a flag can be
 * changed without resending the secret.
 */
class ProjectVariableController extends Controller
{
    /**
     * Keys are shell identifiers because they are exported verbatim.
     */
    private const RULES = [
        'variables' => ['present', 'array'],
        'variables.*.key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
        'variables.*.value' => ['sometimes', 'nullable', 'string', 'max:8192'],
        'variables.*.build_arg' => ['sometimes', 'boolean'],
        'variables.*.masked' => ['sometimes', 'boolean'],
    ];

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json([
            'data' => $project->variables->map(fn (ProjectVariable $v) => $this->present($v))->all(),
        ]);
    }

    /**
     * Replace the whole list, like the steps endpoint: the editor submits every
     * variable it knows about, and anything absent is meant to be gone.
     */
    public function replace(Request $request, Project $project, SyncProjectVariables $sync): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate(self::RULES);

        $keys = array_column($validated['variables'], 'key');
        abort_if(count($keys) !== count(array_unique($keys)), 422, 'Duplicate variable keys.');

        $sync($project, $validated['variables']);

        return response()->json([
            'data' => $project->variables->map(fn (ProjectVariable $v) => $this->present($v))->all(),
        ]);
    }

    public function destroy(Request $request, Project $project, ProjectVariable $variable): JsonResponse
    {
        $this->authorizeProject($request, $project);
        abort_unless($variable->project_id === $project->id, 404);

        $variable->delete();

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
    private function present(ProjectVariable $variable): array
    {
        return [
            'ulid' => $variable->ulid,
            'key' => $variable->key,
            // Never the value: `has_value` is enough to tell a configured
            // variable from an empty one.
            'has_value' => filled($variable->value),
            'build_arg' => $variable->build_arg,
            'masked' => $variable->masked,
        ];
    }
}

<?php

namespace App\Actions;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Writes a project's environment variables.
 *
 * Shared by the project screens and the management API, like SyncWorkflowSteps:
 * variables end up in generated shell commands, so one place decides how they
 * are validated, stored and updated.
 *
 * A submitted variable without a value keeps the stored one. The API never
 * returns values, and the form never receives them, so "unchanged" has to be
 * expressible without sending the secret back and forth -- otherwise editing a
 * flag would silently blank the value.
 */
class SyncProjectVariables
{
    /**
     * Replace the project's variables with this list.
     *
     * @param  array<int, array<string, mixed>>  $variables
     */
    public function __invoke(Project $project, array $variables): void
    {
        DB::transaction(function () use ($project, $variables) {
            $existing = $project->variables()->get()->keyBy('key');
            $keep = [];

            foreach ($variables as $variable) {
                $key = $variable['key'];
                $keep[] = $key;
                $current = $existing->get($key);

                $attributes = [
                    'build_arg' => (bool) ($variable['build_arg'] ?? false),
                    'masked' => (bool) ($variable['masked'] ?? true),
                ];

                // Blank value + an existing variable means "leave the value alone".
                if (filled($variable['value'] ?? null) || $current === null) {
                    $attributes['value'] = $variable['value'] ?? '';
                }

                if ($current) {
                    $current->update($attributes);

                    continue;
                }

                $project->variables()->create($attributes + ['key' => $key]);
            }

            $project->variables()->whereNotIn('key', $keep)->delete();
        });

        $project->unsetRelation('variables');
    }
}

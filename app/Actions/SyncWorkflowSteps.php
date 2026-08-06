<?php

namespace App\Actions;

use App\Models\Workflow;
use App\Support\StepType;
use Illuminate\Support\Facades\DB;

/**
 * Writes a workflow's steps.
 *
 * Shared by the workflow screens and the management API for the same reason
 * TriggerDeployment is shared by the webhook and the API: steps are the only
 * thing a deployment actually runs, so a second copy of this logic would mean
 * two ways to persist a config -- and only one of them exercised by tests.
 */
class SyncWorkflowSteps
{
    /**
     * Replace the workflow's steps with this list, in order.
     *
     * Replace-all rather than reconciling row by row: position *is* the order,
     * and the editor submits the whole list anyway.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function __invoke(Workflow $workflow, array $steps): void
    {
        DB::transaction(function () use ($workflow, $steps) {
            $workflow->steps()->delete();

            $this->insert($workflow, $steps, 0);
        });
    }

    /**
     * Add these steps after the ones already configured.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function append(Workflow $workflow, array $steps): void
    {
        DB::transaction(function () use ($workflow, $steps) {
            $this->insert($workflow, $steps, (int) $workflow->steps()->max('position'));
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function insert(Workflow $workflow, array $steps, int $offset): void
    {
        foreach (array_values($steps) as $index => $step) {
            $workflow->steps()->create([
                'position' => $offset + $index + 1,
                'type' => $step['type'],
                'config' => $this->config($step),
            ]);
        }

        // Steps supersede the pre-steps `actions` script, and ProcessDeployments
        // only falls back to it while there are no steps. Clearing it keeps a
        // workflow from carrying a second, invisible definition of itself.
        if (filled($workflow->actions)) {
            $workflow->update(['actions' => null]);
        }

        $workflow->unsetRelation('steps');
    }

    /**
     * Keep only the keys this type reads, dropping blanks so an untouched
     * editor field doesn't persist as an empty string.
     *
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function config(array $step): array
    {
        return collect($step['config'] ?? [])
            ->only(StepType::configKeys()[$step['type']] ?? [])
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->all();
    }
}

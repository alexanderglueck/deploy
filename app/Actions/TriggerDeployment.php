<?php

namespace App\Actions;

use App\Jobs\ProcessDeployments;
use App\Jobs\PruneDeployments;
use App\Models\Deployment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Creates a deployment and queues it.
 *
 * Extracted so the webhook receiver (ApiDeploymentController) and the management
 * API (Api\ProjectController) cannot drift apart: an API-triggered deploy has to
 * supersede pending work and prune history exactly like a pushed one, otherwise
 * the two entry points would behave differently under load.
 */
class TriggerDeployment
{
    /**
     * @param  array<string, mixed>  $data  as built by the callers' deployment
     *                                     data helpers (project_id, event, ref,
     *                                     repository, ...)
     */
    public function __invoke(array $data): Deployment
    {
        // A newer push for the same ref/event makes queued-but-unstarted work
        // pointless -- cancel it rather than deploying stale commits in order.
        Deployment::query()
            ->where([
                'project_id' => $data['project_id'],
                'ref' => $data['ref'],
                'event' => $data['event'],
                'repository' => $data['repository'],
            ])
            ->whereNull('processed_at')
            ->whereNull('deployed_at')
            ->whereNull('canceled_at')
            ->update([
                'canceled_at' => Carbon::now(),
            ]);

        $deployment = Deployment::create($data);

        ProcessDeployments::dispatch($deployment);

        // Retention pruning piggy-backs on deploy traffic, at most once a day,
        // so it needs no scheduler of its own.
        if (Cache::add('deploy:prune-scheduled', true, 60 * 60 * 24)) {
            PruneDeployments::dispatch();
        }

        return $deployment;
    }
}

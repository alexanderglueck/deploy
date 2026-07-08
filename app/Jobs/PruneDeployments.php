<?php

namespace App\Jobs;

use App\Models\Deployment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deletes concluded deployments (and, via cascade, their steps and logs)
 * older than the retention window, so step output doesn't grow forever.
 */
class PruneDeployments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public function handle(): void
    {
        self::prune();
    }

    /**
     * @return int the number of deployments deleted
     */
    public static function prune(): int
    {
        $cutoff = Carbon::now()->subDays((int) config('deploy.retention_days'));

        return Deployment::query()
            ->where('created_at', '<', $cutoff)
            // Never prune something still queued or running.
            ->where(fn ($query) => $query
                ->whereNotNull('deployed_at')
                ->orWhereNotNull('failed_at')
                ->orWhereNotNull('canceled_at'))
            ->delete();
    }
}

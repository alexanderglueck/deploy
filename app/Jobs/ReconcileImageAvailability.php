<?php

namespace App\Jobs;

use App\Execution\ExecutorFactory;
use App\Models\Deployment;
use App\Models\Project;
use App\Support\Event;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Checks which SHA-tagged images from past deployments still exist on the
 * target (the scheduler prunes old ones), so the UI only offers instant
 * rollbacks that can actually work. Dispatched lazily when a project page is
 * viewed, throttled by a cache lock.
 */
class ReconcileImageAvailability implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * How many of the project's most recent images to check.
     */
    public const CHECK_LIMIT = 30;

    public $timeout = 120;

    public $tries = 1;

    public function __construct(
        private readonly Project $project,
    ) {}

    public function handle(): void
    {
        $deployments = $this->project->deployments()
            ->whereNotNull('image')
            ->limit(self::CHECK_LIMIT)
            ->get();

        if ($deployments->isEmpty()) {
            return;
        }

        // Images live on the host the project's workflow deploys to.
        $server = $this->project->workflows()
            ->where('event', Event::PUSH)
            ->first()
            ?->server;

        if (! $server) {
            return;
        }

        // One executor run for all images: emit "OK <image>" / "MISS <image>".
        $script = $deployments
            ->map(fn (Deployment $deployment) => sprintf(
                'docker image inspect %1$s >/dev/null 2>&1 && echo "OK %2$s" || echo "MISS %2$s"',
                escapeshellarg($deployment->image),
                $deployment->image,
            ))
            ->implode("\n");

        $result = app(ExecutorFactory::class)->for($server)->run($script);

        if ($result->failed()) {
            return;
        }

        $available = collect(explode("\n", $result->output))
            ->filter(fn (string $line) => str_starts_with($line, 'OK '))
            ->map(fn (string $line) => trim(substr($line, 3)))
            ->flip();

        $now = Carbon::now();

        $deployments->each(function (Deployment $deployment) use ($available, $now) {
            $deployment->update([
                'image_available_at' => $available->has($deployment->image) ? $now : null,
                'image_checked_at' => $now,
            ]);
        });
    }
}

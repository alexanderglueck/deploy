<?php

namespace App\Console\Commands;

use App\Jobs\PruneDeployments;
use Illuminate\Console\Command;

class PruneDeploymentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete concluded deployments older than the retention window (DEPLOY_RETENTION_DAYS)';

    public function handle(): int
    {
        $deleted = PruneDeployments::prune();

        $this->info("Pruned {$deleted} deployment(s) older than ".config('deploy.retention_days').' days.');

        return self::SUCCESS;
    }
}

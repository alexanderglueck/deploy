<?php

namespace App\Jobs;

use App\Deployment;
use App\PendingDeployment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPendingDeployments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * @var PendingDeployment
     */
    private $pendingDeployment;

    /**
     * Create a new job instance.
     *
     * @param PendingDeployment $pendingDeployment
     */
    public function __construct(PendingDeployment $pendingDeployment)
    {
        $this->pendingDeployment = $pendingDeployment;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Set processed_at
        $this->pendingDeployment->update([
            'processed_at' => Carbon::now()
        ]);

        $deployment = Deployment::create(
            $this->pendingDeployment->toArray()
        );

        // pending created
        // pending gets processed
        // no

        // Deployment
        // Pending
        // Pending
        // Pending
        // check jobs table for deployments of the same project
        // if deploy in progress, do nothing, we are next
        // delete entry
        // update pending entry as cancelled
        // update ran_at

        // Deployment in progress?

        // Pending deployment processed
        $this->pendingDeployment->delete();
        $deployment->update([
            'deployed_at' => Carbon::now()
        ]);
    }
}

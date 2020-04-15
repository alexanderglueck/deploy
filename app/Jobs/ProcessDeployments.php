<?php

namespace App\Jobs;

use App\Deployment;
use App\Project;
use App\Workflow;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDeployments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * @var Deployment
     */
    private $deployment;

    /**
     * Create a new job instance.
     *
     * @param Deployment $deployment
     */
    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->deployment->isCanceled()) {
            // Don't process canceled deployments
            return;
        }

        // Set processed_at
        $this->deployment->update([
            'processed_at' => Carbon::now()
        ]);

        /** @var Project $project */
        $project = $this->deployment->project;

        /** @var Workflow $workflow */
        $workflow = $project->workflows()->where('event', $this->deployment->event)->first();

        // Store the workflow actions in case the workflow changes
        $this->deployment->update([
            'actions' => $workflow->actions
        ]);

        $server = $workflow->server;

        if ($server->ip != 'test') {
            $workflow->server->execute($this->deployment);
        }

        $this->deployment->update([
            'deployed_at' => Carbon::now()
        ]);
    }
}

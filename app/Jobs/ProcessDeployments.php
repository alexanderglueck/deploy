<?php

namespace App\Jobs;

use App\Execution\ExecutorFactory;
use App\Models\Deployment;
use App\Models\DeploymentStep;
use App\Models\Project;
use App\Models\Server;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Steps\DockerDeployScript;
use App\Steps\StepScriptFactory;
use App\Support\StepType;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProcessDeployments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * High so the job survives being released repeatedly by the overlap lock
     * while an earlier deployment of the same project is still running.
     *
     * @var int
     */
    public $tries = 25;

    /**
     * A single real error fails the deployment; no automatic re-runs.
     *
     * @var int
     */
    public $maxExceptions = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * @var Deployment
     */
    private $deployment;

    /**
     * Create a new job instance.
     */
    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;

        // Slightly above the process timeout so the executor gets to abort
        // (and report) before the queue kills the job.
        $this->timeout = (int) config('deploy.timeout') + 30;
    }

    /**
     * Only one deployment per project runs at a time; later ones wait.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('project:'.$this->deployment->project_id))
                ->releaseAfter(15)
                ->expireAfter($this->timeout + 60),
        ];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->deployment->refresh();

        if ($this->deployment->isCanceled()) {
            // Don't process canceled deployments
            return;
        }

        // Set processed_at
        $this->deployment->update([
            'processed_at' => Carbon::now(),
        ]);

        try {
            $this->deploy();
        } catch (Throwable $e) {
            $this->markFailed('ERROR: '.$e->getMessage());

            report($e);
        }
    }

    /**
     * The job was killed (e.g. it hit the queue timeout) — make sure the
     * deployment doesn't stay "deploying" forever.
     */
    public function failed(?Throwable $exception): void
    {
        $this->deployment->refresh();

        if ($this->deployment->isActive()) {
            $this->markFailed('ERROR: '.($exception?->getMessage() ?? 'The deployment was aborted.'));
        }
    }

    private function deploy(): void
    {
        /** @var Project $project */
        $project = $this->deployment->project;

        /** @var Workflow|null $workflow */
        $workflow = $project->workflows()
            ->where('event', $this->deployment->event)
            ->get()
            ->first(fn (Workflow $candidate) => $candidate->matchesBranch($this->deployment));

        if (! $workflow) {
            $this->markFailed("ERROR: No workflow matches this event and branch ({$this->deployment->ref}).");

            return;
        }

        // Rollbacks pre-create their steps; everything else runs a snapshot
        // of the workflow's current steps.
        $deploymentSteps = $this->deployment->steps()->get();

        if ($deploymentSteps->isEmpty()) {
            $steps = $workflow->steps;

            // Legacy fallback: workflows saved before steps existed carry
            // their script in `actions`.
            if ($steps->isEmpty() && filled($workflow->actions)) {
                $this->deployment->update(['actions' => $workflow->actions]);

                $steps = collect([new WorkflowStep([
                    'position' => 1,
                    'type' => StepType::INLINE_SCRIPT,
                    'config' => ['script' => $workflow->actions],
                ])]);
            }

            if ($steps->isEmpty()) {
                $this->markFailed('ERROR: The workflow has no steps.');

                return;
            }

            // Snapshot the workflow's steps so later edits don't rewrite history.
            $deploymentSteps = $steps->values()->map(function (WorkflowStep $step, int $index) {
                return $this->deployment->steps()->create([
                    'position' => $index + 1,
                    'type' => $step->type,
                    'config' => $step->config,
                    'status' => DeploymentStep::STATUS_PENDING,
                ]);
            });
        }

        /** @var Server $server */
        $server = $workflow->server;

        $executor = app(ExecutorFactory::class)->for($server);

        foreach ($deploymentSteps as $index => $step) {
            // A cancellation between steps stops the deployment.
            if ($this->deployment->fresh()->isCanceled()) {
                $this->skipRemaining($deploymentSteps, $index);

                return;
            }

            $step->update([
                'status' => DeploymentStep::STATUS_RUNNING,
                'started_at' => Carbon::now(),
            ]);

            try {
                $script = StepScriptFactory::scriptFor($step);

                $result = $executor->run($script, fn (string $chunk) => $step->appendOutput($chunk));
            } catch (Throwable $e) {
                $step->appendOutput('ERROR: '.$e->getMessage()."\n");
                $step->update([
                    'status' => DeploymentStep::STATUS_FAILED,
                    'finished_at' => Carbon::now(),
                ]);
                $this->skipRemaining($deploymentSteps, $index + 1);
                $this->markFailed("ERROR: Step {$step->position} failed: {$e->getMessage()}");

                return;
            }

            $step->update([
                'status' => $result->successful() ? DeploymentStep::STATUS_SUCCEEDED : DeploymentStep::STATUS_FAILED,
                'exit_code' => $result->exitCode,
                'finished_at' => Carbon::now(),
            ]);

            if ($result->failed()) {
                $this->skipRemaining($deploymentSteps, $index + 1);
                $this->markFailed("Step {$step->position} exited with code {$result->exitCode}.");

                return;
            }

            // Remember the SHA-tagged image the build produced so this
            // deployment can be rolled back to later.
            if ($step->type === StepType::DOCKER_DEPLOY) {
                $image = DockerDeployScript::shaImage($this->deployment, $step->config ?? []);

                if ($image) {
                    $this->deployment->update([
                        'image' => $image,
                        'image_available_at' => Carbon::now(),
                        'image_checked_at' => Carbon::now(),
                    ]);
                }
            }
        }

        $this->deployment->update([
            'deployed_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark every step from $from on as skipped.
     *
     * @param  Collection<int, DeploymentStep>  $steps
     */
    private function skipRemaining($steps, int $from): void
    {
        $steps->slice($from)
            ->filter(fn (DeploymentStep $step) => $step->status === DeploymentStep::STATUS_PENDING)
            ->each(fn (DeploymentStep $step) => $step->update([
                'status' => DeploymentStep::STATUS_SKIPPED,
            ]));
    }

    private function markFailed(string $message): void
    {
        $this->deployment->appendLog(rtrim($message)."\n");

        $this->deployment->update([
            'failed_at' => Carbon::now(),
        ]);

        $this->notifyFailure($message);
    }

    /**
     * POST a failure to the configured notification URL (ntfy, Slack,
     * healthchecks, ...). Best effort — a broken notifier never breaks
     * deployments.
     */
    private function notifyFailure(string $message): void
    {
        $url = config('deploy.notify_url');

        if (! $url) {
            return;
        }

        rescue(fn () => Http::timeout(5)->post($url, [
            'status' => 'failed',
            'project' => $this->deployment->project->name,
            'repository' => $this->deployment->repository,
            'ref' => $this->deployment->ref,
            'commit_sha' => $this->deployment->commit_sha,
            'message' => trim($message),
            'url' => route('project.show', $this->deployment->project),
        ]), report: false);
    }
}

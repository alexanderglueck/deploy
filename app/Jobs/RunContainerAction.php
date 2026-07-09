<?php

namespace App\Jobs;

use App\Docker\DockerClient;
use App\Events\ContainerActionUpdated;
use App\Models\ContainerAction;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executes a queued container action (docker start/stop/...), moving its
 * audit row through running → ok/failed and broadcasting every transition.
 *
 * Deliberately NOT ShouldQueue: it is dispatched with dispatchAfterResponse(),
 * so it runs in the web container right after the response is flushed. That
 * keeps actions instant for the user without putting them behind the single
 * queue worker, where a long image build could delay a restart by minutes.
 */
class RunContainerAction
{
    use Dispatchable;

    public function __construct(
        public ContainerAction $action,
    ) {}

    public function handle(): void
    {
        $this->transition('running');

        try {
            $docker = DockerClient::forServer($this->action->server, timeout: DockerClient::ACTION_TIMEOUT);

            $result = $docker->action($this->action->action, $this->action->container);

            $this->transition($result->successful() ? 'ok' : 'failed', $result->output);
        } catch (Throwable $e) {
            // Timeouts land here too — the docker CLI was killed, but the
            // daemon finishes the action on its own.
            $this->transition('failed', $e->getMessage());
        }
    }

    private function transition(string $status, ?string $output = null): void
    {
        $this->action->update([
            'status' => $status,
            'output' => $output !== null ? (Str::limit(trim($output), 2000) ?: null) : $this->action->output,
        ]);

        try {
            ContainerActionUpdated::dispatch($this->action);
        } catch (Throwable) {
            // Reverb being down must not fail the action — the dashboard
            // poll picks the new status up on its own.
        }
    }
}

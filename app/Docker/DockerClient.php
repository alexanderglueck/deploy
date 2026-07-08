<?php

namespace App\Docker;

use App\Execution\ExecutionResult;
use App\Execution\Executor;
use App\Execution\ExecutorFactory;
use App\Models\Server;
use Illuminate\Support\Collection;

/**
 * Reads and controls Docker on a server through its Executor — the same
 * mechanism deployments use, so it works identically for the local host and
 * remote SSH servers. Everything shells the docker CLI (present in the image)
 * and parses its JSON output.
 */
class DockerClient
{
    /**
     * Container/image reference charset. Anything else is rejected before it
     * reaches a shell command (defence in depth on top of escapeshellarg).
     */
    private const NAME_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_.\/:-]*$/';

    /**
     * The control actions the UI may invoke.
     */
    public const ACTIONS = ['start', 'stop', 'restart'];

    public function __construct(
        private readonly Executor $executor,
    ) {}

    public static function forServer(Server $server): self
    {
        // Short timeout: these are interactive queries, not deployments.
        return new self(app(ExecutorFactory::class)->for($server, timeout: 20));
    }

    /**
     * All containers (running and stopped), newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function containers(): array
    {
        $output = $this->read("docker ps -a --no-trunc --format '{{json .}}'");

        return $this->decodeLines($output)
            ->map(fn (array $c) => [
                'id' => $c['ID'] ?? null,
                'name' => $c['Names'] ?? '',
                'image' => $c['Image'] ?? '',
                'state' => $c['State'] ?? '',
                'status' => $c['Status'] ?? '',
                'ports' => $c['Ports'] ?? '',
                'created_at' => $c['CreatedAt'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Images in the local store.
     *
     * @return array<int, array<string, mixed>>
     */
    public function images(): array
    {
        $output = $this->read("docker images --format '{{json .}}'");

        return $this->decodeLines($output)
            ->map(fn (array $i) => [
                'id' => $i['ID'] ?? null,
                'repository' => $i['Repository'] ?? '<none>',
                'tag' => $i['Tag'] ?? '<none>',
                'size' => $i['Size'] ?? '',
                'created' => $i['CreatedSince'] ?? ($i['CreatedAt'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * Rich detail for one container, or null if it doesn't exist.
     *
     * @return array<string, mixed>|null
     */
    public function inspect(string $name): ?array
    {
        $this->assertValidName($name);

        $result = $this->executor->run('docker inspect --format '.escapeshellarg('{{json .}}').' '.escapeshellarg($name));

        if ($result->failed()) {
            // "No such object" is a missing container, not a real failure.
            if (str_contains($result->output, 'No such object')) {
                return null;
            }

            throw new DockerException($this->cleanError($result->output));
        }

        $data = json_decode(trim($result->output), true);

        if (! is_array($data)) {
            return null;
        }

        return [
            'name' => ltrim($data['Name'] ?? $name, '/'),
            'image' => data_get($data, 'Config.Image'),
            'created' => $data['Created'] ?? null,
            'state' => data_get($data, 'State.Status'),
            'running' => (bool) data_get($data, 'State.Running'),
            'exit_code' => data_get($data, 'State.ExitCode'),
            'error' => data_get($data, 'State.Error') ?: null,
            'started_at' => data_get($data, 'State.StartedAt'),
            'finished_at' => data_get($data, 'State.FinishedAt'),
            'restart_count' => $data['RestartCount'] ?? 0,
            'restart_policy' => data_get($data, 'HostConfig.RestartPolicy.Name'),
        ];
    }

    /**
     * The tail of a container's logs (stdout + stderr merged).
     */
    public function logs(string $name, int $tail = 500): string
    {
        $this->assertValidName($name);

        $tail = max(1, min($tail, 5000));

        // Container stderr is legitimate log output; merge it so the SSH
        // executor doesn't prefix it as an error.
        $result = $this->executor->run(
            'docker logs --tail '.$tail.' --timestamps '.escapeshellarg($name).' 2>&1'
        );

        return $result->output;
    }

    /**
     * Run a lifecycle action (start/stop/restart) on a container.
     */
    public function action(string $action, string $name): ExecutionResult
    {
        $this->assertValidName($name);

        if (! in_array($action, self::ACTIONS, true)) {
            throw new DockerException("Unknown action [{$action}].");
        }

        return $this->executor->run('docker '.$action.' '.escapeshellarg($name));
    }

    /**
     * Run a read command, raising if the daemon couldn't be reached.
     */
    private function read(string $command): string
    {
        $result = $this->executor->run($command);

        if ($result->failed()) {
            throw new DockerException($this->cleanError($result->output));
        }

        return $result->output;
    }

    /**
     * Parse newline-delimited JSON, silently dropping any non-JSON lines
     * (stray daemon warnings interleaved from stderr).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function decodeLines(string $output): Collection
    {
        return collect(explode("\n", trim($output)))
            ->map(fn (string $line) => json_decode(trim($line), true))
            ->filter(fn ($decoded) => is_array($decoded));
    }

    private function assertValidName(string $name): void
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new DockerException('Invalid container or image name.');
        }
    }

    private function cleanError(string $output): string
    {
        $output = trim($output);

        return $output === ''
            ? 'Could not reach the Docker daemon on this server.'
            : $output;
    }
}

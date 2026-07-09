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
    public const ACTIONS = ['start', 'stop', 'restart', 'unpause', 'kill'];

    /**
     * Interactive queries should answer fast; lifecycle actions wait out a
     * container's stop grace period (compose `stop_grace_period` can well
     * exceed docker's 10s default), so they get a longer leash.
     */
    public const READ_TIMEOUT = 20;

    public const ACTION_TIMEOUT = 60;

    public function __construct(
        private readonly Executor $executor,
    ) {}

    public static function forServer(Server $server, int $timeout = self::READ_TIMEOUT): self
    {
        return new self(app(ExecutorFactory::class)->for($server, timeout: $timeout));
    }

    /**
     * All containers (running and stopped), newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function containers(): array
    {
        $output = $this->read("docker ps -a --no-trunc --format '{{json .}}'");

        return $this->decodeLines($output, guarded: true)
            ->map(fn (array $c) => [
                'id' => $c['ID'] ?? null,
                'name' => $c['Names'] ?? '',
                'image' => $c['Image'] ?? '',
                'state' => $c['State'] ?? '',
                'status' => $c['Status'] ?? '',
                'health' => $this->parseHealth($c['Status'] ?? ''),
                'ports' => $c['Ports'] ?? '',
                'created_at' => $c['CreatedAt'] ?? null,
                'compose_project' => $this->parseLabel($c['Labels'] ?? '', 'com.docker.compose.project'),
                'compose_service' => $this->parseLabel($c['Labels'] ?? '', 'com.docker.compose.service'),
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

        return $this->decodeLines($output, guarded: true)
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
     * One resource sample (CPU, memory) per running container, keyed by
     * container name. `--no-stream` still waits ~1.5s for the sample, so this
     * is fetched on demand, not with every dashboard poll.
     *
     * @return array<string, array<string, string>>
     */
    public function stats(): array
    {
        $output = $this->read("docker stats --no-stream --format '{{json .}}'");

        return $this->decodeLines($output)
            ->mapWithKeys(fn (array $s) => [
                ($s['Name'] ?? '') => [
                    'cpu' => $s['CPUPerc'] ?? '',
                    'memory' => $s['MemUsage'] ?? '',
                    'memory_percent' => $s['MemPerc'] ?? '',
                ],
            ])
            ->forget('')
            ->all();
    }

    /**
     * Daemon disk usage by resource type (images, containers, volumes, build
     * cache), as reported by `docker system df`.
     *
     * @return array<int, array<string, string>>
     */
    public function diskUsage(): array
    {
        $output = $this->read("docker system df --format '{{json .}}'");

        return $this->decodeLines($output)
            ->map(fn (array $row) => [
                'type' => $row['Type'] ?? '',
                'count' => (string) ($row['TotalCount'] ?? ''),
                'size' => $row['Size'] ?? '',
                'reclaimable' => $row['Reclaimable'] ?? '',
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
            'health' => data_get($data, 'State.Health.Status'),
            'exit_code' => data_get($data, 'State.ExitCode'),
            'error' => data_get($data, 'State.Error') ?: null,
            'started_at' => data_get($data, 'State.StartedAt'),
            'finished_at' => data_get($data, 'State.FinishedAt'),
            'restart_count' => $data['RestartCount'] ?? 0,
            'restart_policy' => data_get($data, 'HostConfig.RestartPolicy.Name'),
        ];
    }

    /**
     * The tail of a container's logs (stdout + stderr merged). $since narrows
     * to a recent window ("15m", "1h", ...); anything else is ignored.
     */
    public function logs(string $name, int $tail = 500, bool $timestamps = true, ?string $since = null): string
    {
        $this->assertValidName($name);

        $tail = max(1, min($tail, 5000));

        $command = 'docker logs --tail '.$tail;

        if ($timestamps) {
            $command .= ' --timestamps';
        }

        if ($since !== null && preg_match('/^\d{1,4}[smh]$/', $since)) {
            $command .= ' --since '.$since;
        }

        // Container stderr is legitimate log output; merge it so the SSH
        // executor doesn't prefix it as an error.
        $result = $this->executor->run($command.' '.escapeshellarg($name).' 2>&1');

        if ($result->failed()) {
            // A missing container or an unreachable daemon, not log content.
            throw new DockerException($this->cleanError($result->output));
        }

        return $result->output;
    }

    /**
     * Run a lifecycle action (start/stop/restart/unpause/kill) on a container.
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
     * (stray daemon warnings interleaved from stderr). With $guarded, output
     * that contains *only* non-JSON text raises instead of decoding to an
     * empty list — that's an error message from a daemon whose failure the
     * exit code didn't report (the SSH executor treats an unreported exit
     * status as success).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function decodeLines(string $output, bool $guarded = false): Collection
    {
        $decoded = collect(explode("\n", trim($output)))
            ->map(fn (string $line) => json_decode(trim($line), true))
            ->filter(fn ($decoded) => is_array($decoded));

        if ($guarded && $decoded->isEmpty() && trim($output) !== '') {
            throw new DockerException($this->cleanError($output));
        }

        return $decoded;
    }

    /**
     * Healthcheck verdict from a `docker ps` status line ("Up 3 hours
     * (healthy)"), or null when the container defines no healthcheck.
     */
    private function parseHealth(string $status): ?string
    {
        return match (true) {
            str_contains($status, '(healthy)') => 'healthy',
            str_contains($status, '(unhealthy)') => 'unhealthy',
            str_contains($status, '(health: starting)') => 'starting',
            default => null,
        };
    }

    /**
     * A label's value from the comma-separated `docker ps` Labels string.
     */
    private function parseLabel(string $labels, string $key): ?string
    {
        if (preg_match('/(?:^|,)'.preg_quote($key, '/').'=([^,]+)/', $labels, $match)) {
            return $match[1];
        }

        return null;
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

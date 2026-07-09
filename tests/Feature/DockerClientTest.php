<?php

namespace Tests\Feature;

use App\Docker\DockerClient;
use App\Docker\DockerException;
use App\Execution\ExecutionResult;
use App\Execution\Executor;
use Closure;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DockerClientTest extends TestCase
{
    /**
     * An executor that records the last script and returns a canned result.
     */
    private function executor(string $output = '', int $exitCode = 0, ?string &$captured = null): Executor
    {
        return new class($output, $exitCode, $captured) implements Executor
        {
            public function __construct(private string $output, private int $exitCode, private ?string &$captured) {}

            public function run(string $script, ?Closure $onOutput = null): ExecutionResult
            {
                $this->captured = $script;

                return new ExecutionResult($this->exitCode, $this->output);
            }
        };
    }

    #[Test]
    public function it_parses_containers_and_ignores_stray_lines()
    {
        $output = implode("\n", [
            'WARNING: something on stderr',
            json_encode(['ID' => 'abc', 'Names' => 'web', 'Image' => 'nginx:latest', 'State' => 'running', 'Status' => 'Up 3 hours', 'Ports' => '0.0.0.0:80->80/tcp', 'CreatedAt' => '2026-07-01']),
            json_encode(['ID' => 'def', 'Names' => 'db', 'Image' => 'mariadb:11', 'State' => 'exited', 'Status' => 'Exited (1) 2 hours ago', 'Ports' => '']),
        ]);

        $containers = (new DockerClient($this->executor($output)))->containers();

        $this->assertCount(2, $containers);
        $this->assertSame('web', $containers[0]['name']);
        $this->assertSame('running', $containers[0]['state']);
        $this->assertSame('Exited (1) 2 hours ago', $containers[1]['status']);
    }

    #[Test]
    public function it_parses_health_and_compose_labels()
    {
        $output = json_encode([
            'ID' => 'abc',
            'Names' => 'contacts-app',
            'Image' => 'contacts:latest',
            'State' => 'running',
            'Status' => 'Up 3 hours (healthy)',
            'Labels' => 'com.docker.compose.project=contacts,com.docker.compose.service=app,other=x',
        ]);

        $containers = (new DockerClient($this->executor($output)))->containers();

        $this->assertSame('healthy', $containers[0]['health']);
        $this->assertSame('contacts', $containers[0]['compose_project']);
        $this->assertSame('app', $containers[0]['compose_service']);
    }

    #[Test]
    public function containers_without_healthcheck_or_labels_report_null()
    {
        $output = json_encode(['ID' => 'abc', 'Names' => 'web', 'State' => 'running', 'Status' => 'Up 3 hours', 'Labels' => 'maintainer=nginx']);

        $containers = (new DockerClient($this->executor($output)))->containers();

        $this->assertNull($containers[0]['health']);
        $this->assertNull($containers[0]['compose_project']);
    }

    #[Test]
    public function garbage_only_output_raises_instead_of_reading_as_an_empty_list()
    {
        // An SSH server that doesn't report exit codes can hand back a daemon
        // error with exit 0 — that must not render as "no containers".
        $client = new DockerClient($this->executor('ERROR: Cannot connect to the Docker daemon', 0));

        $this->expectException(DockerException::class);

        $client->containers();
    }

    #[Test]
    public function it_parses_images()
    {
        $output = json_encode(['ID' => 'sha256:abc', 'Repository' => 'nginx', 'Tag' => 'latest', 'Size' => '187MB', 'CreatedSince' => '3 days ago']);

        $images = (new DockerClient($this->executor($output)))->images();

        $this->assertSame('nginx', $images[0]['repository']);
        $this->assertSame('latest', $images[0]['tag']);
        $this->assertSame('187MB', $images[0]['size']);
    }

    #[Test]
    public function it_parses_stats_keyed_by_container_name()
    {
        $output = json_encode(['Name' => 'web', 'CPUPerc' => '0.50%', 'MemUsage' => '120MiB / 1GiB', 'MemPerc' => '12.00%']);

        $stats = (new DockerClient($this->executor($output)))->stats();

        $this->assertSame('0.50%', $stats['web']['cpu']);
        $this->assertSame('120MiB / 1GiB', $stats['web']['memory']);
    }

    #[Test]
    public function it_parses_disk_usage()
    {
        $output = implode("\n", [
            json_encode(['Type' => 'Images', 'TotalCount' => 12, 'Size' => '4.2GB', 'Reclaimable' => '1.1GB (26%)']),
            json_encode(['Type' => 'Build Cache', 'TotalCount' => 80, 'Size' => '900MB', 'Reclaimable' => '900MB']),
        ]);

        $disk = (new DockerClient($this->executor($output)))->diskUsage();

        $this->assertSame('Images', $disk[0]['type']);
        $this->assertSame('4.2GB', $disk[0]['size']);
        $this->assertSame('900MB', $disk[1]['reclaimable']);
    }

    #[Test]
    public function it_extracts_inspect_fields()
    {
        $output = json_encode([
            'Name' => '/web',
            'Created' => '2026-07-01T10:00:00Z',
            'RestartCount' => 3,
            'Config' => ['Image' => 'nginx:latest'],
            'State' => ['Status' => 'exited', 'Running' => false, 'ExitCode' => 137, 'Error' => 'OOMKilled', 'StartedAt' => '2026-07-01T10:00:01Z', 'FinishedAt' => '2026-07-02T02:00:00Z'],
            'HostConfig' => ['RestartPolicy' => ['Name' => 'unless-stopped']],
        ]);

        $detail = (new DockerClient($this->executor($output)))->inspect('web');

        $this->assertSame('web', $detail['name']);
        $this->assertSame('nginx:latest', $detail['image']);
        $this->assertSame(137, $detail['exit_code']);
        $this->assertSame('OOMKilled', $detail['error']);
        $this->assertSame(3, $detail['restart_count']);
        $this->assertSame('unless-stopped', $detail['restart_policy']);
        $this->assertFalse($detail['running']);
    }

    #[Test]
    public function inspect_returns_null_for_a_missing_container()
    {
        $client = new DockerClient($this->executor('Error: No such object: nope', 1));

        $this->assertNull($client->inspect('nope'));

        // Newer daemons lowercase the message.
        $client = new DockerClient($this->executor('error: no such object: nope', 1));

        $this->assertNull($client->inspect('nope'));
    }

    #[Test]
    public function logs_merge_stderr_and_pass_the_tail()
    {
        $captured = null;
        $client = new DockerClient($this->executor('log line', 0, $captured));

        $client->logs('web', 250);

        $this->assertStringContainsString('docker logs --tail 250 --timestamps', $captured);
        $this->assertStringContainsString("'web' 2>&1", $captured);
    }

    #[Test]
    public function logs_can_narrow_the_window_and_drop_timestamps()
    {
        $captured = null;
        $client = new DockerClient($this->executor('log line', 0, $captured));

        $client->logs('web', 100, timestamps: false, since: '15m');

        $this->assertStringContainsString('--since 15m', $captured);
        $this->assertStringNotContainsString('--timestamps', $captured);
    }

    #[Test]
    public function a_malformed_since_window_is_ignored()
    {
        $captured = null;
        $client = new DockerClient($this->executor('log line', 0, $captured));

        $client->logs('web', 100, since: '2 days');

        $this->assertStringNotContainsString('--since', $captured);
    }

    #[Test]
    public function a_log_failure_raises_instead_of_returning_the_error_as_content()
    {
        $client = new DockerClient($this->executor('Error: No such container: web', 1));

        $this->expectException(DockerException::class);

        $client->logs('web');
    }

    #[Test]
    public function actions_build_the_expected_command()
    {
        $captured = null;
        $client = new DockerClient($this->executor('', 0, $captured));

        $client->action('restart', 'web');

        $this->assertSame("docker restart 'web'", $captured);
    }

    #[Test]
    public function kill_and_unpause_are_valid_actions()
    {
        $captured = null;
        $client = new DockerClient($this->executor('', 0, $captured));

        $client->action('kill', 'web');
        $this->assertSame("docker kill 'web'", $captured);

        $client->action('unpause', 'web');
        $this->assertSame("docker unpause 'web'", $captured);
    }

    #[Test]
    public function a_read_failure_raises()
    {
        $this->expectException(DockerException::class);

        (new DockerClient($this->executor('Cannot connect to the Docker daemon', 1)))->containers();
    }

    #[Test]
    public function names_with_shell_metacharacters_are_rejected()
    {
        $client = new DockerClient($this->executor());

        $this->expectException(DockerException::class);

        $client->action('restart', 'web; rm -rf /');
    }

    #[Test]
    public function an_unknown_action_is_rejected()
    {
        $client = new DockerClient($this->executor());

        $this->expectException(DockerException::class);

        $client->action('exec', 'web');
    }
}

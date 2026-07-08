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
    public function it_parses_images()
    {
        $output = json_encode(['ID' => 'sha256:abc', 'Repository' => 'nginx', 'Tag' => 'latest', 'Size' => '187MB', 'CreatedSince' => '3 days ago']);

        $images = (new DockerClient($this->executor($output)))->images();

        $this->assertSame('nginx', $images[0]['repository']);
        $this->assertSame('latest', $images[0]['tag']);
        $this->assertSame('187MB', $images[0]['size']);
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
    public function actions_build_the_expected_command()
    {
        $captured = null;
        $client = new DockerClient($this->executor('', 0, $captured));

        $client->action('restart', 'web');

        $this->assertSame("docker restart 'web'", $captured);
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

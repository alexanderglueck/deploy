<?php

namespace Tests\Feature;

use App\Execution\LocalExecutor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalExecutorTest extends TestCase
{
    #[Test]
    public function it_runs_a_script_and_streams_output()
    {
        $streamed = '';

        $result = (new LocalExecutor(timeout: 10))->run(
            "echo first\necho second",
            function (string $chunk) use (&$streamed) {
                $streamed .= $chunk;
            },
        );

        $this->assertTrue($result->successful());
        $this->assertSame(0, $result->exitCode);
        $this->assertStringContainsString("first\nsecond", $result->output);
        $this->assertStringContainsString("first\nsecond", $streamed);
    }

    #[Test]
    public function it_reports_a_non_zero_exit_code()
    {
        $result = (new LocalExecutor(timeout: 10))->run('exit 3');

        $this->assertTrue($result->failed());
        $this->assertSame(3, $result->exitCode);
    }

    #[Test]
    public function it_captures_stderr()
    {
        $result = (new LocalExecutor(timeout: 10))->run('echo oops 1>&2');

        $this->assertStringContainsString('oops', $result->output);
    }

    #[Test]
    public function it_runs_under_bash_so_pipefail_works()
    {
        // Generated docker deploy scripts start with `set -euo pipefail`,
        // which plain sh (dash) rejects.
        $result = (new LocalExecutor(timeout: 10))->run("set -euo pipefail\nfalse | cat");

        $this->assertSame(1, $result->exitCode);
    }
}

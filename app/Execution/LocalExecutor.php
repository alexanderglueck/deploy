<?php

namespace App\Execution;

use Closure;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Runs scripts on the host the application itself runs on. Scripts run under
 * bash explicitly — generated deploy scripts use `set -o pipefail`, which
 * plain sh (dash) rejects.
 */
class LocalExecutor implements Executor
{
    public function __construct(
        private readonly int $timeout,
    ) {}

    public function run(string $script, ?Closure $onOutput = null): ExecutionResult
    {
        // Run from a private file rather than `bash -c <script>`: the script
        // carries exported project variables and the git token in the clone
        // URL, and an argument is visible to every user on the host through
        // the process list for as long as the deployment runs.
        $file = tempnam(sys_get_temp_dir(), 'deploy-step-');

        if ($file === false) {
            throw new RuntimeException('Could not create a temporary file for the deployment step.');
        }

        chmod($file, 0600);
        file_put_contents($file, $script);

        try {
            $result = Process::timeout($this->timeout)
                ->run(['bash', $file], function (string $type, string $buffer) use ($onOutput) {
                    if ($onOutput) {
                        $onOutput($buffer);
                    }
                });

            return new ExecutionResult(
                exitCode: $result->exitCode() ?? 1,
                output: $result->output().$result->errorOutput(),
            );
        } finally {
            @unlink($file);
        }
    }
}

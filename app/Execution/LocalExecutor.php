<?php

namespace App\Execution;

use Closure;
use Illuminate\Support\Facades\Process;

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
        $result = Process::timeout($this->timeout)
            ->run(['bash', '-c', $script], function (string $type, string $buffer) use ($onOutput) {
                if ($onOutput) {
                    $onOutput($buffer);
                }
            });

        return new ExecutionResult(
            exitCode: $result->exitCode() ?? 1,
            output: $result->output().$result->errorOutput(),
        );
    }
}

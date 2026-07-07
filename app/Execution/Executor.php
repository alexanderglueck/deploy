<?php

namespace App\Execution;

use Closure;

interface Executor
{
    /**
     * Run a shell script, invoking $onOutput with each chunk of output as it
     * is produced, and return the final result once the script finishes.
     */
    public function run(string $script, ?Closure $onOutput = null): ExecutionResult;
}

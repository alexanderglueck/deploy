<?php

namespace App\Execution;

use Closure;

/**
 * Records scripts instead of running them. Swapped in by tests via
 * ExecutorFactory::fake().
 */
class FakeExecutor implements Executor
{
    /**
     * Every script this executor was asked to run.
     *
     * @var array<int, string>
     */
    public array $scripts = [];

    public function __construct(
        private readonly int $exitCode = 0,
        private readonly string $output = '',
    ) {}

    public function run(string $script, ?Closure $onOutput = null): ExecutionResult
    {
        $this->scripts[] = $script;

        if ($onOutput && $this->output !== '') {
            $onOutput($this->output);
        }

        return new ExecutionResult($this->exitCode, $this->output);
    }
}

<?php

namespace App\Execution;

class ExecutionResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output = '',
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }
}

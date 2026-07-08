<?php

namespace App\Execution;

use App\Models\Server;
use Closure;

class ExecutorFactory
{
    public function for(Server $server, ?int $timeout = null): Executor
    {
        $timeout ??= (int) config('deploy.timeout');

        return $server->isLocal()
            ? new LocalExecutor($timeout)
            : new SshExecutor($server, $timeout);
    }

    /**
     * Bind a FakeExecutor into the container for the remainder of the test.
     */
    public static function fake(int $exitCode = 0, string $output = ''): FakeExecutor
    {
        $fake = new FakeExecutor($exitCode, $output);

        app()->instance(self::class, new class($fake) extends ExecutorFactory
        {
            public function __construct(private readonly FakeExecutor $fake) {}

            public function for(Server $server, ?int $timeout = null): Executor
            {
                return $this->fake;
            }
        });

        return $fake;
    }

    /**
     * Bind an executor whose output is computed per script — useful when one
     * request issues several different commands (e.g. docker ps + images).
     * The resolver returns a string (exit 0) or an ExecutionResult.
     */
    public static function fakeUsing(Closure $resolver): void
    {
        app()->instance(self::class, new class($resolver) extends ExecutorFactory
        {
            public function __construct(private readonly Closure $resolver) {}

            public function for(Server $server, ?int $timeout = null): Executor
            {
                return new class($this->resolver) implements Executor
                {
                    public function __construct(private readonly Closure $resolver) {}

                    public function run(string $script, ?Closure $onOutput = null): ExecutionResult
                    {
                        $out = ($this->resolver)($script);

                        return $out instanceof ExecutionResult ? $out : new ExecutionResult(0, (string) $out);
                    }
                };
            }
        });
    }
}

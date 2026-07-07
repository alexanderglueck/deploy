<?php

namespace App\Execution;

use App\Models\Server;

class ExecutorFactory
{
    public function for(Server $server): Executor
    {
        $timeout = (int) config('deploy.timeout');

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

            public function for(Server $server): Executor
            {
                return $this->fake;
            }
        });

        return $fake;
    }
}

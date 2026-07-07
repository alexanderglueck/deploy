<?php

namespace App\Execution;

use App\Models\Server;
use App\SSH\Connection;
use Closure;

/**
 * Runs scripts on a remote server over SSH, authenticated with the server's
 * own keypair.
 */
class SshExecutor implements Executor
{
    public function __construct(
        private readonly Server $server,
        private readonly int $timeout,
    ) {}

    public function run(string $script, ?Closure $onOutput = null): ExecutionResult
    {
        $connection = (new Connection($this->server->ip, $this->server->port, $this->server->user, $this->timeout))
            ->usingPrivateKey($this->server->private_key)
            ->connect();

        // With a callback, phpseclib's exec() returns a bool rather than the
        // output, so accumulate it ourselves.
        $output = '';
        $connection->run($script, function ($chunk) use (&$output, $onOutput) {
            $output .= $chunk;

            if ($onOutput) {
                $onOutput($chunk);
            }
        });

        // stderr is only available in bulk after the command finishes.
        $error = $connection->getError();
        if ($error !== null && trim($error) !== '') {
            $errorChunk = 'ERROR: '.$error;
            $output .= $errorChunk;

            if ($onOutput) {
                $onOutput($errorChunk);
            }
        }

        $exitCode = $connection->getExitStatus();

        $connection->disconnect();

        return new ExecutionResult(
            // Some servers do not report an exit status; treat that as success
            // (matches the pre-executor behavior of ignoring exit codes).
            exitCode: $exitCode === false ? 0 : $exitCode,
            output: $output,
        );
    }
}

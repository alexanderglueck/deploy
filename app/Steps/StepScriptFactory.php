<?php

namespace App\Steps;

use App\Models\DeploymentStep;
use App\Models\ProjectVariable;
use App\Support\StepType;
use InvalidArgumentException;

/**
 * Turns a deployment step into the shell script the executor runs. Every step
 * type reduces to a script, so all of them work on local and SSH targets.
 */
class StepScriptFactory
{
    public static function scriptFor(DeploymentStep $step): string
    {
        $config = $step->config ?? [];

        $script = match ($step->type) {
            StepType::INLINE_SCRIPT => self::inlineScript($config),
            StepType::SCRIPT_FILE => self::scriptFile($config),
            StepType::DOCKER_DEPLOY => DockerDeployScript::generate($step->deployment, $config),
            StepType::DOCKER_ROLLBACK => DockerRollbackScript::generate($step->deployment, $config),
            default => throw new InvalidArgumentException("Unknown step type [{$step->type}]."),
        };

        return self::withProjectVariables($step, $script);
    }

    /**
     * Prefix the project's variables as exports, so every step type sees them:
     * a script step, the app's own deploy/build.sh, and `docker compose up`
     * (which interpolates them into the compose file) alike.
     *
     * Resolved at run time rather than snapshotted onto the deployment: a
     * variable is configuration of the project, and a retry of an old
     * deployment should use today's value, not a stale copy of a rotated one.
     */
    private static function withProjectVariables(DeploymentStep $step, string $script): string
    {
        $variables = $step->deployment?->project?->variables ?? collect();

        if ($variables->isEmpty()) {
            return $script;
        }

        // Re-checked here, not just in the controllers: the key is written into
        // the script unquoted, so this is the boundary that decides what a
        // deployment executes. A row that reached the table some other way (an
        // import, a console command, a future endpoint) must not be able to
        // smuggle `KEY=x; curl … | sh` into a root-equivalent shell, or replace
        // PATH out from under every command in the step.
        $exports = $variables
            ->filter(fn ($variable) => ProjectVariable::keyIsAllowed((string) $variable->key))
            ->map(fn ($variable) => 'export '.$variable->key.'='.escapeshellarg((string) $variable->value))
            ->implode("\n");

        if ($exports === '') {
            return $script;
        }

        return $exports."\n\n".$script;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function inlineScript(array $config): string
    {
        $script = $config['script'] ?? '';

        if (trim($script) === '') {
            throw new InvalidArgumentException('The script step has no script configured.');
        }

        return str_replace("\r\n", "\n", $script);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function scriptFile(array $config): string
    {
        $path = $config['path'] ?? '';

        if (trim($path) === '') {
            throw new InvalidArgumentException('The script file step has no path configured.');
        }

        $script = escapeshellarg($path);

        if (! empty($config['args'])) {
            $script .= ' '.$config['args'];
        }

        return $script;
    }
}

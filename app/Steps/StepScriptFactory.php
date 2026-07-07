<?php

namespace App\Steps;

use App\Models\DeploymentStep;
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

        return match ($step->type) {
            StepType::INLINE_SCRIPT => self::inlineScript($config),
            StepType::SCRIPT_FILE => self::scriptFile($config),
            StepType::DOCKER_DEPLOY => DockerDeployScript::generate($step->deployment, $config),
            default => throw new InvalidArgumentException("Unknown step type [{$step->type}]."),
        };
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

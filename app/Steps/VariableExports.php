<?php

namespace App\Steps;

use App\Models\Deployment;
use App\Models\ProjectVariable;
use Illuminate\Support\Collection;

/**
 * Turns a project's variables into `export` lines for a step script.
 *
 * A Docker deploy gets them in two phases so that `build_arg` is an actual
 * boundary rather than a hint: only flagged variables exist while the image is
 * built, the rest appear afterwards, in time for `docker compose up`. Without
 * that split the flag would only govern what this application passes to
 * `docker build` -- the repository's own deploy/build.sh runs in the same shell
 * and could forward anything it liked.
 */
class VariableExports
{
    /**
     * Every variable, for steps that have no build phase to separate.
     */
    public static function all(?Deployment $deployment): string
    {
        return self::lines(self::variables($deployment));
    }

    /**
     * The variables that are allowed to be visible while the image is built.
     */
    public static function forBuild(?Deployment $deployment): string
    {
        return self::lines(self::variables($deployment)->filter(fn ($variable) => $variable->build_arg));
    }

    /**
     * Everything else -- exported once the build is done.
     */
    public static function forRuntime(?Deployment $deployment): string
    {
        return self::lines(self::variables($deployment)->reject(fn ($variable) => $variable->build_arg));
    }

    /**
     * @return Collection<int, ProjectVariable>
     */
    private static function variables(?Deployment $deployment): Collection
    {
        // Keys are interpolated into the script unquoted, so this is the last
        // point at which a row that never passed validation can be refused.
        return ($deployment?->project?->variables ?? collect())
            ->filter(fn ($variable) => ProjectVariable::keyIsAllowed((string) $variable->key))
            ->values();
    }

    /**
     * @param  Collection<int, ProjectVariable>  $variables
     */
    private static function lines(Collection $variables): string
    {
        if ($variables->isEmpty()) {
            return '';
        }

        return $variables
            ->map(fn ($variable) => 'export '.$variable->key.'='.escapeshellarg((string) $variable->value))
            ->implode("\n")."\n";
    }
}

<?php

namespace App\Steps;

use App\Models\Deployment;

/**
 * Instant rollback: retag a previously built SHA image back to :latest and
 * recreate the stack. The image's presence is re-verified at execution time —
 * the reconcile stamp can be stale (the scheduler prunes old images).
 */
class DockerRollbackScript
{
    /**
     * @param  array<string, mixed>  $config  requires app, sha, compose_file
     */
    public static function generate(Deployment $deployment, array $config): string
    {
        $app = $config['app'];
        $sha = $config['sha'];
        $composeFile = $config['compose_file'];

        $imageQ = escapeshellarg($app.':'.$sha);
        $latestQ = escapeshellarg($app.':latest');
        $webImageQ = escapeshellarg($app.'-web:'.$sha);
        $webLatestQ = escapeshellarg($app.'-web:latest');
        $composeFileQ = escapeshellarg($composeFile);

        // A rollback retags an existing image -- nothing is built, so there is
        // no build phase to withhold variables from.
        $exports = VariableExports::all($deployment);

        return <<<BASH
        set -euo pipefail
        {$exports}
        if ! docker image inspect {$imageQ} >/dev/null 2>&1; then
            echo "Image {$app}:{$sha} is no longer available (pruned). Use a rebuild rollback instead." >&2
            exit 1
        fi

        echo "Retagging {$app}:{$sha} as latest..."
        docker tag {$imageQ} {$latestQ}

        if docker image inspect {$webImageQ} >/dev/null 2>&1; then
            echo "Retagging {$app}-web:{$sha} as latest..."
            docker tag {$webImageQ} {$webLatestQ}
        fi

        COMPOSE_FILE={$composeFileQ}
        echo "Restarting {$app} via \$COMPOSE_FILE..."
        # --force-recreate: Compose 5.7 sometimes keeps a container on its old
        # image after the local tag moved to a new build (seen 2026-11-20 on
        # two -web containers). Recreating every service also restarts nginx
        # together with php-fpm, so nginx never keeps a stale upstream IP.
        docker compose -f "\$COMPOSE_FILE" up -d --force-recreate

        echo "Rolled back {$app} to {$sha}."
        BASH;
    }
}

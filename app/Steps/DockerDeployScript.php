<?php

namespace App\Steps;

use App\Models\Deployment;
use Illuminate\Support\Str;

/**
 * Generates the shell script for a docker_deploy step. The conventions mirror
 * the server repo's update-app.sh: shallow-clone the pushed repository into a
 * temp dir, build via deploy/build.sh > Dockerfile.dist > Dockerfile, tag
 * latest + commit SHA, optionally build a docker/nginx.Dockerfile companion
 * image, then docker compose up the app's stack.
 */
class DockerDeployScript
{
    /**
     * The image tags and compose path derive from the app name: the
     * repository name with dots turned into dashes, unless overridden.
     *
     * @param  array<string, mixed>  $config
     */
    public static function appName(Deployment $deployment, array $config): string
    {
        return $config['app'] ?? str_replace('.', '-', Str::afterLast($deployment->repository, '/'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function composeFile(Deployment $deployment, array $config): string
    {
        return $config['compose_file']
            ?? str_replace('{app}', self::appName($deployment, $config), config('deploy.compose_file_pattern'));
    }

    /**
     * The SHA-tagged image this step produces (used for rollbacks), or null
     * when the deployment has no commit SHA to tag with.
     *
     * @param  array<string, mixed>  $config
     */
    public static function shaImage(Deployment $deployment, array $config): ?string
    {
        return $deployment->commit_sha
            ? self::appName($deployment, $config).':'.$deployment->commit_sha
            : null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function generate(Deployment $deployment, array $config): string
    {
        $repository = $deployment->repository;
        $app = self::appName($deployment, $config);
        $composeFile = self::composeFile($deployment, $config);

        $sha = $deployment->commit_sha;
        $target = $config['target'] ?? null;

        $base = config('deploy.git_base');
        $token = config('deploy.git_token');
        $cloneUrl = $token
            ? preg_replace('#^https://#', 'https://x-access-token:'.$token.'@', $base).'/'.$repository.'.git'
            : $base.'/'.$repository.'.git';

        $targetFlag = $target ? '--target '.escapeshellarg($target).' ' : '';
        $shaTagApp = $sha ? ' -t '.escapeshellarg($app.':'.$sha) : '';
        $shaTagWeb = $sha ? ' -t '.escapeshellarg($app.'-web:'.$sha) : '';

        $appQ = escapeshellarg($app);
        $cloneUrlQ = escapeshellarg($cloneUrl);
        $composeFileQ = escapeshellarg($composeFile);

        $checkout = self::checkoutCommands($deployment, $config, $cloneUrlQ);

        return <<<BASH
        set -euo pipefail
        export GIT_TERMINAL_PROMPT=0

        BUILD_DIR="\$(mktemp -d /tmp/deploy-build-XXXXXX)"
        cleanup() { rm -rf "\$BUILD_DIR"; }
        trap cleanup EXIT

        {$checkout}
        cd "\$BUILD_DIR"

        echo "Building image {$app}..."
        if [ -x deploy/build.sh ]; then
            ./deploy/build.sh {$appQ}
        elif [ -f Dockerfile.dist ]; then
            docker build {$targetFlag}-f Dockerfile.dist -t {$appQ}:latest{$shaTagApp} .
        elif [ -f Dockerfile ]; then
            docker build {$targetFlag}-t {$appQ}:latest{$shaTagApp} .
        else
            echo "No deploy/build.sh, Dockerfile.dist or Dockerfile found in {$repository}." >&2
            exit 1
        fi

        if [ -f docker/nginx.Dockerfile ]; then
            echo "Building web image {$app}-web..."
            docker build -f docker/nginx.Dockerfile -t {$appQ}-web:latest{$shaTagWeb} .
        fi

        COMPOSE_FILE={$composeFileQ}
        echo "Starting {$app} via \$COMPOSE_FILE..."
        docker compose -f "\$COMPOSE_FILE" up -d

        echo "Deployed {$app}."
        BASH;
    }

    /**
     * How the build dir gets its sources. Rollback rebuilds fetch the exact
     * recorded commit; normal deploys clone the pushed branch (or the remote
     * default branch when the ref isn't a branch, e.g. manual deploys).
     *
     * @param  array<string, mixed>  $config
     */
    private static function checkoutCommands(Deployment $deployment, array $config, string $cloneUrlQ): string
    {
        $repository = $deployment->repository;

        if (($config['checkout_sha'] ?? false) && $deployment->commit_sha) {
            $shaQ = escapeshellarg($deployment->commit_sha);

            return <<<BASH
            echo "Fetching {$repository} at {$deployment->commit_sha}..."
            git init -q "\$BUILD_DIR"
            git -C "\$BUILD_DIR" remote add origin {$cloneUrlQ}
            git -C "\$BUILD_DIR" fetch -q --depth 1 origin {$shaQ}
            git -C "\$BUILD_DIR" checkout -q --detach FETCH_HEAD
            BASH;
        }

        $branch = Str::startsWith($deployment->ref, 'refs/heads/')
            ? Str::after($deployment->ref, 'refs/heads/')
            : null;

        $branchFlag = $branch ? '--branch '.escapeshellarg($branch).' ' : '';
        $branchLabel = $branch ?? 'default branch';

        return <<<BASH
        echo "Cloning {$repository} ({$branchLabel})..."
        git clone --quiet --depth 1 {$branchFlag}{$cloneUrlQ} "\$BUILD_DIR"
        BASH;
    }
}

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
     * @param  array<string, mixed>  $config
     */
    public static function generate(Deployment $deployment, array $config): string
    {
        $repository = $deployment->repository;

        // The app name drives image tags and the compose path convention.
        $app = $config['app'] ?? str_replace('.', '-', Str::afterLast($repository, '/'));

        $composeFile = $config['compose_file']
            ?? str_replace('{app}', $app, config('deploy.compose_file_pattern'));

        // Only real branch refs are cloned explicitly; anything else (e.g. a
        // manual deploy) clones the repository's default branch.
        $branch = Str::startsWith($deployment->ref, 'refs/heads/')
            ? Str::after($deployment->ref, 'refs/heads/')
            : null;

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
        $branchFlag = $branch ? '--branch '.escapeshellarg($branch).' ' : '';
        $branchLabel = $branch ?? 'default branch';
        $cloneUrlQ = escapeshellarg($cloneUrl);
        $composeFileQ = escapeshellarg($composeFile);

        return <<<BASH
        set -euo pipefail
        export GIT_TERMINAL_PROMPT=0

        BUILD_DIR="\$(mktemp -d /tmp/deploy-build-XXXXXX)"
        cleanup() { rm -rf "\$BUILD_DIR"; }
        trap cleanup EXIT

        echo "Cloning {$repository} ({$branchLabel})..."
        git clone --quiet --depth 1 {$branchFlag}{$cloneUrlQ} "\$BUILD_DIR"
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

        echo "Starting {$app} via {$composeFile}..."
        docker compose -f {$composeFileQ} up -d

        echo "Deployed {$app}."
        BASH;
    }
}

<?php

use App\Http\Controllers\Api\DeploymentController as ApiDeploymentStatusController;
use App\Http\Controllers\Api\ProjectController as ApiProjectController;
use App\Http\Controllers\Api\WorkflowController as ApiWorkflowController;
use App\Http\Controllers\ApiDeploymentController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('/deploy/{project:deploy_endpoint}', [ApiDeploymentController::class, 'store'])
    ->middleware(VerifyWebhookSignature::class)
    ->name('api.deployment.store');

/*
 * Management API (Sanctum tokens from Jetstream's "API Tokens" screen).
 *
 * Scriptable equivalent of the project screens, so projects can be registered and
 * deployed from a terminal -- the UI is behind Cloudflare Access and cannot be
 * driven headlessly. Everything is scoped to the token owner's teams.
 *
 * Deliberately limited to this application's own data: it never writes another
 * repository's compose files, because the deploy manager is meant to work
 * independently of any particular server config repo.
 */
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/projects', [ApiProjectController::class, 'index']);
    Route::post('/projects', [ApiProjectController::class, 'store']);
    Route::get('/projects/{project}', [ApiProjectController::class, 'show']);
    Route::patch('/projects/{project}', [ApiProjectController::class, 'update']);
    Route::post('/projects/{project}/deploy', [ApiProjectController::class, 'deploy']);

    Route::get('/projects/{project}/workflows', [ApiWorkflowController::class, 'index']);
    Route::post('/projects/{project}/workflows', [ApiWorkflowController::class, 'store']);
    Route::get('/projects/{project}/workflows/{workflow}', [ApiWorkflowController::class, 'show']);
    Route::patch('/projects/{project}/workflows/{workflow}', [ApiWorkflowController::class, 'update']);
    Route::delete('/projects/{project}/workflows/{workflow}', [ApiWorkflowController::class, 'destroy']);

    // Steps are what a deployment actually runs; a workflow without them fails
    // on every trigger. PUT replaces the list (the editor's own semantics),
    // POST appends to it.
    Route::get('/projects/{project}/workflows/{workflow}/steps', [ApiWorkflowController::class, 'steps']);
    Route::put('/projects/{project}/workflows/{workflow}/steps', [ApiWorkflowController::class, 'replaceSteps']);
    Route::post('/projects/{project}/workflows/{workflow}/steps', [ApiWorkflowController::class, 'addSteps']);
    Route::delete('/projects/{project}/workflows/{workflow}/steps/{step}', [ApiWorkflowController::class, 'destroyStep']);

    Route::get('/deployments/{deployment}', [ApiDeploymentStatusController::class, 'show']);
});

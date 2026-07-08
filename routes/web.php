<?php

use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DeploymentRollbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegacyLogController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServerConnectionTestController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerSetupController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index'])->name('page.index');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');

    // Everything below is scoped to the user's *current* team (Jetstream tracks one
    // active team per session). All models bind by their public ULID; controllers
    // verify each resource belongs to the current team.
    Route::get('/team', [TeamController::class, 'show'])->name('team.show');

    Route::get('/servers', [ServerController::class, 'index'])->name('server.index');
    Route::get('/servers/create', [ServerController::class, 'create'])->name('server.create');
    Route::post('/servers', [ServerController::class, 'store'])->name('server.store');
    Route::get('/servers/{server}', [ServerController::class, 'show'])->name('server.show');
    Route::post('/servers/{server}/setup', [ServerSetupController::class, 'store'])->name('server.setup.store');
    Route::post('/servers/{server}/test-connection', [ServerConnectionTestController::class, 'store'])->name('server.test.store');

    Route::get('/projects/create', [ProjectController::class, 'create'])->name('project.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('project.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('project.show');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('project.update');

    // scopeBindings() forces the nested {workflow}/{deployment} to belong to {project}.
    Route::scopeBindings()->group(function () {
        Route::get('/projects/{project}/workflows/create', [WorkflowController::class, 'create'])->name('workflow.create');
        Route::post('/projects/{project}/workflows', [WorkflowController::class, 'store'])->name('workflow.store');
        Route::get('/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'show'])->name('workflow.show');
        Route::delete('/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflow.destroy');
        Route::get('/projects/{project}/workflows/{workflow}/edit', [WorkflowController::class, 'edit'])->name('workflow.edit');
        Route::put('/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflow.update');

        Route::post('/projects/{project}/deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])->name('deployment.cancel');
        Route::post('/projects/{project}/deployments/{deployment}/rollback', [DeploymentRollbackController::class, 'store'])->name('deployment.rollback');
    });

    Route::post('/deploy/{project:deploy_endpoint}', [DeploymentController::class, 'store'])->name('deployment.store');

    // Read-only viewer for logs written by an external deploy hook
    // (hidden unless deploy.legacy_logs_path is configured).
    Route::get('/legacy-logs', [LegacyLogController::class, 'index'])->name('legacy-log.index');
    Route::get('/legacy-logs/{file}', [LegacyLogController::class, 'show'])
        ->where('file', '[\w][\w.-]*\.log')
        ->name('legacy-log.show');
});

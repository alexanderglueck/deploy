<?php

use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
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

    // Deploy's per-team overview (servers + projects). Jetstream owns /teams/{team}
    // for team settings, so the deploy overview lives under /overview.
    Route::get('/teams/{team}/overview', [TeamController::class, 'show'])->name('team.show');

    Route::get('/teams/{team}/servers', [ServerController::class, 'index'])->name('server.index');
    Route::get('/teams/{team}/servers/create', [ServerController::class, 'create'])->name('server.create');
    Route::post('/teams/{team}/servers', [ServerController::class, 'store'])->name('server.store');
    Route::get('/teams/{team}/servers/{server}', [ServerController::class, 'show'])->name('server.show');
    Route::post('/teams/{team}/servers/{server}/setup', [ServerSetupController::class, 'store'])->name('server.setup.store');

    Route::get('/teams/{team}/projects/create', [ProjectController::class, 'create'])->name('project.create');
    Route::post('/teams/{team}/projects', [ProjectController::class, 'store'])->name('project.store');
    Route::get('/teams/{team}/projects/{project}', [ProjectController::class, 'show'])->name('project.show');

    Route::get('/teams/{team}/projects/{project}/workflows/create', [WorkflowController::class, 'create'])->name('workflow.create');
    Route::post('/teams/{team}/projects/{project}/workflows', [WorkflowController::class, 'store'])->name('workflow.store');
    Route::get('/teams/{team}/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'show'])->name('workflow.show');
    Route::delete('/teams/{team}/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'destroy'])->name('workflow.destroy');
    Route::get('/teams/{team}/projects/{project}/workflows/{workflow}/edit', [WorkflowController::class, 'edit'])->name('workflow.edit');
    Route::put('/teams/{team}/projects/{project}/workflows/{workflow}', [WorkflowController::class, 'update'])->name('workflow.update');

    Route::post('/deploy/{project:deploy_endpoint}', [DeploymentController::class, 'store'])->name('deployment.store');
});

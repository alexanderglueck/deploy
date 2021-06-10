<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'PageController@index')->name('page.index');

Auth::routes();

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');

    Route::get('/teams/{team}', [\App\Http\Controllers\TeamController::class, 'show'])->name('team.show');

    Route::get('/teams/{team}/servers', [\App\Http\Controllers\ServerController::class, 'index'])->name('server.index');
    Route::get('/teams/{team}/servers/create', [\App\Http\Controllers\ServerController::class, 'create'])->name('server.create');
    Route::post('/teams/{team}/servers', [\App\Http\Controllers\ServerController::class, 'store'])->name('server.store');
    Route::get('/teams/{team}/servers/{server}', [\App\Http\Controllers\ServerController::class, 'show'])->name('server.show');
    Route::post('/teams/{team}/servers/{server}/setup', [\App\Http\Controllers\ServerSetupController::class, 'store'])->name('server.setup.store');

    Route::get('/teams/{team}/projects/create', [\App\Http\Controllers\ProjectController::class, 'create'])->name('project.create');
    Route::post('/teams/{team}/projects', [\App\Http\Controllers\ProjectController::class, 'store'])->name('project.store');
    Route::get('/teams/{team}/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'show'])->name('project.show');

    Route::get('/teams/{team}/projects/{project}/workflows/create', [\App\Http\Controllers\WorkflowController::class, 'create'])->name('workflow.create');
    Route::post('/teams/{team}/projects/{project}/workflows', [\App\Http\Controllers\WorkflowController::class, 'store'])->name('workflow.store');
    Route::get('/teams/{team}/projects/{project}/workflows/{workflow}', [\App\Http\Controllers\WorkflowController::class, 'show'])->name('workflow.show');
    Route::delete('/teams/{team}/projects/{project}/workflows/{workflow}', [\App\Http\Controllers\WorkflowController::class, 'destroy'])->name('workflow.destroy');
    Route::get('/teams/{team}/projects/{project}/workflows/{workflow}/edit', [\App\Http\Controllers\WorkflowController::class, 'edit'])->name('workflow.edit');
    Route::put('/teams/{team}/projects/{project}/workflows/{workflow}', [\App\Http\Controllers\WorkflowController::class, 'update'])->name('workflow.update');

    Route::post('/deploy/{project:deploy_endpoint}', [\App\Http\Controllers\DeploymentController::class, 'store'])->name('deployment.store');
});

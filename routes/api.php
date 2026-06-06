<?php

use App\Http\Controllers\ApiDeploymentController;
use Illuminate\Support\Facades\Route;

Route::get('/deploy/{project:deploy_endpoint}', [ApiDeploymentController::class, 'store'])
    ->name('api.deployment.store');

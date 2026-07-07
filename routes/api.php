<?php

use App\Http\Controllers\ApiDeploymentController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('/deploy/{project:deploy_endpoint}', [ApiDeploymentController::class, 'store'])
    ->middleware(VerifyWebhookSignature::class)
    ->name('api.deployment.store');

<?php

use App\Http\Controllers\PaymongoWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/paymongo/webhook', [PaymongoWebhookController::class, 'handle']);
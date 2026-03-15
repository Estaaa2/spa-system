<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Owner\SubscriptionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/paymongo/webhook', [SubscriptionController::class, 'webhook']);
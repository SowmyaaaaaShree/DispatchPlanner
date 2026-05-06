<?php

use App\Http\Controllers\DispatchBatchController;
use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/dispatch-batches', [DispatchBatchController::class, 'store']);
Route::get('/dispatch-batches/{batch}', [DispatchBatchController::class, 'show']);
Route::post('/dispatch-batches/{batch}/recompute', [DispatchBatchController::class, 'recompute']);
Route::get('/healthz', [HealthController::class, 'index']);
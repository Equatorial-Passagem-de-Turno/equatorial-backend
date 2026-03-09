<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\OccurrenceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Shift routes
    Route::prefix('shifts')->group(function () {
        Route::post('/start', [ShiftController::class, 'start']);
        Route::post('/finish', [ShiftController::class, 'finish']);
    });

    // Occurrence routes
    Route::prefix('occurrences')->group(function () {
        Route::post('/', [OccurrenceController::class, 'store']);
    });
});

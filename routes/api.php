<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\OccurrenceController;
use App\Http\Controllers\OperationDeskController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/operation-desks', [OperationDeskController::class, 'index']);
    Route::post('/operation-desks', [OperationDeskController::class, 'store']);
    Route::put('/operation-desks/{id}', [OperationDeskController::class, 'update']);
    Route::delete('/operation-desks/{id}', [OperationDeskController::class, 'destroy']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Shift routes
    Route::prefix('shifts')->group(function () {
        Route::post('/start', [ShiftController::class, 'start']);
        Route::post('/finish', [ShiftController::class, 'finish']);
        Route::post('/reopen', [ShiftController::class, 'reopen']);
        Route::post('/{shift}/notify', [ShiftController::class, 'sendFinishEmail']);
        Route::get('/{shift}', [ShiftController::class, 'show'])->whereNumber('shift');
        Route::get('/operators/active', [ShiftController::class, 'getActiveOperatorsSummary']);
        Route::get('/current', [ShiftController::class, 'getCurrentShift']);
        Route::get('/handover/previous', [ShiftController::class, 'getPreviousShift']);
        Route::get('/previous-details', [ShiftController::class, 'getPreviousShiftDetails']);
        Route::get('/by-date', [ShiftController::class, 'getShiftsByDate']);
        Route::get('/by-user/{userId}', [ShiftController::class, 'getShiftsByUser']);
    });

    // Occurrence routes
    Route::prefix('occurrences')->group(function () {
        Route::get('/', [OccurrenceController::class, 'index']);
        Route::post('/', [OccurrenceController::class, 'store']);
        Route::get('/{id}', [OccurrenceController::class, 'show']);
        Route::put('/{id}', [OccurrenceController::class, 'update']);
        Route::delete('/{id}', [OccurrenceController::class, 'destroy']);
        Route::post('/bulk', [OccurrenceController::class, 'bulkStore']);
    });
});

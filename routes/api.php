<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\OccurrenceController;
use App\Http\Controllers\OperationDeskController;
use App\Http\Controllers\RoleController;
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

    // Shift routes
    Route::prefix('shifts')->group(function () {
        Route::post('/start', [ShiftController::class, 'start']);
        Route::post('/finish', [ShiftController::class, 'finish']);
        Route::get('/handover/previous', [ShiftController::class, 'getPreviousShift']);
    });

    // Occurrence routes
    Route::prefix('occurrences')->group(function () {
        Route::get('/', [OccurrenceController::class, 'index']);       // Listar todas
        Route::post('/', [OccurrenceController::class, 'store']);      // Criar nova
        Route::get('/{id}', [OccurrenceController::class, 'show']);    // Mostrar uma
        Route::put('/{id}', [OccurrenceController::class, 'update']);  // Atualizar
        Route::delete('/{id}', [OccurrenceController::class, 'destroy']); // Excluir
        Route::post('/bulk', [OccurrenceController::class, 'bulkStore']);
    });
});

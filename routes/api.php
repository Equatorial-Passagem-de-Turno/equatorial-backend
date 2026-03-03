<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcorrenciaController;
use App\Models\User;

Route::post('/login', [AuthController::class, 'authenticate']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/turnos/iniciar', [TurnoController::class, 'iniciar']);
    Route::post('/turnos/encerrar', [TurnoController::class, 'encerrar']);
    Route::post('/ocorrencias', [OcorrenciaController::class, 'store']);
});

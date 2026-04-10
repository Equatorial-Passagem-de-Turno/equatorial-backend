<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/setup-banco', function () {
    try {
        // Executa as migrations forçadamente (necessário em produção)
        Artisan::call('migrate', ['--force' => true]);
        
        return response()->json([
            'status' => 'sucesso',
            'mensagem' => 'Migrations executadas com sucesso no Supabase!'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'erro',
            'mensagem' => $e->getMessage()
        ]);
    }
});
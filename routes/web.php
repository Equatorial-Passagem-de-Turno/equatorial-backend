<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/setup-banco', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        
        Artisan::call('db:seed', ['--force' => true]);

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
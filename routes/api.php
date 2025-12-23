<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagoController;

use Illuminate\Support\Facades\DB;

Route::post('/pago', [PagoController::class, 'crearPago']);
Route::post('/webhook', [PagoController::class, 'webhook']);


Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});


Route::get('/db-wakeup', function () {
    DB::statement('SELECT 1');
    return response()->json(['db' => 'awake']);
});

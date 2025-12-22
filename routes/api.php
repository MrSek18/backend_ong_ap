<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagoController;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

Route::post('/pago', [PagoController::class, 'crearPago']);
Route::post('/webhook', [PagoController::class, 'webhook']);
Route::get('/health', function () {
    try {
        DB::select('SELECT 1');
        return response()->json(['status' => 'ok', 'db' => 'ok']);
    } catch (\Exception $e) {
        Log::warning("Health-check falló: " . $e->getMessage());
        return response()->json(['status' => 'ok', 'db' => 'down']);
    }
});

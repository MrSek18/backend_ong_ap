<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PagoController;

Route::post('/pago', [PagoController::class, 'crearPago']);
Route::post('/webhook', [PagoController::class, 'webhook']);

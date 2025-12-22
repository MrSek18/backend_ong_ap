<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use MercadoPago\MercadoPagoConfig;
use Illuminate\Support\Facades\Log;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
{
    $token = env('MERCADOPAGO_ACCESS_TOKEN');
    if (!empty($token)) {
        \MercadoPago\MercadoPagoConfig::setAccessToken($token);
    } else {
        Log::warning("MercadoPago Access Token no definido en build");
    }
}


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configuración global de Mercado Pago
        MercadoPagoConfig::setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));
    }
}

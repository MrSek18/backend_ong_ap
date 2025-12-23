<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Common\RequestOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\Exceptions\MPApiException;

class PagoController extends Controller
{
    /**
     * Espera hasta que la DB despierte (máx X segundos)
     */
    private function waitForDatabase(int $timeoutSeconds = 7): bool
    {
        $start = microtime(true);

        while ((microtime(true) - $start) < $timeoutSeconds) {
            try {
                DB::statement('SELECT 1');
                return true;
            } catch (\Exception $e) {
                usleep(500000); // 0.5 segundos
            }
        }

        return false;
    }

    public function crearPago(Request $request)
    {
        Log::info('Entró a crearPago');

        $request->validate([
            'token' => 'required|string',
            'monto' => 'required|numeric|min:1',
            'email' => 'required|email',
            'plan' => 'required|in:unica,mensual',
            'payment_method_id' => 'required|string',
            'installments' => 'required|integer|min:1',
            'identification_type' => 'required|string',
            'identification_number' => 'required|string',
            'issuer_id' => 'nullable|string',
        ]);

        /** Crear pago en MercadoPago  */
        $client = new PaymentClient();
        $options = new RequestOptions();
        $options->setAccessToken(config('services.mercadopago.access_token'));

        try {
            $payment = $client->create([
                'transaction_amount' => (float) $request->monto,
                'token' => $request->token,
                'description' => 'Donación - ' . $request->plan,
                'installments' => $request->installments,
                'payment_method_id' => $request->payment_method_id,
                'payer' => [
                    'email' => $request->email,
                    'identification' => [
                        'type' => $request->identification_type,
                        'number' => $request->identification_number,
                    ],
                ],
            ], $options);

        } catch (MPApiException $e) {
            $response = $e->getApiResponse();

            Log::error('Error MercadoPago', [
                'status' => $response->getStatusCode(),
                'content' => $response->getContent(),
            ]);

            return response()->json([
                'error' => 'Error al procesar el pago',
                'details' => $response->getContent(),
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error inesperado MercadoPago', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unexpected error',
            ], 500);
        }

        /**  DB y guardar (HASTA 7s) */
        $donacionId = null;

        if ($this->waitForDatabase(7)) {
            try {
                $donacionId = DB::table('donaciones')->insertGetId([
                    'payment_id' => $payment->id,
                    'monto' => $payment->transaction_amount,
                    'estado' => $payment->status,
                    'plan' => $request->plan,
                    'email' => $request->email,
                    'identification_type' => $request->identification_type,
                    'identification_number' => $request->identification_number,
                    'payment_method_id' => $request->payment_method_id,
                    'installments' => $request->installments,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('Donación guardada correctamente', [
                    'payment_id' => $payment->id,
                    'donacion_id' => $donacionId,
                ]);

            } catch (\Exception $e) {
                Log::error('DB activa pero insert falló', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::error('DB no despertó tras 7 segundos', [
                'payment_id' => $payment->id,
            ]);
        }

        /**  Respuesta */
        return response()->json([
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'donacion_id' => $donacionId,
            'db_saved' => $donacionId !== null,
        ]);
    }

    
    public function webhook(Request $request)
    {
        $data = $request->all();
        Log::info('Webhook recibido', $data);

        if (
            isset($data['type'], $data['data']['id']) &&
            $data['type'] === 'payment'
        ) {
            $paymentId = $data['data']['id'];

            try {
                $client = new PaymentClient();
                $options = new RequestOptions();
                $options->setAccessToken(config('services.mercadopago.access_token'));

                $payment = $client->get($paymentId, $options);

                DB::table('donaciones')->updateOrInsert(
                    ['payment_id' => $payment->id],
                    [
                        'monto' => $payment->transaction_amount,
                        'estado' => $payment->status,
                        'plan' => str_contains($payment->description, 'mensual')
                            ? 'mensual'
                            : 'unica',
                        'email' => $payment->payer->email,
                        'identification_type' => $payment->payer->identification->type ?? null,
                        'identification_number' => $payment->payer->identification->number ?? null,
                        'payment_method_id' => $payment->payment_method_id,
                        'installments' => $payment->installments,
                        'updated_at' => now(),
                    ]
                );

                Log::info('Webhook sincronizó donación', [
                    'payment_id' => $payment->id,
                ]);

            } catch (\Exception $e) {
                Log::error('Error webhook MercadoPago', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }
}

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

        Log::info('Payload recibido en backend', $request->all());

        $client = new PaymentClient();

        $options = new RequestOptions();
        $options->setAccessToken(config('services.mercadopago.access_token'));

        /**  MercadoPago  */
        try {
            $payload = [
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
            ];

            Log::info('Payload enviado a MercadoPago', $payload);

            $payment = $client->create($payload, $options);

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
            Log::error('Error inesperado al crear pago', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unexpected error',
                'details' => $e->getMessage(),
            ], 500);
        }

        /**  DB  */
        $donacionId = null;

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
        } catch (\Exception $e) {
            Log::warning('DB sleeping, se guardará vía webhook', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        /**  Respuesta OK */
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
                $paymentClient = new PaymentClient();

                $options = new RequestOptions();
                $options->setAccessToken(config('services.mercadopago.access_token'));

                $payment = $paymentClient->get($paymentId, $options);

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

            } catch (MPApiException $e) {
                Log::error('Error MercadoPago webhook', [
                    'message' => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                Log::error('Error inesperado webhook', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }
}

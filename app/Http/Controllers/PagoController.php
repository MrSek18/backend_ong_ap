<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\Client\Payment\PaymentClient;
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

        Log::info('Payload recibido en backend:', $request->all());

        $client = new PaymentClient(env('MERCADOPAGO_ACCESS_TOKEN'));

        try {
            $payload = [
                "transaction_amount" => (float) $request->monto,
                "token" => $request->token,
                "description" => "Donación - " . $request->plan,
                "installments" => $request->installments,
                "payment_method_id" => $request->payment_method_id,
                "payer" => [
                    "email" => $request->email,
                    "identification" => [
                        "type" => $request->identification_type,
                        "number" => $request->identification_number
                    ]
                ]
            ];

            Log::info('Payload enviado a MercadoPago:', $payload);

            $payment = $client->create($payload);
        } catch (MPApiException $e) {
            $response = $e->getApiResponse();
            Log::error('Error al crear pago', [
                'status' => $response->getStatusCode(),
                'content' => $response->getContent(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'details' => $response->getContent()
            ], 400);
        }

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
            'updated_at' => now()
        ]);

        return response()->json([
            "payment_id" => $payment->id,
            "status" => $payment->status,
            "donacion_id" => $donacionId
        ]);
    }

    public function webhook(Request $request)
    {
        $data = $request->all();
        Log::info('Webhook recibido:', $data);

        if (isset($data['type'], $data['data']['id']) && $data['type'] === 'payment') {
            $paymentId = $data['data']['id'];

            try {
                $paymentClient = new PaymentClient();
                $payment = $paymentClient->get($paymentId);

                DB::table('donaciones')->updateOrInsert(
                    ['payment_id' => $payment->id],
                    [
                        'monto' => $payment->transaction_amount,
                        'estado' => $payment->status,
                        'plan' => in_array($payment->description, ['unica', 'mensual']) ? $payment->description : 'unica',
                        'email' => $payment->payer->email ?? DB::table('donaciones')->where('payment_id', $payment->id)->value('email'),
                        'identification_type' => $payment->payer->identification->type ?? DB::table('donaciones')->where('payment_id', $payment->id)->value('identification_type'),
                        'identification_number' => $payment->payer->identification->number ?? DB::table('donaciones')->where('payment_id', $payment->id)->value('identification_number'),
                        'payment_method_id' => $payment->payment_method_id ?? DB::table('donaciones')->where('payment_id', $payment->id)->value('payment_method_id'),
                        'installments' => $payment->installments ?? DB::table('donaciones')->where('payment_id', $payment->id)->value('installments'),
                        'updated_at' => now(),
                    ]
                );
            } catch (MPApiException $e) {
                Log::error('Error al obtener pago: ' . $e->getMessage(), $e->getApiResponse());
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }
}

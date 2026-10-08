<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Payments\PaymentManager;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/** إشعارات بوابات الدفع. نتحقق من التوقيع ثم نسأل البوابة عن الحالة بأنفسنا. */
class WebhookController extends Controller
{
    public function zaincash(Request $request, PaymentManager $gateways, PaymentService $payments): JsonResponse
    {
        return $this->handle('zaincash', $request, $gateways, $payments);
    }

    public function qicard(Request $request, PaymentManager $gateways, PaymentService $payments): JsonResponse
    {
        return $this->handle('qicard', $request, $gateways, $payments);
    }

    private function handle(string $gateway, Request $request, PaymentManager $gateways, PaymentService $payments): JsonResponse
    {
        try {
            $refs = $gateways->driver($gateway)->parseWebhook($request);
        } catch (Throwable $e) {
            Log::warning("Rejected {$gateway} webhook", ['error' => $e->getMessage()]);

            return response()->json(['message' => 'invalid signature'], 400);
        }

        $payment = Payment::query()
            ->where('gateway', $gateway)
            ->where(function ($q) use ($refs) {
                $q->when($refs['external_ref'], fn ($q2) => $q2->orWhere('external_ref', $refs['external_ref']))
                    ->when($refs['gateway_txn_id'], fn ($q2) => $q2->orWhere('gateway_txn_id', $refs['gateway_txn_id']));
            })
            ->first();

        if (! $payment || (! $refs['external_ref'] && ! $refs['gateway_txn_id'])) {
            return response()->json(['message' => 'unknown payment'], 404);
        }

        try {
            $status = $payments->confirm($payment);
        } catch (Throwable $e) {
            Log::warning("{$gateway} webhook confirm failed", ['payment' => $payment->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'retry later'], 503);
        }

        return response()->json(['status' => $status]);
    }
}

<?php

namespace App\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * بوابة كي كارد (https://developers-gate.qi.iq)
 * Basic Auth + X-Terminal-Id ← POST /payment ← formUrl ← finishPaymentUrl ← GET /payment/{id}/status
 * الإشعارات موقّعة RSA-SHA256 بمفتاح البوابة العام (يُطلب من دعم كي كارد).
 */
class QiCardGateway implements PaymentGateway
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function initiate(Payment $payment, string $returnUrl): string
    {
        $json = $this->api()->post('/payment', [
            'requestId' => $payment->external_ref,
            'amount' => $payment->amount,
            'currency' => 'IQD',
            'locale' => $this->config['locale'] ?? 'en_US',
            'finishPaymentUrl' => $returnUrl,
            'notificationUrl' => route('webhooks.qicard'),
            'customerInfo' => array_filter([
                'firstName' => $payment->user?->name,
                'phone' => $payment->user?->phone ? '00964'.substr($payment->user->phone, 1) : null,
            ]),
            'additionalInfo' => ['payment' => (string) $payment->id],
        ])->throw()->json() ?? [];

        $paymentId = $json['paymentId'] ?? null;
        $formUrl = $json['formUrl'] ?? null;
        if (! $paymentId || ! $formUrl) {
            Log::error('QiCard create payment: unexpected response', ['payment' => $payment->id, 'response' => $json]);
            throw new RuntimeException('استجابة غير متوقعة من كي كارد.');
        }

        $payment->gateway_txn_id = (string) $paymentId;
        $payment->remember(['init' => $json]);

        return (string) $formUrl;
    }

    public function confirm(Payment $payment, array $returnQuery = []): GatewayResult
    {
        if (! $payment->gateway_txn_id) {
            return new GatewayResult(GatewayResult::FAILED);
        }

        $json = $this->api()->get('/payment/'.urlencode($payment->gateway_txn_id).'/status')->throw()->json() ?? [];
        $status = strtoupper((string) ($json['status'] ?? ''));
        $canceled = (bool) ($json['canceled'] ?? false);

        return new GatewayResult(
            status: match (true) {
                $status === 'SUCCESS' && ! $canceled => GatewayResult::PAID,
                $canceled, in_array($status, ['FAILED', 'AUTHENTICATION_FAILED'], true) => GatewayResult::FAILED,
                default => GatewayResult::PENDING,
            },
            amount: isset($json['amount']) && is_numeric($json['amount']) ? (int) round((float) $json['amount']) : null,
            reference: $payment->gateway_txn_id,
            raw: $json,
        );
    }

    public function refund(Payment $payment, int $amount, string $reason): GatewayResult
    {
        $json = $this->api()->post('/payment/'.urlencode((string) $payment->gateway_txn_id).'/refund', [
            'requestId' => (string) Str::uuid(),
            'amount' => $amount,
            'message' => mb_substr($reason, 0, 200),
        ])->throw()->json() ?? [];

        $status = strtoupper((string) ($json['status'] ?? ''));

        return new GatewayResult(
            status: match ($status) {
                'SUCCESS' => GatewayResult::PAID,
                'CREATED' => GatewayResult::PENDING,
                default => GatewayResult::FAILED,
            },
            amount: $amount,
            reference: $json['refundId'] ?? null,
            raw: $json,
        );
    }

    public function parseWebhook(Request $request): array
    {
        $data = $request->json()->all();
        $signature = base64_decode((string) $request->header('X-Signature', ''), true);
        if ($signature === false || $signature === '') {
            throw new RuntimeException('إشعار كي كارد بدون توقيع.');
        }

        // النص الموقّع حسب توثيق كي كارد: paymentId|amount(3 منازل)|currency|creationDate|status
        $signed = implode('|', [
            $data['paymentId'] ?? '-',
            number_format((float) ($data['amount'] ?? 0), 3, '.', ''),
            $data['currency'] ?? '-',
            $data['creationDate'] ?? '-',
            $data['status'] ?? '-',
        ]);

        $path = (string) ($this->config['public_key_path'] ?? '');
        $pem = is_file(base_path($path)) ? file_get_contents(base_path($path)) : (is_file($path) ? file_get_contents($path) : false);
        if (! $pem) {
            throw new RuntimeException('مفتاح كي كارد العام غير موجود.');
        }
        if (openssl_verify($signed, $signature, $pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('توقيع إشعار كي كارد غير صحيح.');
        }

        return [
            'external_ref' => $data['requestId'] ?? null,
            'gateway_txn_id' => $data['paymentId'] ?? null,
        ];
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->withBasicAuth((string) $this->config['username'], (string) $this->config['password'])
            ->withHeaders(['X-Terminal-Id' => (string) $this->config['terminal_id']])
            ->acceptJson()
            ->asJson()
            ->timeout(25);
    }
}

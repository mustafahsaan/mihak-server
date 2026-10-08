<?php

namespace App\Payments;

use App\Models\Payment;
use App\Support\Jwt;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * زين كاش Payment Gateway v2 (https://docs.zaincash.iq)
 * OAuth2 client_credentials ← transaction/init ← تحويل المطوّر ← الرجوع مع token ← transaction/inquiry
 *
 * ملاحظة: التوثيق لا يعرض أمثلة كاملة للاستجابات، لذلك نقرأ الحقول من أكثر من مسار.
 * أكّد أسماء الحقول مع فريق زين كاش أثناء التفعيل وعدّل مصفوفات pick إن لزم.
 */
class ZainCashGateway implements PaymentGateway
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function initiate(Payment $payment, string $returnUrl): string
    {
        $separator = str_contains($returnUrl, '?') ? '&' : '?';
        $body = [
            'language' => $this->config['language'] ?? 'ar',
            'externalReferenceId' => $payment->external_ref,
            'orderId' => 'MHK-'.$payment->id,
            'serviceType' => $this->config['service_type'] ?? 'Testing service',
            'amount' => ['value' => (string) $payment->amount, 'currency' => 'IQD'],
            'redirectUrls' => [
                'successUrl' => $returnUrl.$separator.'result=success',
                'failureUrl' => $returnUrl.$separator.'result=failure',
            ],
        ];

        $json = $this->api('payment:read payment:write')
            ->post('/api/v2/payment-gateway/transaction/init', $body)
            ->throw()
            ->json() ?? [];

        $transactionId = PaymentManager::pick($json, ['transactionId', 'data.transactionId', 'transaction.id', 'data.transaction.id', 'id', 'data.id']);
        $redirectUrl = PaymentManager::pick($json, ['redirectUrl', 'data.redirectUrl', 'paymentUrl', 'data.paymentUrl', 'url', 'data.url']);

        if (! $transactionId || ! $redirectUrl) {
            Log::error('ZainCash init: unexpected response', ['payment' => $payment->id, 'response' => $json]);
            throw new RuntimeException('استجابة غير متوقعة من زين كاش.');
        }

        $payment->gateway_txn_id = (string) $transactionId;
        $payment->remember(['init' => $json]);

        return (string) $redirectUrl;
    }

    public function confirm(Payment $payment, array $returnQuery = []): GatewayResult
    {
        if (! empty($returnQuery['token']) && ! empty($this->config['api_key'])) {
            try {
                $payment->remember(['return_token' => Jwt::decodeHs256((string) $returnQuery['token'], (string) $this->config['api_key'])]);
            } catch (Throwable $e) {
                // التوقيع لا يكفي وحده للحكم؛ الاستعلام المباشر أدناه هو المرجع
                Log::warning('ZainCash return token rejected', ['payment' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        if (! $payment->gateway_txn_id) {
            return new GatewayResult(GatewayResult::FAILED);
        }

        $json = $this->api('payment:read')
            ->get('/api/v2/payment-gateway/transaction/inquiry/'.urlencode($payment->gateway_txn_id))
            ->throw()
            ->json() ?? [];

        $status = strtoupper((string) PaymentManager::pick($json, ['status', 'data.status', 'transaction.status', 'data.transaction.status', 'currentStatus']));
        $amount = PaymentManager::pick($json, ['amount.value', 'data.amount.value', 'transaction.amount.value', 'amount', 'data.amount']);

        return new GatewayResult(
            status: match ($status) {
                'SUCCESS' => GatewayResult::PAID,
                'FAILED', 'EXPIRED' => GatewayResult::FAILED,
                'REFUNDED', 'PARTIALLY_REFUNDED' => GatewayResult::REFUNDED,
                default => GatewayResult::PENDING,
            },
            amount: is_numeric($amount) ? (int) round((float) $amount) : null,
            reference: $payment->gateway_txn_id,
            raw: $json,
        );
    }

    public function refund(Payment $payment, int $amount, string $reason): GatewayResult
    {
        $full = $amount >= $payment->amount;
        $body = ['transactionId' => $payment->gateway_txn_id, 'reason' => mb_substr($reason, 0, 44)];
        if (! $full) {
            $body['amount'] = $amount;
        }

        $json = $this->api('reverse:read reverse:write')
            ->post($full ? '/api/v2/payment-gateway/transaction/reverse' : '/api/v2/payment-gateway/transaction/partial-reverse', $body)
            ->throw()
            ->json() ?? [];

        // التوثيق ينبّه: استجابة 200 قد تحمل فشلاً، لذلك نقرأ الحالة
        $status = strtoupper((string) PaymentManager::pick($json, ['status', 'data.status']));

        return new GatewayResult(
            status: in_array($status, ['SUCCESS', 'COMPLETED', 'REFUNDED', 'PARTIALLY_REFUNDED'], true) ? GatewayResult::PAID
                : (in_array($status, ['PENDING', 'PROCESSING'], true) ? GatewayResult::PENDING : GatewayResult::FAILED),
            amount: $amount,
            reference: (string) PaymentManager::pick($json, ['reversalReferenceId', 'data.reversalReferenceId']),
            raw: $json,
        );
    }

    public function parseWebhook(Request $request): array
    {
        $token = (string) $request->input('webhook_token', '');
        $claims = Jwt::decodeHs256($token, (string) ($this->config['api_key'] ?? ''));

        return [
            'external_ref' => PaymentManager::pick($claims, ['merchantReferenceId', 'data.merchantReferenceId', 'externalReferenceId']),
            'gateway_txn_id' => PaymentManager::pick($claims, ['transactionId', 'data.transactionId']),
        ];
    }

    /** صرف مبلغ إلى محفظة زين كاش (لسحوبات المختبِرين). يعيد رقم عنصر الصرف للمتابعة. */
    public function disburse(string $msisdn, int $amount, string $reference, string $comment): string
    {
        $json = $this->api('disbursement:read disbursement:write')
            ->post('/api/v2/cash-disbursement/bulk', [
                'externalReferenceId' => $reference,
                'batchName' => 'MIHAK '.$reference,
                'cashDisbursementBatchItems' => [[
                    'recipientMsisdn' => $msisdn,
                    'amount' => $amount,
                    'comment' => mb_substr($comment, 0, 150),
                ]],
            ])
            ->throw()
            ->json() ?? [];

        $itemId = PaymentManager::pick($json, ['cashDisbursementBatchItems.0.id', 'data.cashDisbursementBatchItems.0.id', 'items.0.id', 'data.items.0.id']);
        if (! $itemId) {
            Log::error('ZainCash disbursement: unexpected response', ['reference' => $reference, 'response' => $json]);
            throw new RuntimeException('استجابة غير متوقعة من زين كاش عند الصرف.');
        }

        return (string) $itemId;
    }

    /** @return 'paid'|'pending'|'failed' */
    public function disbursementStatus(string $itemId): string
    {
        $json = $this->api('disbursement:read')
            ->get('/api/v2/cash-disbursement/batch-items/'.urlencode($itemId))
            ->throw()
            ->json() ?? [];

        return match (strtoupper((string) PaymentManager::pick($json, ['status', 'data.status']))) {
            'SUCCESS' => GatewayResult::PAID,
            'FAILED' => GatewayResult::FAILED,
            default => GatewayResult::PENDING,
        };
    }

    private function api(string $scope): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(25)
            ->withToken($this->token($scope));
    }

    private function token(string $scope): string
    {
        $cacheKey = 'zaincash_token_'.md5($scope.($this->config['client_id'] ?? ''));
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $json = Http::asForm()
            ->acceptJson()
            ->timeout(20)
            ->post(rtrim((string) $this->config['base_url'], '/').'/oauth2/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'scope' => $scope,
            ])
            ->throw()
            ->json() ?? [];

        $token = PaymentManager::pick($json, ['access_token', 'data.access_token', 'accessToken']);
        if (! $token) {
            throw new RuntimeException('زين كاش لم يُرجع رمز دخول.');
        }
        $ttl = max(60, (int) (PaymentManager::pick($json, ['expires_in', 'data.expires_in']) ?? 3600) - 60);
        Cache::put($cacheKey, $token, $ttl);

        return (string) $token;
    }
}

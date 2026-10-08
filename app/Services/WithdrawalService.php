<?php

namespace App\Services;

use App\Domain\CampaignRules;
use App\Models\User;
use App\Models\Withdrawal;
use App\Payments\GatewayResult;
use App\Payments\PaymentManager;
use App\Payments\ZainCashGateway;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly LedgerService $ledger,
        private readonly PaymentManager $gateways,
    ) {}

    public function request(User $tester, int $amount, string $method, string $account): Withdrawal
    {
        if (! array_key_exists($method, Withdrawal::METHODS)) {
            throw new RuleViolation('طريقة السحب غير مدعومة.');
        }
        $account = $method === 'zaincash' ? Phone::normalize($account) : preg_replace('/\D+/', '', $account);
        if ($account === null || ($method === 'qicard' && strlen($account) !== 16)) {
            throw new RuleViolation($method === 'zaincash' ? 'رقم محفظة زين كاش غير صحيح.' : 'رقم بطاقة كي يجب أن يكون 16 رقماً.');
        }

        return DB::transaction(function () use ($tester, $amount, $method, $account) {
            User::query()->whereKey($tester->id)->lockForUpdate()->first();

            if ($tester->withdrawals()->whereIn('status', [Withdrawal::PENDING, Withdrawal::PROCESSING])->exists()) {
                throw new RuleViolation('لديك طلب سحب قيد المراجعة. انتظر حتى يُنفَّذ.');
            }

            $balance = $this->ledger->balance(LedgerService::WALLET, $tester);
            $minimum = $this->settings->int('withdraw_min');
            $error = CampaignRules::withdrawalError($balance, $amount, $minimum);
            if ($error === 'below_minimum') {
                throw new RuleViolation('الحد الأدنى للسحب '.number_format($minimum).' د.ع.');
            }
            if ($error === 'insufficient_balance') {
                throw new RuleViolation('المبلغ أكبر من رصيدك.');
            }

            $withdrawal = Withdrawal::query()->create([
                'user_id' => $tester->id,
                'amount' => $amount,
                'method' => $method,
                'account' => $account,
                'status' => Withdrawal::PENDING,
            ]);

            $this->ledger->move('withdrawal_requested', "طلب سحب {$tester->name}", LedgerService::WALLET, $tester, LedgerService::WITHDRAWAL_HOLD, $tester, $amount, $withdrawal);

            return $withdrawal;
        });
    }

    /**
     * موافقة الإدارة. لزين كاش مع تفعيل الصرف الآلي: يُرسل المبلغ عبر API ويُتابَع.
     * غير ذلك: الإدارة حوّلت يدوياً وتُدخل رقم الحوالة.
     */
    public function approve(Withdrawal $withdrawal, User $admin, ?string $transferRef = null): Withdrawal
    {
        $withdrawal = $this->lockPending($withdrawal);

        if ($withdrawal->method === 'zaincash' && config('mihak.auto_zaincash_payouts') && ! config('mihak.fake_payments')) {
            $gateway = $this->gateways->driver('zaincash');
            if ($gateway instanceof ZainCashGateway) {
                try {
                    // المرجع ثابت لكل طلب، فإعادة المحاولة لا تصرف المبلغ مرتين
                    $h = md5('mihak-withdrawal-'.$withdrawal->id.'-'.config('app.key'));
                    $reference = sprintf('%s-%s-4%s-a%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), substr($h, 17, 3), substr($h, 20, 12));
                    $itemId = $gateway->disburse(Phone::toMsisdn($withdrawal->account), $withdrawal->amount, $reference, 'سحب رصيد مِحَكّ');
                } catch (\Throwable $e) {
                    Log::error('ZainCash disbursement failed', ['withdrawal' => $withdrawal->id, 'error' => $e->getMessage()]);
                    throw new RuleViolation('تعذّر الصرف عبر زين كاش. راجع لوحة زين كاش قبل إعادة المحاولة.');
                }
                $withdrawal->update(['status' => Withdrawal::PROCESSING, 'gateway_ref' => $itemId, 'admin_id' => $admin->id]);

                return $withdrawal;
            }
        }

        return $this->markPaid($withdrawal, $admin, $transferRef);
    }

    public function markPaid(Withdrawal $withdrawal, ?User $admin, ?string $transferRef): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $transferRef) {
            $withdrawal = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if (! in_array($withdrawal->status, [Withdrawal::PENDING, Withdrawal::PROCESSING], true)) {
                throw new RuleViolation('عولج هذا الطلب مسبقاً.');
            }
            $withdrawal->update([
                'status' => Withdrawal::PAID,
                'gateway_ref' => $transferRef ?: $withdrawal->gateway_ref,
                'admin_id' => $admin?->id ?? $withdrawal->admin_id,
                'processed_at' => now(),
            ]);
            $this->ledger->move('withdrawal_paid', "تحويل سحب {$withdrawal->user->name} إلى {$withdrawal->methodLabel()}", LedgerService::WITHDRAWAL_HOLD, $withdrawal->user, LedgerService::EXTERNAL, null, $withdrawal->amount, $withdrawal);

            return $withdrawal;
        });
    }

    public function reject(Withdrawal $withdrawal, User $admin, string $note, string $status = Withdrawal::REJECTED): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $note, $status) {
            $withdrawal = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if (! in_array($withdrawal->status, [Withdrawal::PENDING, Withdrawal::PROCESSING], true)) {
                throw new RuleViolation('عولج هذا الطلب مسبقاً.');
            }
            $withdrawal->update(['status' => $status, 'admin_id' => $admin->id, 'admin_note' => $note, 'processed_at' => now()]);
            $this->ledger->move('withdrawal_returned', "إعادة مبلغ سحب {$withdrawal->user->name} إلى رصيده", LedgerService::WITHDRAWAL_HOLD, $withdrawal->user, LedgerService::WALLET, $withdrawal->user, $withdrawal->amount, $withdrawal);

            return $withdrawal;
        });
    }

    /** متابعة سحوبات زين كاش المرسلة آلياً */
    public function syncProcessing(User $systemAdmin): int
    {
        $updated = 0;
        $gateway = $this->gateways->driver('zaincash');
        if (! $gateway instanceof ZainCashGateway) {
            return 0;
        }
        foreach (Withdrawal::query()->where('status', Withdrawal::PROCESSING)->whereNotNull('gateway_ref')->get() as $withdrawal) {
            try {
                $status = $gateway->disbursementStatus($withdrawal->gateway_ref);
                if ($status === GatewayResult::PAID) {
                    $this->markPaid($withdrawal, null, null);
                    $updated++;
                } elseif ($status === GatewayResult::FAILED) {
                    $this->reject($withdrawal, $systemAdmin, 'فشل الصرف من زين كاش، أُعيد المبلغ للرصيد.', Withdrawal::FAILED);
                    $updated++;
                }
            } catch (\Throwable $e) {
                Log::warning('Disbursement sync failed', ['withdrawal' => $withdrawal->id, 'error' => $e->getMessage()]);
            }
        }

        return $updated;
    }

    private function lockPending(Withdrawal $withdrawal): Withdrawal
    {
        $fresh = Withdrawal::query()->findOrFail($withdrawal->id);
        if ($fresh->status !== Withdrawal::PENDING) {
            throw new RuleViolation('عولج هذا الطلب مسبقاً.');
        }

        return $fresh;
    }
}

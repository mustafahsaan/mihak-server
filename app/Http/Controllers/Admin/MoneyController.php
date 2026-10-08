<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerTransaction;
use App\Models\Refund;
use App\Models\Withdrawal;
use App\Services\RefundService;
use App\Services\WithdrawalService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** السحوبات والاستردادات ودفتر الحركات */
class MoneyController extends Controller
{
    public function withdrawals(Request $request): View
    {
        $status = $request->query('status', Withdrawal::PENDING);

        return view('admin.withdrawals', [
            'status' => $status,
            'withdrawals' => Withdrawal::query()
                ->with('user')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'autoZainCash' => (bool) config('mihak.auto_zaincash_payouts'),
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $data = $request->validate(['transfer_ref' => ['nullable', 'string', 'max:100']]);
        $withdrawal = $service->approve($withdrawal, $request->user(), $data['transfer_ref'] ?? null);

        return back()->with('success', $withdrawal->status === Withdrawal::PROCESSING
            ? 'أُرسل المبلغ إلى زين كاش ونتابع حالته.'
            : 'سُجّل تحويل '.Dates::money($withdrawal->amount).' إلى '.$withdrawal->user->name.'.');
    }

    public function reject(Request $request, Withdrawal $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], ['note.required' => 'اكتب سبب الرفض ليراه المختبِر.']);
        $service->reject($withdrawal, $request->user(), $data['note']);

        return back()->with('success', 'رُفض الطلب وأُعيد المبلغ إلى رصيد المختبِر.');
    }

    public function refunds(): View
    {
        return view('admin.refunds', [
            'refunds' => Refund::query()->with(['user', 'campaign.androidApp', 'payment'])->latest('id')->paginate(30),
        ]);
    }

    public function refundViaGateway(Request $request, Refund $refund, RefundService $service): RedirectResponse
    {
        $refund = $service->viaGateway($refund, $request->user());

        return back()->with('success', $refund->status === Refund::PAID ? 'أُعيد المبلغ عبر البوابة.' : 'أُرسل الاسترداد للبوابة وننتظر النتيجة.');
    }

    public function refundManual(Request $request, Refund $refund, RefundService $service): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:100']], ['reference.required' => 'اكتب رقم الحوالة.']);
        $service->markPaid($refund, $request->user(), $data['reference']);

        return back()->with('success', 'سُجّل الاسترداد اليدوي.');
    }

    public function ledger(): View
    {
        return view('admin.ledger', [
            'transactions' => LedgerTransaction::query()->with('entries')->latest('id')->paginate(40),
        ]);
    }
}

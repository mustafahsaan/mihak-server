<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PaymentController extends Controller
{
    public function choose(Request $request, Campaign $campaign): View|RedirectResponse
    {
        abort_unless($campaign->androidApp->developer_id === $request->user()->id, 404);
        if ($campaign->status !== Campaign::UNPAID) {
            return redirect()->route('campaigns.show', $campaign);
        }

        return view('dev.pay', [
            'campaign' => $campaign,
            'app' => $campaign->androidApp,
            'terms' => $campaign->terms(),
            'gateways' => Payment::GATEWAYS,
            'fake' => (bool) config('mihak.fake_payments'),
        ]);
    }

    public function start(Request $request, Campaign $campaign, PaymentService $payments): RedirectResponse
    {
        abort_unless($campaign->androidApp->developer_id === $request->user()->id, 404);
        $data = $request->validate(['gateway' => ['required', Rule::in(array_keys(Payment::GATEWAYS))]]);

        try {
            $result = $payments->start($campaign, $request->user(), $data['gateway']);
        } catch (\App\Support\RuleViolation $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Payment start failed', ['campaign' => $campaign->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'تعذّر الاتصال ببوابة الدفع. حاول بعد قليل أو اختر وسيلة أخرى.');
        }

        return redirect()->away($result['redirect']);
    }

    /** البوابة تعيد المطوّر إلى هنا بعد الدفع. نتأكد من الحالة مباشرة من البوابة. */
    public function return(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        try {
            $status = $payments->confirm($payment, $request->query());
        } catch (Throwable $e) {
            Log::warning('Payment confirm on return failed', ['payment' => $payment->id, 'error' => $e->getMessage()]);
            $status = Payment::PENDING;
        }

        $target = route('campaigns.show', $payment->campaign_id);

        return match ($status) {
            Payment::PAID => redirect($target)->with('success', 'تم الدفع ('.Dates::money($payment->amount).') وحُجز المبلغ في الضمان. بدأ جمع المختبِرين.'),
            Payment::FAILED => redirect()->route('campaigns.pay', $payment->campaign_id)->with('error', 'لم تكتمل عملية الدفع. يمكنك المحاولة مرة أخرى.'),
            Payment::MISMATCH => redirect($target)->with('error', 'وصل مبلغ مختلف عن المطلوب. ستراجع الإدارة الدفعة وتتواصل معك.'),
            default => redirect($target)->with('success', 'استلمنا طلب الدفع وننتظر تأكيد البوابة. تتحدث الصفحة خلال دقائق.'),
        };
    }
}

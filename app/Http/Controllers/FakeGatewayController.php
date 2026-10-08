<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** صفحة الدفع التجريبية. تعمل فقط مع MIHAK_FAKE_PAYMENTS=true */
class FakeGatewayController extends Controller
{
    public function show(Payment $payment): View
    {
        abort_unless(config('mihak.fake_payments'), 404);

        return view('payments.fake', ['payment' => $payment]);
    }

    public function complete(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless(config('mihak.fake_payments'), 404);
        $payment->remember(['fake_status' => $request->input('outcome') === 'pay' ? 'paid' : 'failed']);

        return redirect($payment->payload['fake_return_url'] ?? route('payments.return', $payment));
    }
}

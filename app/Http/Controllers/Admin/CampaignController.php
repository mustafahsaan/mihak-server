<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Enrollment;
use App\Services\LedgerService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('admin.campaigns', [
            'status' => $status,
            'campaigns' => Campaign::query()
                ->with('androidApp.developer')
                ->withCount(['enrollments as active_count' => fn ($q) => $q->where('status', Enrollment::ACTIVE)])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function show(Campaign $campaign, LedgerService $ledger): View
    {
        $campaign->load(['androidApp.developer', 'payments', 'feedback.tester']);

        return view('admin.campaign', [
            'campaign' => $campaign,
            'enrollments' => $campaign->enrollments()->with('tester')->orderBy('id')->get(),
            'escrow' => $ledger->balance(LedgerService::ESCROW, $campaign),
        ]);
    }

    public function settings(SettingsService $settings): View
    {
        return view('admin.settings', [
            'values' => $settings->all(),
            'terms' => $settings->terms(),
            'fonts' => config('mihak.fonts'),
        ]);
    }

    public function updateFont(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'site_font' => ['required', \Illuminate\Validation\Rule::in(array_keys(config('mihak.fonts')))],
        ]);
        $settings->put($data);

        return back()->with('success', 'تغيّر خط الموقع إلى '.$data['site_font'].'.');
    }

    public function updateSettings(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:1000', 'max:10000000'],
            'commission_percent' => ['required', 'integer', 'min:0', 'max:60'],
            'required_testers' => ['required', 'integer', 'min:1', 'max:100'],
            'reserve_testers' => ['required', 'integer', 'min:0', 'max:20'],
            'test_days' => ['required', 'integer', 'min:'.config('mihak.google_days'), 'max:40'],
            'miss_allowed' => ['required', 'integer', 'min:0', 'max:10'],
            'min_open_seconds' => ['required', 'integer', 'min:5', 'max:3600'],
            'withdraw_min' => ['required', 'integer', 'min:0', 'max:10000000'],
            'seat_confirm_hours' => ['required', 'integer', 'min:1', 'max:168'],
        ], [
            'test_days.min' => 'المدة لا تقل عن '.config('mihak.google_days').' يوماً، وهو شرط Google.',
        ]);
        $data['failure_pays_completers'] = $request->boolean('failure_pays_completers');
        $data['refund_includes_commission'] = $request->boolean('refund_includes_commission');

        try {
            \App\Domain\Terms::fromSettings(array_merge($settings->all(), $data));
        } catch (\InvalidArgumentException) {
            throw \Illuminate\Validation\ValidationException::withMessages(['price' => 'هذه القيم تجعل حصة المختبِر صفراً. ارفع السعر أو قلّل العمولة أو المقاعد.']);
        }

        $settings->put($data);

        return back()->with('success', 'حُفظت الإعدادات وتُطبّق على التطبيقات التي تُدفع من الآن.');
    }
}

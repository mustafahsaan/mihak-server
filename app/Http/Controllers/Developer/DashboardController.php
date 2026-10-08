<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\AndroidApp;
use App\Models\Campaign;
use App\Services\CampaignService;
use App\Services\SettingsService;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, SettingsService $settings): View
    {
        $apps = $request->user()->apps()
            ->with(['campaigns' => fn ($q) => $q->latest('id')->withCount([
                'enrollments as active_count' => fn ($e) => $e->where('status', 'active'),
                'feedback',
            ])])
            ->latest('id')
            ->get();

        return view('dev.dashboard', [
            'apps' => $apps,
            'terms' => $settings->terms(),
            'groupEmail' => config('mihak.group_email'),
        ]);
    }

    public function storeApp(Request $request, CampaignService $campaigns): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'package_name' => [
                'required', 'string', 'max:190', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+$/',
                Rule::unique('android_apps')->where('developer_id', $request->user()->id),
            ],
            'opt_in_url' => ['nullable', 'url', 'starts_with:https://play.google.com/apps/testing/'],
        ], [
            'package_name.regex' => 'اسم الحزمة بصيغة com.example.app، بحروف إنجليزية ونقاط.',
            'package_name.unique' => 'أضفت هذا التطبيق مسبقاً. تجده في قائمة تطبيقاتك.',
            'opt_in_url.starts_with' => 'رابط الاختبار يبدأ بـ https://play.google.com/apps/testing/',
        ]);

        $app = $request->user()->apps()->create($data);
        $campaign = $campaigns->createFor($app);

        return redirect()->route('campaigns.pay', $campaign);
    }

    /** دورة اختبار جديدة مدفوعة لتطبيق سبق اختباره */
    public function newCampaign(Request $request, AndroidApp $app, CampaignService $campaigns): RedirectResponse
    {
        abort_unless($app->developer_id === $request->user()->id, 404);
        $campaign = $campaigns->createFor($app);
        if ($campaign->status !== Campaign::UNPAID) {
            throw new RuleViolation('لدى هذا التطبيق اختبار قائم.');
        }

        return redirect()->route('campaigns.pay', $campaign);
    }
}

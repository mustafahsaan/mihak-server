<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(WalletService $wallets): View
    {
        $integrity = $wallets->integrity();

        return view('admin.dashboard', [
            'integrity' => $integrity,
            'totals' => $integrity['totals'],
            'accrued' => (int) Enrollment::query()->where('status', Enrollment::ACTIVE)
                ->whereHas('campaign', fn ($q) => $q->where('status', Campaign::TESTING))->sum('accrued'),
            'counts' => [
                'testing' => Campaign::query()->where('status', Campaign::TESTING)->count(),
                'recruiting' => Campaign::query()->where('status', Campaign::RECRUITING)->count(),
                'failed' => Campaign::query()->where('status', Campaign::FAILED)->count(),
                'testers' => User::query()->where('role', User::TESTER)->count(),
                'developers' => User::query()->where('role', User::DEVELOPER)->count(),
                'paid_apps' => Payment::query()->where('status', Payment::PAID)->count(),
                'mismatch' => Payment::query()->where('status', Payment::MISMATCH)->count(),
            ],
            'pendingWithdrawals' => Withdrawal::query()->where('status', Withdrawal::PENDING)->count(),
            'pendingRefunds' => Refund::query()->where('status', Refund::PENDING)->count(),
        ]);
    }
}

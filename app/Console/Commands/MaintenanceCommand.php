<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CampaignService;
use App\Services\PaymentService;
use App\Services\WithdrawalService;
use Illuminate\Console\Command;

class MaintenanceCommand extends Command
{
    protected $signature = 'mihak:maintain';

    protected $description = 'مهام دورية: تحرير المقاعد غير المؤكدة، تأكيد الدفعات المعلّقة، ومتابعة سحوبات زين كاش';

    public function handle(CampaignService $campaigns, PaymentService $payments, WithdrawalService $withdrawals): int
    {
        $released = $campaigns->releaseUnconfirmedSeats();
        $reconciled = $payments->reconcilePending();

        $synced = 0;
        if (config('mihak.auto_zaincash_payouts')) {
            $admin = User::query()->where('role', User::ADMIN)->orderBy('id')->first();
            if ($admin) {
                $synced = $withdrawals->syncProcessing($admin);
            }
        }

        $this->info("مقاعد محرّرة: {$released} · دفعات أُكّدت: {$reconciled} · سحوبات حُدّثت: {$synced}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LedgerCheckCommand extends Command
{
    protected $signature = 'mihak:ledger-check';

    protected $description = 'يتحقق أن الدفتر المالي متوازن ويكتب تحذيراً في السجل إن لم يكن';

    public function handle(WalletService $wallets): int
    {
        $report = $wallets->integrity();
        foreach ($report['checks'] as $check) {
            $this->line(($check['ok'] ? '✓ ' : '✗ ').$check['label'].' — '.$check['detail']);
        }
        if (! $report['ok']) {
            Log::critical('Ledger integrity check failed', $report);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

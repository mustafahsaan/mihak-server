<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * بيانات تجريبية للتشغيل المحلي فقط:
 *   php artisan db:seed --class=DemoSeeder
 *
 * - مطوّر: 07700000001 / password123
 * - 16 مختبِراً: 07800000001 حتى 07800000016 / password123
 * - تطبيق مدفوع اكتملت مقاعده، فيبدأ اختباره. جرّب بعدها:
 *   php artisan mihak:demo-day 1   (كررها 16 مرة لترى التسوية)
 */
class DemoSeeder extends Seeder
{
    public function run(CampaignService $campaigns): void
    {
        if (app()->isProduction()) {
            $this->command?->error('لا تشغّل البيانات التجريبية على السيرفر الحقيقي.');

            return;
        }

        $developer = User::query()->updateOrCreate(['phone' => '07700000001'], [
            'name' => 'مطوّر تجريبي',
            'password' => 'password123',
            'role' => User::DEVELOPER,
            'phone_verified_at' => now(),
        ]);

        $testers = collect(range(1, 16))->map(fn (int $i) => User::query()->updateOrCreate(
            ['phone' => sprintf('078%08d', $i)],
            [
                'name' => "مختبِر {$i}",
                'email' => "tester{$i}@gmail.com",
                'password' => 'password123',
                'role' => User::TESTER,
                'device_id' => 'demo-device-'.$i,
                'phone_verified_at' => now(),
            ],
        ));

        $app = $developer->apps()->firstOrCreate(
            ['package_name' => 'com.demo.restaurants'],
            ['name' => 'دليل مطاعم بغداد'],
        );
        $campaign = $campaigns->createFor($app);

        if ($campaign->status === \App\Models\Campaign::UNPAID) {
            $payment = $campaign->payments()->create([
                'user_id' => $developer->id,
                'gateway' => 'zaincash',
                'amount' => $campaign->price,
                'status' => Payment::PENDING,
                'external_ref' => (string) Str::uuid(),
                'gateway_txn_id' => 'DEMO-'.$campaign->id,
            ]);
            $campaigns->markPaid($payment, ['demo' => true]);
            $campaign->refresh();

            foreach ($testers->take($campaign->slots) as $tester) {
                $enrollment = $campaigns->join($campaign, $tester);
                $campaigns->confirmInstall($enrollment);
            }
        }

        $this->command?->info("الحملة #{$campaign->id} جاهزة. المطوّر: 07700000001 / password123");
    }
}

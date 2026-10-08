<?php

namespace App\Payments;

use InvalidArgumentException;

class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $resolved = [];

    public function driver(string $gateway): PaymentGateway
    {
        if (! array_key_exists($gateway, \App\Models\Payment::GATEWAYS)) {
            throw new InvalidArgumentException("بوابة غير معروفة: {$gateway}");
        }

        return $this->resolved[$gateway] ??= match (true) {
            config('mihak.fake_payments') => new FakeGateway,
            $gateway === 'zaincash' => new ZainCashGateway(config('services.zaincash')),
            $gateway === 'qicard' => new QiCardGateway(config('services.qicard')),
        };
    }

    /** يقرأ قيمة من JSON بأكثر من مسار محتمل، لأن أشكال الاستجابة قد تختلف بين بيئة التجربة والإنتاج */
    public static function pick(array $json, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($json, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}

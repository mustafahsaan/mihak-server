<?php

namespace App\Support;

final class Phone
{
    /** يحوّل الأشكال الشائعة (+964 7.., 9647.., 07..) إلى 07XXXXXXXXX، أو null إذا لم يكن رقماً عراقياً */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);
        if (str_starts_with($digits, '00964')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '964')) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '7')) {
            $digits = '0'.$digits;
        }

        return preg_match('/^07\d{9}$/', $digits) ? $digits : null;
    }

    /** صيغة زين كاش للصرف: 9647XXXXXXXXX */
    public static function toMsisdn(string $phone): string
    {
        return '964'.substr((string) self::normalize($phone), 1);
    }
}

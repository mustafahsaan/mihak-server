<?php

namespace App\Support;

use RuntimeException;

/** فك والتحقق من JWT بتوقيع HS256 (يستخدمه زين كاش في رابط الرجوع والإشعارات). */
final class Jwt
{
    /** @return array<string, mixed> */
    public static function decodeHs256(string $jwt, string $key, int $leewaySeconds = 120): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3 || $key === '') {
            throw new RuntimeException('JWT غير صالح.');
        }
        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode(self::base64UrlDecode($header64), true);
        if (! is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('خوارزمية JWT غير مدعومة.');
        }

        $expected = self::base64UrlEncode(hash_hmac('sha256', $header64.'.'.$payload64, $key, true));
        if (! hash_equals($expected, $signature64)) {
            throw new RuntimeException('توقيع JWT غير صحيح.');
        }

        $payload = json_decode(self::base64UrlDecode($payload64), true);
        if (! is_array($payload)) {
            throw new RuntimeException('محتوى JWT غير صالح.');
        }
        if (isset($payload['exp']) && is_numeric($payload['exp']) && (int) $payload['exp'] + $leewaySeconds < time()) {
            throw new RuntimeException('انتهت صلاحية JWT.');
        }

        return $payload;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/').str_repeat('=', (4 - strlen($data) % 4) % 4);

        return (string) base64_decode($padded, true);
    }
}

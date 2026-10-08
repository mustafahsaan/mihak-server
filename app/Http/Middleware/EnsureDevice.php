<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حساب المختبِر مربوط بجهاز واحد: كل طلب من تطبيق الهاتف يحمل X-Device-Id
 * ويجب أن يطابق الجهاز المسجّل، حتى لا يُستخدم حساب واحد على عدة أجهزة.
 */
class EnsureDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $device = (string) $request->header('X-Device-Id', '');

        if ($user && $user->isTester() && $user->device_id !== null && ! hash_equals($user->device_id, $device)) {
            return response()->json([
                'message' => 'هذا الحساب مسجّل على جهاز آخر. تواصل مع الدعم لنقله.',
                'device_mismatch' => true,
            ], 403);
        }

        return $next($request);
    }
}

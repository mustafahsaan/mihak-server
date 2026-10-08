<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** الاستخدام: ->middleware('role:admin') أو 'role:developer,admin' */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true) || ! $user->is_active) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'لا تملك صلاحية لهذا الإجراء.'], 403);
            }
            abort(403, 'لا تملك صلاحية لهذه الصفحة.');
        }

        // حسابات أُنشئت والرمز مفعّل ثم أُوقف لاحقاً: تُؤكَّد تلقائياً
        if (! $user->isVerified() && ! app(\App\Services\OtpService::class)->autoVerifyIfDisabled($user)) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'أكّد رقم هاتفك أولاً.', 'needs_verification' => true], 403);
            }

            return redirect()->route('otp.show');
        }

        return $next($request);
    }
}

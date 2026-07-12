<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class CheckDeviceSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
{
    $currentIp = $request->ip();
    // ফ্রন্টএন্ড থেকে পাঠানো ডিভাইস আইডি (Header বা Cookie থেকে)
    $deviceId = $request->header('X-Device-ID'); 

    // চেক করুন এই আইপি বা ডিভাইস দিয়ে কয়টি রেজিস্ট্রেশন বা লগইন আছে
    $existingUser = User::where('device_id', $deviceId)->first();

    if ($existingUser && $existingUser->is_banned) {
        return response()->json(['message' => 'Your device is banned.'], 403);
    }

    return $next($request);
}
}

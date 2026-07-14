<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Jenssegers\Agent\Agent;

class AuthController extends Controller {
    
    // 📱 FLUTTER MOBILE API: রেজিস্ট্রেশন (Device Limit Check সহ)
public function apiRegister(Request $request) 
{
    // খালি স্ট্রিং পাঠানো হলে তা null হিসেবে গণ্য করার জন্য ConvertEmptyStringsToNull মিডলওয়্যার কাজ না করলে ম্যানুয়ালি সেট করা
    if ($request->has('referred_by') && empty($request->referred_by)) {
        $request->merge(['referred_by' => null]);
    }

    $request->validate([
        'name' => 'required|string|max:255',
        'username' => 'required|string|min:5|unique:users,username',
        'mobile' => 'required|string|unique:users,mobile',
        'email' => 'nullable|email|unique:users,email',
        'password' => 'required|string|min:6|confirmed', // 👈 confirmed যুক্ত করা হয়েছে
        'referred_by' => 'nullable|string|exists:users,referral_code', // 👈 রেফারেল কোড থাকলে ডাটাবেজে থাকতে হবে
    ]);

    $agent = new \Jenssegers\Agent\Agent();
    $deviceId = md5($agent->browser() . $agent->platform() . $agent->device());
    $ip = $request->ip();

    // মাল্টি-অ্যাকাউন্ট চেক
    $existingAccounts = \App\Models\User::where('register_device_id', $deviceId)
                                ->orWhere('register_ip', $ip)
                                ->count();

    if ($existingAccounts >= 2) {
        return response()->json([
            'status' => false,
            'message' => 'Multi-account abuse detected!'
        ], 403);
    }

    // ডাটাবেজ ট্রানজেকশন
    $user = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $ip, $deviceId) {
        
        // ১. ইউজার ক্রিয়েট
        $newUser = \App\Models\User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email ?? null,
            'mobile' => $request->mobile,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role' => 'player',
            'referral_code' => strtoupper(\Illuminate\Support\Str::random(8)), // নতুন ইউজারের কোড
            'referred_by' => $request->referred_by, // রেফারারের কোড/আইডি
            'register_ip' => $ip,
            'register_device_id' => $deviceId,
            'last_login_ip' => $ip,
        ]);

        // ২. ওয়ালেট অটো-ক্রিয়েট
        \App\Models\Wallet::create([
            'user_id' => $newUser->id,
            'balance' => 0.00,
            'bonus_balance' => 0.00,
        ]);

        return $newUser;
    });

    // Sanctum টোকেন জেনারেট
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'status' => true,
        'message' => 'Registration successful with wallet and referral setup.',
        'token' => $token,
        'user' => $user
    ], 201);
}
    // 📱 FLUTTER MOBILE API: লগইন
    public function apiLogin(Request $request) {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('username', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid username or password'
            ], 401);
        }

        $user = User::where('username', $request->username)->first();
        
        // লগইন করার সময়ও লাস্ট আইপি আপডেট হবে
        $user->update(['last_login_ip' => $request->ip()]);
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ], 200);
    }

    // 📱 FLUTTER MOBILE API: লগআউট
    public function apiLogout(Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    // 📱 FLUTTER MOBILE API: ইউজার প্রোফাইল ডিটেইলস
    public function apiProfile(Request $request) {
        $user = $request->user();

        // ইউজার রিলেশনশিপ (Wallet, Referrals, etc.) একসাথে লোড করা হচ্ছে
        $user->load(['wallet']);

        return response()->json([
            'status' => true,
            'message' => 'User profile retrieved successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'role' => $user->role,
                'referral_code' => $user->referral_code,
                'referred_by' => $user->referred_by,
                'wallet' => [
                    'balance' => $user->wallet ? number_format($user->wallet->balance, 2) : "0.00",
                    'bonus_balance' => $user->wallet ? number_format($user->wallet->bonus_balance, 2) : "0.00",
                ],
                'stats' => [
                    'total_referrals' => $user->referrals()->count(),
                ],
                'meta' => [
                    'register_ip' => $user->register_ip,
                    'last_login_ip' => $user->last_login_ip,
                    'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
                ]
            ]
        ], 200);
    }
}
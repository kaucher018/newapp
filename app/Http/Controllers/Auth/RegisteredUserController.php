<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Jenssegers\Agent\Agent;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // ইউনিক ডিভাইস ফিঙ্গারপ্রিন্ট তৈরি (যদি রিকোয়েস্টে ডিভাইস আইডি না থাকে)
        $agent = new Agent();
        $generatedDeviceId = md5($agent->browser() . $agent->platform() . $agent->device());
        
        // ফ্রন্টএন্ড বা হেডার থেকে আসলে সেটা নিবে, না হয় ব্যাকএন্ড নিজে জেনারেট করবে
        $deviceId = $request->header('X-Device-ID') ?? $request->input('device_id') ?? $generatedDeviceId;
        $ip = $request->ip();

        // 🚨 সিকিউরিটি চেক: এই ডিভাইস বা আইপি দিয়ে অলরেডি কয়টা অ্যাকাউন্ট আছে?
        $existingAccounts = User::where('register_device_id', $deviceId)
                                ->orWhere('register_ip', $ip)
                                ->count();

        if ($existingAccounts >= 2) {
            return back()->withErrors(['error' => 'Abuse Detected! Multi-account creation is restricted on this device/IP.']);
        }

        // অ্যাকাউন্ট তৈরি এবং আইপি-ডিভাইস আইডি সেভ
       $user = User::create([
    'name' => $request->name,
    'username' => $request->username, // 👈 এই লাইনটি নিশ্চিত করুন
    'email' => $request->email,
    'mobile' => $request->mobile,     // 👈 এই লাইনটি নিশ্চিত করুন
    'password' => Hash::make($request->password),
    'role' => 'user', 
    'register_ip' => $ip,
    'register_device_id' => $deviceId,
    'last_login_ip' => $ip,
]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}